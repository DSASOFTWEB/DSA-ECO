<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Acesso;
use App\Models\Carteirinha;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\TipoEntrada;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Regra de controle de acesso. Usada tanto pela catraca/totem (validação por
 * QR, tempo real, nunca lança exceção) quanto pelo PDV (check-in manual de
 * cliente com plano, buscado por CPF/nome/código/cartão em vez de QR).
 */
class AcessoService
{
    public function validarEEntrar(string $codigo, int $unidadeId, string $tipo = 'entrada', ?string $dispositivo = null): array
    {
        // Unidade::findOrFail já aplica o TenantScope (o token da
        // catraca/totem é autenticado via Sanctum) — se o id não pertencer
        // à empresa do dispositivo, nem chega a resolver a unidade.
        $unidade = Unidade::findOrFail($unidadeId);

        // Carteirinha não tem TenantScope próprio: restringe explicitamente
        // à empresa da unidade, senão um código de OUTRA empresa (cartão
        // achado/vazado) seria validado normalmente na catraca errada.
        $carteirinha = Carteirinha::with(['cliente.contratoAtivo.mensalidades', 'dependente.contratos.mensalidades'])
            ->where('codigo', $codigo)
            ->where(function ($q) use ($unidade) {
                $q->whereHas('cliente', fn ($qq) => $qq->where('empresa_id', $unidade->empresa_id))
                    ->orWhereHas('dependente.cliente', fn ($qq) => $qq->where('empresa_id', $unidade->empresa_id));
            })
            ->first();

        [$autorizado, $motivo] = $this->avaliar($carteirinha);

        $acesso = Acesso::create([
            'carteirinha_id' => $carteirinha?->id,
            'plano_id' => $carteirinha ? $this->contratoAtivoDoTitular($carteirinha)?->plano_id : null,
            'unidade_id' => $unidadeId,
            'tipo' => $tipo,
            'origem' => 'catraca',
            'dispositivo' => $dispositivo,
            'autorizado' => $autorizado,
            'motivo_negado' => $autorizado ? null : $motivo,
            'registrado_em' => now(),
        ]);

        // Sem carteirinha correspondente ao código lido: nada mais a retornar.
        if (! $carteirinha) {
            return ['autorizado' => false, 'motivo' => $motivo, 'acesso' => $acesso, 'titular' => null];
        }

        return [
            'autorizado' => $autorizado,
            'motivo' => $motivo,
            'acesso' => $acesso,
            'titular' => $carteirinha->titular(),
        ];
    }

    /**
     * Busca clientes por CPF, código do cliente (ID/matrícula), nome ou
     * código da carteirinha (cartão/QR/leitor de código de barras) — usada
     * pela tela de check-in do PDV, onde o operador digita, escaneia com a
     * câmera ou passa um leitor de código de barras (que "digita" o código
     * seguido de Enter, como um teclado).
     */
    public function pesquisarClientes(string $termo, int $empresaId, int $limite = 8): Collection
    {
        $termo = trim($termo);

        if ($termo === '') {
            return collect();
        }

        // Match exato de código de carteirinha (QR/cartão/leitor) resolve
        // direto para o titular do plano — inclusive quando a carteirinha
        // escaneada é a de um DEPENDENTE, não a do cliente titular, caso em
        // que a busca abaixo (que só olha Cliente::carteirinha) não acharia nada.
        // Restrito à empresa do operador: Carteirinha e Dependente não têm
        // TenantScope próprio, então sem este filtro um código de outra
        // empresa vazaria o cliente/dependente titular dele.
        $carteirinha = Carteirinha::where('codigo', $termo)
            ->where(function ($q) use ($empresaId) {
                $q->whereHas('cliente', fn ($qq) => $qq->where('empresa_id', $empresaId))
                    ->orWhereHas('dependente.cliente', fn ($qq) => $qq->where('empresa_id', $empresaId));
            })
            ->first();
        if ($carteirinha) {
            $titular = $carteirinha->titular();
            $clienteDaCarteirinha = $titular instanceof \App\Models\Dependente ? $titular->cliente : $titular;

            if ($clienteDaCarteirinha) {
                return collect([$clienteDaCarteirinha]);
            }
        }

        $cpfLimpo = preg_replace('/\D/', '', $termo);

        return Cliente::query()
            ->where(function ($query) use ($termo, $cpfLimpo) {
                $query->where('nome', 'like', "%{$termo}%")
                    ->orWhereHas('carteirinha', fn ($q) => $q->where('codigo', $termo));

                if ($cpfLimpo !== '') {
                    $query->orWhere('cpf', 'like', "%{$cpfLimpo}%");
                }

                if (ctype_digit($termo)) {
                    $query->orWhere('id', (int) $termo);
                }
            })
            ->orderBy('nome')
            ->limit($limite)
            ->get();
    }

