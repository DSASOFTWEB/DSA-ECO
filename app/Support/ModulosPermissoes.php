<?php

namespace App\Support;

/**
 * Catálogo único de módulos × permissões do painel.
 * Fonte da verdade para seeder, Policies (@can) e formulário de usuários.
 */
class ModulosPermissoes
{
    /**
     * @return array<string, array{label:string, permissoes:array<string, string>}>
     */
    public static function modulos(): array
    {
        return [
            'clientes' => [
                'label' => 'Clientes',
                'permissoes' => [
                    'clientes.visualizar' => 'Visualizar',
                    'clientes.criar' => 'Criar',
                    'clientes.editar' => 'Editar',
                    'clientes.excluir' => 'Excluir',
                ],
            ],
            'planos' => [
                'label' => 'Planos',
                'permissoes' => [
                    'planos.visualizar' => 'Visualizar',
                    'planos.criar' => 'Criar',
                    'planos.editar' => 'Editar',
                    'planos.excluir' => 'Excluir',
                ],
            ],
            'contratos' => [
                'label' => 'Contratos',
                'permissoes' => [
                    'contratos.visualizar' => 'Visualizar',
                    'contratos.criar' => 'Criar',
                    'contratos.editar' => 'Editar / prorrogar',
                    'contratos.cancelar' => 'Cancelar / reativar',
                ],
            ],
            'financeiro' => [
                'label' => 'Mensalidades / Cobrança',
                'permissoes' => [
                    'financeiro.visualizar' => 'Visualizar',
                    'financeiro.baixar_manual' => 'Baixar manualmente',
                    'financeiro.cancelar_mensalidade' => 'Cancelar mensalidade',
                    'financeiro.enviar_cobranca' => 'Enviar cobrança',
                ],
            ],
            'contas_pagar' => [
                'label' => 'Contas a pagar',
                'permissoes' => [
                    'contas_pagar.visualizar' => 'Visualizar',
                    'contas_pagar.gerenciar' => 'Gerenciar',
                ],
            ],
            'contas_receber' => [
                'label' => 'Contas a receber',
                'permissoes' => [
                    'contas_receber.visualizar' => 'Visualizar',
                    'contas_receber.gerenciar' => 'Gerenciar',
                ],
            ],
            'caixa' => [
                'label' => 'Caixa',
                'permissoes' => [
                    'caixa.visualizar' => 'Visualizar',
                    'caixa.abrir' => 'Abrir',
                    'caixa.fechar' => 'Fechar',
                    'caixa.movimentar' => 'Movimentar',
                    'caixa.transferir' => 'Transferir entre caixas',
                    'caixa.estornar' => 'Estornar',
                    'caixa.editar_movimentacao' => 'Editar movimentação',
                ],
            ],
            'estoque' => [
                'label' => 'Estoque / Produtos',
                'permissoes' => [
                    'estoque.visualizar' => 'Visualizar',
                    'estoque.criar' => 'Criar',
                    'estoque.editar' => 'Editar',
                    'estoque.excluir' => 'Excluir',
                    'estoque.ajustar' => 'Ajustar estoque',
                ],
            ],
            'vendas' => [
                'label' => 'Vendas (PDV)',
                'permissoes' => [
                    'vendas.visualizar' => 'Visualizar',
                    'vendas.criar' => 'Vender',
                    'vendas.cancelar' => 'Cancelar venda',
                ],
            ],
            'food' => [
                'label' => 'Restaurante / Food',
                'permissoes' => [
                    'food.visualizar' => 'Visualizar',
                    'food.operar' => 'Operar mesas/comandas',
                    'food.fechar' => 'Fechar conta',
                    'food.configurar' => 'Configurar',
                ],
            ],
            'pousada' => [
                'label' => 'Pousada / Hospedagem',
                'permissoes' => [
                    'pousada.visualizar' => 'Visualizar',
                    'pousada.gerenciar' => 'Gerenciar quartos',
                    'pousada.reservar' => 'Reservar',
                    'pousada.checkin' => 'Check-in',
                    'pousada.checkout' => 'Check-out',
                    'pousada.consumos' => 'Consumos',
                    'pousada.limpeza' => 'Limpeza',
                    'pousada.comodato' => 'Comodato',
                ],
            ],
            'tipos_entrada' => [
                'label' => 'Tipos de entrada',
                'permissoes' => [
                    'tipos_entrada.visualizar' => 'Visualizar',
                    'tipos_entrada.gerenciar' => 'Gerenciar',
                ],
            ],
            'comissoes' => [
                'label' => 'Comissões',
                'permissoes' => [
                    'comissoes.visualizar' => 'Visualizar todas',
                    'comissoes.visualizar_proprias' => 'Ver as próprias',
                    'comissoes.pagar' => 'Marcar como paga',
                ],
            ],
            'carteirinhas' => [
                'label' => 'Carteirinhas',
                'permissoes' => [
                    'carteirinhas.visualizar' => 'Visualizar',
                    'carteirinhas.emitir' => 'Emitir',
                    'carteirinhas.bloquear' => 'Bloquear / desbloquear',
                ],
            ],
            'acessos' => [
                'label' => 'Controle de acesso',
                'permissoes' => [
                    'acessos.validar' => 'Validar entrada / catraca',
                    'acessos.cortesia' => 'Cortesias',
                ],
            ],
            'empresa' => [
                'label' => 'Minha empresa / Fiscal',
                'permissoes' => [
                    'empresa.gerenciar' => 'Gerenciar empresa e fiscal',
                ],
            ],
            'unidades' => [
                'label' => 'Unidades',
                'permissoes' => [
                    'unidades.visualizar' => 'Visualizar',
                    'unidades.criar' => 'Criar',
                    'unidades.editar' => 'Editar',
                ],
            ],
            'terminais' => [
                'label' => 'Terminais',
                'permissoes' => [
                    'terminais.visualizar' => 'Visualizar',
                    'terminais.criar' => 'Criar',
                    'terminais.editar' => 'Editar',
                ],
            ],
            'usuarios' => [
                'label' => 'Usuários',
                'permissoes' => [
                    'usuarios.visualizar' => 'Visualizar',
                    'usuarios.criar' => 'Criar',
                    'usuarios.editar' => 'Editar',
                    'usuarios.excluir' => 'Excluir',
                ],
            ],
            'auditoria' => [
                'label' => 'Auditoria',
                'permissoes' => [
                    'auditoria.visualizar' => 'Visualizar',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function todasPermissoes(): array
    {
        $lista = [];
        foreach (self::modulos() as $modulo) {
            foreach (array_keys($modulo['permissoes']) as $nome) {
                $lista[] = $nome;
            }
        }

        return $lista;
    }

    /**
     * @return array<string, string>
     */
    public static function labelsPapeis(): array
    {
        return [
            'admin' => 'Administrador',
            'gerente' => 'Gerente',
            'recepcao' => 'Recepção',
            'vendedor' => 'Vendedor',
            'financeiro' => 'Financeiro',
        ];
    }

    public static function labelPapel(string $nome): string
    {
        return self::labelsPapeis()[$nome] ?? ucfirst(str_replace('_', ' ', $nome));
    }

    /**
     * Pacotes de permissão por perfil (mesmo conteúdo do seeder).
     *
     * @return array<string, list<string>>
     */
    public static function permissoesPorPapel(): array
    {
        $todas = self::todasPermissoes();

        return [
            'admin' => $todas,
            'gerente' => array_values(array_filter($todas, fn ($p) => $p !== 'usuarios.excluir')),
            'recepcao' => [
                'clientes.visualizar', 'clientes.criar', 'clientes.editar',
                'contratos.visualizar', 'contratos.criar',
                'financeiro.visualizar', 'financeiro.baixar_manual', 'financeiro.enviar_cobranca',
                'caixa.visualizar', 'caixa.abrir', 'caixa.fechar', 'caixa.movimentar',
                'carteirinhas.visualizar', 'carteirinhas.emitir', 'carteirinhas.bloquear',
                'acessos.validar', 'acessos.cortesia',
                'vendas.visualizar', 'vendas.criar',
                'food.visualizar', 'food.operar', 'food.fechar',
                'pousada.visualizar', 'pousada.reservar', 'pousada.checkin', 'pousada.checkout', 'pousada.consumos', 'pousada.limpeza', 'pousada.comodato',
                'estoque.visualizar',
            ],
            'vendedor' => [
                'clientes.visualizar', 'clientes.criar',
                'contratos.visualizar', 'contratos.criar',
                'vendas.visualizar', 'vendas.criar',
                'food.visualizar', 'food.operar',
                'comissoes.visualizar_proprias',
                'caixa.visualizar',
                'estoque.visualizar',
            ],
            'financeiro' => [
                'financeiro.visualizar', 'financeiro.baixar_manual', 'financeiro.cancelar_mensalidade', 'financeiro.enviar_cobranca',
                'contas_pagar.visualizar', 'contas_pagar.gerenciar',
                'contas_receber.visualizar', 'contas_receber.gerenciar',
                'caixa.visualizar', 'caixa.fechar',
                'caixa.transferir', 'caixa.estornar', 'caixa.editar_movimentacao',
                'comissoes.visualizar', 'comissoes.pagar',
                'contratos.visualizar',
                'clientes.visualizar',
                'auditoria.visualizar',
            ],
        ];
    }
}