    /**
     * Busca exata (CPF completo, nome completo ou só o primeiro nome, sem
     * "%like%" no meio) usada pelo link público de autoatendimento — nunca
     * lista candidatos parecidos pra um visitante anônimo (isso vazaria
     * nome de outros clientes), então só resolve quando o termo aponta pra
     * exatamente 1 cliente (se dois clientes têm o mesmo primeiro nome, a
     * busca por só o primeiro nome fica ambígua de propósito e não resolve
     * nenhum dos dois — o visitante precisa digitar o nome completo ou o CPF).
     * Sempre restrita à empresa da unidade do link (a página é pública,
     * sem usuário logado, então o TenantScope normal não filtra sozinho).
     */
    public function buscarClienteExatoParaCheckinPublico(string $termo, int $empresaId): ?Cliente
    {
        $termo = trim($termo);

        if ($termo === '') {
            return null;
        }

        $cpfLimpo = preg_replace('/\D/', '', $termo);
        $termoLower = mb_strtolower($termo);

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->where(function ($query) use ($termoLower, $cpfLimpo) {
                $query->whereRaw('LOWER(nome) = ?', [$termoLower])
                    ->orWhereRaw('LOWER(SUBSTRING_INDEX(nome, " ", 1)) = ?', [$termoLower]);

                if ($cpfLimpo !== '') {
                    $query->orWhere('cpf', $cpfLimpo);
                }
            })
            ->limit(2)
            ->get();

        return $clientes->count() === 1 ? $clientes->first() : null;
    }

    /**
     * Situação do plano/contrato do cliente para exibição no check-in do PDV:
     * qual plano, status, vigência, se está ativo/vencido/bloqueado e a data
     * do último pagamento.
     *
     * @return array{tem_plano:bool, contrato:?Contrato, plano_nome:?string, status:?string, ativo:bool, vencido:bool, bloqueado:bool, data_inicio:?\Illuminate\Support\Carbon, data_fim:?\Illuminate\Support\Carbon, ultimo_pagamento:?\Illuminate\Support\Carbon, motivo_bloqueio:?string}
     */
    public function situacaoPlano(Cliente $cliente): array
    {
        $contrato = $cliente->contratos()->with(['plano', 'mensalidades', 'dependentes'])->latest('data_inicio')->first();

        if (! $contrato) {
            return [
                'tem_plano' => false,
                'contrato' => null,
                'plano_nome' => null,
                'status' => null,
                'ativo' => false,
                'vencido' => false,
                'bloqueado' => false,
                'data_inicio' => null,
                'data_fim' => null,
                'ultimo_pagamento' => null,
                'motivo_bloqueio' => 'Cliente sem contrato/plano cadastrado',
            ];
        }

        $motivoInadimplencia = $contrato->estaAtivo() ? $this->motivoInadimplencia($contrato) : null;
        $ultimoPagamento = $contrato->mensalidades->where('status', 'pago')->sortByDesc('data_pagamento')->first();

        return [
            'tem_plano' => true,
            'contrato' => $contrato,
            'plano_nome' => $contrato->plano->nome,
            'status' => $contrato->status,
            'ativo' => $contrato->estaAtivo() && ! $motivoInadimplencia,
            'vencido' => in_array($contrato->status, ['cancelado', 'encerrado'], true),
            'bloqueado' => $motivoInadimplencia !== null || $contrato->status === 'suspenso',
            'data_inicio' => $contrato->data_inicio,
            'data_fim' => $contrato->data_fim,
            'ultimo_pagamento' => $ultimoPagamento?->data_pagamento,
            'motivo_bloqueio' => $motivoInadimplencia,
        ];
    }

    /**
     * Registra a entrada de um cliente com plano identificado manualmente no
     * PDV (CPF/nome/código/cartão), sem gerar venda nem receita — apenas
     * controle de acesso/frequência, exatamente como a leitura de QR na
     * catraca faz para quem tem carteirinha. Quando autorizado, a família
     * inteira (titular + dependentes do contrato) entra junto — cada um
     * ganha seu próprio registro de Acesso, para contagem e voucher corretos.
     *
     * $operador é nulo e $origem passa a 'checkin_online' quando quem
     * registra é o próprio cliente pelo link público de autoatendimento
     * (sem operador da recepção envolvido) — nesse caso $dependentesIds
     * fica null de propósito e a família inteira entra junto, já que não
     * há um operador ali pra perguntar "quem realmente veio hoje".
     *
     * $dependentesIds: null = todos os dependentes do contrato (padrão,
     * inclusive quando não há dependente nenhum); array (mesmo vazio) =
     * restringe aos ids informados — usado pelo check-in presencial, onde o
     * operador confere com o cliente quem de fato está ali antes de imprimir.
     *
     * @return array{autorizado:bool, motivo:?string, acesso:Acesso, acessos:Collection<int,Acesso>, situacao:array}
     */
    public function registrarEntradaPlano(Cliente $cliente, int $unidadeId, ?User $operador = null, string $origem = 'pdv_plano', ?array $dependentesIds = null): array
    {
        $situacao = $this->situacaoPlano($cliente);
        $autorizado = $situacao['ativo'];
        $motivo = $autorizado ? null : ($situacao['motivo_bloqueio'] ?? 'Sem plano ativo');

        return DB::transaction(function () use ($cliente, $unidadeId, $operador, $origem, $situacao, $autorizado, $motivo, $dependentesIds) {
            $agora = now();

            // Todo acesso autorizado ganha um código de validação — mesmo o
            // check-in presencial de plano — pra sempre ter QR/código de
            // barras no comprovante impresso (ver ORIGENS_QUE_EXIGEM_VALIDACAO
            // no Acesso: isso só controla se a portaria PRECISA escanear pra
            // liberar, não se o código existe).
            $planoId = $situacao['contrato']?->plano_id;

            $acesso = Acesso::create([
                'carteirinha_id' => $cliente->carteirinha?->id,
                'cliente_id' => $cliente->id,
                'plano_id' => $planoId,
                'unidade_id' => $unidadeId,
                'tipo' => 'entrada',
                'origem' => $origem,
                'codigo_validacao' => $autorizado ? Acesso::gerarCodigoValidacao() : null,
                'autorizado' => $autorizado,
                'motivo_negado' => $motivo,
                'registrado_por_id' => $operador?->id,
                'registrado_em' => $agora,
            ]);

            $acessos = collect([$acesso]);

            if ($autorizado) {
                $dependentes = $dependentesIds === null
                    ? $situacao['contrato']->dependentes
                    : $situacao['contrato']->dependentes->whereIn('id', $dependentesIds);

                foreach ($dependentes as $dependente) {
                    $acessos->push(Acesso::create([
                        'carteirinha_id' => $dependente->carteirinha?->id,
                        'dependente_id' => $dependente->id,
                        'plano_id' => $planoId,
                        'unidade_id' => $unidadeId,
                        'tipo' => 'entrada',
                        'origem' => $origem,
                        'codigo_validacao' => Acesso::gerarCodigoValidacao(),
                        'autorizado' => true,
                        'registrado_por_id' => $operador?->id,
                        'registrado_em' => $agora,
                    ]));
                }
            }

            return ['autorizado' => $autorizado, 'motivo' => $motivo, 'acesso' => $acesso, 'acessos' => $acessos, 'situacao' => $situacao];
        });
    }

    /**
     * Gera N entradas de cortesia (presente/promocional) — sem venda, sem
     * pagamento, sem lançamento no caixa. Cada uma recebe seu próprio QR de
     * validação (a pessoa costuma usar em outro dia, não na hora que a
     * cortesia é gerada, então precisa ser validada na portaria como
     * qualquer voucher — ver ORIGENS_QUE_EXIGEM_VALIDACAO).
     *
     * @return Collection<int,Acesso>
     */
    public function gerarCortesias(
        int $unidadeId,
        int $quantidade,
        User $operador,
        ?TipoEntrada $tipoEntrada = null,
        ?Cliente $cliente = null,
        ?string $observacao = null,
        ?int $validadeDias = null,
    ): Collection {
        if ($quantidade < 1 || $quantidade > 50) {
            throw new NegocioException('Quantidade inválida (máximo 50 por vez).');
        }

        if ($validadeDias !== null && $validadeDias < 1) {
            throw new NegocioException('Validade inválida (mínimo 1 dia).');
        }

        $validadeAte = $validadeDias ? now()->addDays($validadeDias)->toDateString() : null;

        return DB::transaction(function () use ($unidadeId, $quantidade, $operador, $tipoEntrada, $cliente, $observacao, $validadeAte) {
            $acessos = collect();

            for ($i = 0; $i < $quantidade; $i++) {
                $acessos->push(Acesso::create([
                    'cliente_id' => $cliente?->id,
                    'unidade_id' => $unidadeId,
                    'tipo_entrada_id' => $tipoEntrada?->id,
                    'tipo' => 'entrada',
                    'origem' => 'cortesia',
                    'codigo_validacao' => Acesso::gerarCodigoValidacao(),
                    'autorizado' => true,
                    'observacao' => $observacao,
                    'validade_ate' => $validadeAte,
                    'registrado_por_id' => $operador->id,
                    'registrado_em' => now(),
                ]));
            }

            return $acessos;
        });
    }

    /**
     * Quantidade de cortesias emitidas e já validadas — a visibilidade que
     * a recepção/gerência precisa sobre quantas entradas grátis saíram.
     *
     * @return array{emitidas:int, validadas:int, pendentes:int}
     */
    public function contarCortesias(int $empresaId, ?\Illuminate\Support\Carbon $desde = null): array
    {
        $query = Acesso::daEmpresa($empresaId)->where('origem', 'cortesia')->when($desde, fn ($q, $v) => $q->where('registrado_em', '>=', $v));

        $emitidas = (clone $query)->count();
        $validadas = (clone $query)->whereNotNull('validado_em')->count();

        return ['emitidas' => $emitidas, 'validadas' => $validadas, 'pendentes' => $emitidas - $validadas];
    }

    /**
     * Valida na portaria um voucher comprado/gerado pelo link público
     * (venda avulsa online ou check-in online) — o passo que faltava pra
     * fechar a brecha de fraude: sem isso, o mesmo PDF/print poderia ser
     * usado por qualquer pessoa qualquer número de vezes, já que a entrada
     * era contabilizada na hora da compra/check-in, não na portaria. Cada
     * código só passa por aqui com sucesso uma única vez.
     *
     * @return array{status:string, mensagem:string, acesso:?Acesso}
     */
    public function validarVoucher(string $codigo, User $operador): array
    {
        $codigo = trim($codigo);
        $acesso = Acesso::with(['cliente', 'dependente', 'unidade'])
            ->daEmpresa($operador->empresa_id)
            ->where('codigo_validacao', $codigo)
            ->first();

        if (! $acesso) {
            return ['status' => 'invalido', 'mensagem' => 'Código não encontrado. Confira o QR ou o código digitado.', 'acesso' => null];
        }

        if (! $acesso->autorizado) {
            return ['status' => 'negado', 'mensagem' => 'Esta entrada não foi autorizada — não deveria ter sido usada.', 'acesso' => $acesso];
        }

        if ($acesso->jaValidado()) {
            $quandoQuem = $acesso->validado_em->format('d/m/Y H:i').($acesso->validadoPor ? " por {$acesso->validadoPor->name}" : '');

            return ['status' => 'ja_usado', 'mensagem' => "Voucher já utilizado em {$quandoQuem}.", 'acesso' => $acesso];
        }

        if ($acesso->estaExpirado()) {
            return ['status' => 'expirado', 'mensagem' => 'Este voucher expirou em '.$acesso->validade_ate->format('d/m/Y').'.', 'acesso' => $acesso];
        }

        $acesso->update(['validado_em' => now(), 'validado_por_id' => $operador->id]);

        return ['status' => 'valido', 'mensagem' => 'Entrada validada com sucesso.', 'acesso' => $acesso->fresh(['cliente', 'dependente', 'unidade'])];
    }

    /**
     * @return array{0: bool, 1: ?string} [$autorizado, $motivo]
     */
    protected function avaliar(?Carteirinha $carteirinha): array
    {
        if (! $carteirinha) {
            return [false, 'Carteirinha não encontrada'];
        }

        if ($carteirinha->status === 'bloqueada') {
            return [false, 'Carteirinha bloqueada: '.($carteirinha->motivo_bloqueio ?? 'sem motivo informado')];
        }

        if ($carteirinha->status !== 'ativa') {
            return [false, "Carteirinha com status \"{$carteirinha->status}\""];
        }

        if ($carteirinha->expira_em && $carteirinha->expira_em->isPast()) {
            return [false, 'Carteirinha expirada'];
        }

        $contrato = $this->contratoAtivoDoTitular($carteirinha);

        if (! $contrato) {
            return [false, 'Sem contrato ativo vinculado'];
        }

        if ($motivoInadimplencia = $this->motivoInadimplencia($contrato)) {
            return [false, $motivoInadimplencia];
        }

        return [true, null];
    }

    protected function contratoAtivoDoTitular(Carteirinha $carteirinha): ?Contrato
    {
        if ($carteirinha->cliente_id) {
            return $carteirinha->cliente?->contratoAtivo;
        }

        return $carteirinha->dependente?->contratos->firstWhere('status', 'ativo');
    }

    protected function motivoInadimplencia(Contrato $contrato): ?string
    {
        $diasLimite = Config::get('parque.dias_atraso_bloqueia_acesso', 5);

        $mensalidadeAtrasada = $contrato->mensalidades
            ->where('status', 'atrasado')
            ->first(fn ($m) => $m->diasEmAtraso() >= $diasLimite);

        if ($mensalidadeAtrasada) {
            return "Inadimplente: mensalidade vencida há {$mensalidadeAtrasada->diasEmAtraso()} dia(s)";
        }

        return null;
    }
}
