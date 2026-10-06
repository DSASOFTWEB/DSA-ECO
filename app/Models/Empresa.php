<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class Empresa extends Model
{
    use HasFactory;

    public const REGIME_SIMPLES = 'simples';

    public const REGIME_SIMPLES_EXCESSO = 'simples_excesso';

    public const REGIME_NORMAL = 'normal';

    public const REGIME_MEI = 'mei';

    public const AMBIENTE_PRODUCAO = 1;

    public const AMBIENTE_HOMOLOGACAO = 2;

    public const NFSE_AUTH_CERTIFICADO = 'certificado';

    public const NFSE_AUTH_USUARIO_SENHA = 'usuario_senha';

    public const GATEWAY_PIX_NENHUM = 'nenhum';

    public const GATEWAY_PIX_ITAU = 'itau';

    public const GATEWAY_PIX_MERCADOPAGO = 'mercadopago';

    protected $fillable = [
        'nome', 'razao_social', 'cnpj',         'ie', 'im', 'cnae', 'regime_tributario', 'aut_xml', 'codigo_municipio_ibge', 'codigo_servico_hospedagem_lc116', 'codigo_tributacao_municipal_hospedagem', 'aliquota_iss_hospedagem',
        'logo_path', 'email', 'telefone',
        'plano_saas', 'status', 'trial_termina_em', 'configuracoes',
        'ambiente_nfe', 'csc', 'csc_id',
        'numero_serie_nfe', 'numero_serie_nfce', 'numero_serie_nfse',
        'numero_ultima_nfe_producao', 'numero_ultima_nfe_homologacao',
        'numero_ultima_nfce_producao', 'numero_ultima_nfce_homologacao',
        'numero_ultima_nfse',
        'nfse_provider', 'nfse_auth_mode', 'nfse_nacional_habilitado', 'token_nfse', 'token_ibpt',
        'nfse_ws_user', 'nfse_ws_senha', 'nfse_ws_chave_acesso',
        'bluesoft_token',
        'certificado_arquivo', 'certificado_senha', 'certificado_validade',
        'observacao_padrao_nfe', 'observacao_padrao_nfce',
    ];

    protected $hidden = [
        'certificado_arquivo',
        'certificado_senha',
        'token_nfse',
        'nfse_ws_senha',
        'bluesoft_token',
        'csc',
    ];

    protected $casts = [
        'trial_termina_em' => 'date',
        'configuracoes' => 'array',
        'ambiente_nfe' => 'integer',
        'nfse_nacional_habilitado' => 'boolean',
        'certificado_senha' => 'encrypted',
        'nfse_ws_senha' => 'encrypted',
        'certificado_validade' => 'datetime',
        'numero_serie_nfe' => 'integer',
        'numero_serie_nfce' => 'integer',
        'numero_serie_nfse' => 'integer',
        'numero_ultima_nfe_producao' => 'integer',
        'numero_ultima_nfe_homologacao' => 'integer',
        'numero_ultima_nfce_producao' => 'integer',
        'numero_ultima_nfce_homologacao' => 'integer',
        'numero_ultima_nfse' => 'integer',
        'aliquota_iss_hospedagem' => 'decimal:4',
    ];

    public static function regimesTributarios(): array
    {
        return [
            self::REGIME_SIMPLES => 'Simples Nacional',
            self::REGIME_SIMPLES_EXCESSO => 'Simples Nacional — excesso de sublimite',
            self::REGIME_NORMAL => 'Regime Normal',
            self::REGIME_MEI => 'MEI',
        ];
    }

    /**
     * Formas de autenticação no webservice NFS-e municipal.
     *
     * @return array<string, string>
     */
    public static function nfseAuthModes(): array
    {
        return [
            self::NFSE_AUTH_CERTIFICADO => 'Certificado digital A1',
            self::NFSE_AUTH_USUARIO_SENHA => 'Usuário e senha do portal',
        ];
    }

    public function usaAuthNfseCertificado(): bool
    {
        return ($this->nfse_auth_mode ?: self::NFSE_AUTH_CERTIFICADO) === self::NFSE_AUTH_CERTIFICADO;
    }

    public function usaAuthNfseUsuarioSenha(): bool
    {
        return ($this->nfse_auth_mode ?: self::NFSE_AUTH_CERTIFICADO) === self::NFSE_AUTH_USUARIO_SENHA;
    }

    /**
     * CRT da NF-e/NFC-e (1=SN, 2=SN excesso, 3=Normal, 4=MEI).
     */
    public function crt(): int
    {
        return match ($this->regime_tributario) {
            self::REGIME_SIMPLES => 1,
            self::REGIME_SIMPLES_EXCESSO => 2,
            self::REGIME_MEI => 4,
            default => 3,
        };
    }

    public function temCertificadoDigital(): bool
    {
        $conteudo = $this->attributes['certificado_arquivo'] ?? null;
        $temNoBanco = is_resource($conteudo)
            ? (int) ((fstat($conteudo)['size'] ?? 0)) > 0
            : is_string($conteudo) && strlen($conteudo) > 0;

        return $temNoBanco
            || Storage::disk('local')->exists($this->certificadoCaminho());
    }

    public function certificadoCaminho(): string
    {
        return "certificados/empresa-{$this->getKey()}.pfx";
    }

    /**
     * Token Cosmos da empresa; se vazio, usa COSMOS_TOKEN do .env (plataforma).
     */
    public function tokenCosmos(): string
    {
        $token = trim((string) ($this->attributes['bluesoft_token'] ?? ''));

        return $token !== '' ? $token : (string) config('parque.cosmos_token', '');
    }

    /**
     * Parâmetros do emitente prontos para NFePHP / NFS-e (com defaults seguros).
     *
     * @return array<string, mixed>
     */
    public function configuracaoFiscal(): array
    {
        return [
            'cnpj' => preg_replace('/\D+/', '', (string) $this->cnpj) ?: '',
            'razao_social' => (string) ($this->razao_social ?: $this->nome),
            'nome_fantasia' => (string) $this->nome,
            'ie' => (string) ($this->ie ?? ''),
            'im' => (string) ($this->im ?? ''),
            'cnae' => (string) ($this->cnae ?? ''),
            'regime_tributario' => (string) ($this->regime_tributario ?: self::REGIME_SIMPLES),
            'crt' => $this->crt(),
            'aut_xml' => (string) ($this->aut_xml ?? ''),
            'ambiente' => (int) ($this->ambiente_nfe ?: self::AMBIENTE_HOMOLOGACAO),
            'csc' => (string) ($this->csc ?? ''),
            'csc_id' => (string) ($this->csc_id ?? ''),
            'numero_serie_nfe' => (int) $this->numero_serie_nfe,
            'numero_serie_nfce' => (int) $this->numero_serie_nfce,
            'numero_serie_nfse' => (int) $this->numero_serie_nfse,
            'numero_ultima_nfe' => ((int) $this->ambiente_nfe) === self::AMBIENTE_HOMOLOGACAO
                ? (int) $this->numero_ultima_nfe_homologacao
                : (int) $this->numero_ultima_nfe_producao,
            'numero_ultima_nfce' => ((int) $this->ambiente_nfe) === self::AMBIENTE_HOMOLOGACAO
                ? (int) $this->numero_ultima_nfce_homologacao
                : (int) $this->numero_ultima_nfce_producao,
            'numero_ultima_nfse' => (int) $this->numero_ultima_nfse,
            'nfse_provider' => (string) ($this->nfse_provider ?: 'nacional_gov'),
            'nfse_nacional_habilitado' => (bool) $this->nfse_nacional_habilitado,
            'token_nfse' => (string) ($this->token_nfse ?: config('parque.token_nfse', '')),
            'token_ibpt' => (string) ($this->token_ibpt ?: config('parque.token_ibpt', '')),
            'bluesoft_token' => $this->tokenCosmos(),
            'tem_certificado' => $this->temCertificadoDigital(),
            'certificado_validade' => $this->certificado_validade,
            'observacao_padrao_nfe' => (string) ($this->observacao_padrao_nfe ?? ''),
            'observacao_padrao_nfce' => (string) ($this->observacao_padrao_nfce ?? ''),
        ];
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Logo já embutida como data URI (base64) — usada nos PDFs (contrato,
     * caixa, relatórios, voucher), já que o dompdf não tem acesso garantido
     * ao disco público/rede do container que serve as páginas web.
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($this->logo_path) ?: 'image/png';
        $conteudo = base64_encode(Storage::disk('public')->get($this->logo_path));

        return "data:{$mime};base64,{$conteudo}";
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    /**
     * Credenciais PRÓPRIAS desta empresa pra Mercado Pago (Pix). Chave
     * vazia = não configurou a própria — quem chama (MercadoPagoService::
     * paraEmpresa) cai de volta pra credencial global da plataforma (.env).
     * Guardado em configuracoes->mercadopago, nunca em coluna própria, pra
     * não precisar de migration toda vez que um novo campo de integração
     * aparecer.
     */
    public function credenciaisMercadoPago(): array
    {
        return [
            'access_token' => (string) Arr::get($this->configuracoes, 'mercadopago.access_token', ''),
            'public_key' => (string) Arr::get($this->configuracoes, 'mercadopago.public_key', ''),
            'webhook_secret' => (string) Arr::get($this->configuracoes, 'mercadopago.webhook_secret', ''),
        ];
    }

    /**
     * Credenciais PRÓPRIAS desta empresa pra Evolution API (WhatsApp) — ver
     * comentário de credenciaisMercadoPago(), mesma lógica de fallback.
     */
    public function credenciaisEvolution(): array
    {
        return [
            'base_url' => (string) Arr::get($this->configuracoes, 'evolution.base_url', ''),
            'api_key' => (string) Arr::get($this->configuracoes, 'evolution.api_key', ''),
            'instance' => (string) Arr::get($this->configuracoes, 'evolution.instance', ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function provedoresGatewayPix(): array
    {
        return [
            self::GATEWAY_PIX_NENHUM => 'Nenhum (Pix conferido manualmente)',
            self::GATEWAY_PIX_MERCADOPAGO => 'Mercado Pago — Pix (QR Code dinâmico)',
            self::GATEWAY_PIX_ITAU => 'Itaú — Pix Recebimentos (QR Code dinâmico)',
        ];
    }

    /**
     * Gateway Pix usado na tela de recebimento do PDV. Guardado em
     * configuracoes->gateway_pix; o client_secret do Itaú fica cifrado com a
     * APP_KEY e o certificado/chave mTLS ficam no disco local (fora do banco).
     * As credenciais do Mercado Pago continuam em configuracoes->mercadopago
     * (ver credenciaisMercadoPago), pois também servem ao checkout online e
     * às mensalidades.
     *
     * @return array{provedor: string, expiracao_minutos: int, itau: array{client_id: string, client_secret: string, chave_pix: string, expiracao_minutos: int, tem_certificado: bool}}
     */
    public function configuracaoGatewayPix(): array
    {
        $provedor = (string) Arr::get($this->configuracoes, 'gateway_pix.provedor', self::GATEWAY_PIX_NENHUM);
        if (! array_key_exists($provedor, self::provedoresGatewayPix())) {
            $provedor = self::GATEWAY_PIX_NENHUM;
        }

        $segredoCifrado = (string) Arr::get($this->configuracoes, 'gateway_pix.itau.client_secret', '');
        try {
            $segredo = $segredoCifrado !== '' ? Crypt::decryptString($segredoCifrado) : '';
        } catch (DecryptException) {
            $segredo = '';
        }

        $expiracao = (int) Arr::get(
            $this->configuracoes,
            'gateway_pix.expiracao_minutos',
            Arr::get($this->configuracoes, 'gateway_pix.itau.expiracao_minutos', 10)
        ) ?: 10;

        return [
            'provedor' => $provedor,
            'expiracao_minutos' => $expiracao,
            'itau' => [
                'client_id' => (string) Arr::get($this->configuracoes, 'gateway_pix.itau.client_id', ''),
                'client_secret' => $segredo,
                'chave_pix' => (string) Arr::get($this->configuracoes, 'gateway_pix.itau.chave_pix', ''),
                'expiracao_minutos' => $expiracao,
                'tem_certificado' => $this->temCertificadoItau(),
            ],
        ];
    }

    /**
     * Access token efetivo do Mercado Pago: o da empresa ou, se vazio, o da
     * plataforma (.env) — mesma regra de MercadoPagoService::paraEmpresa.
     */
    public function temMercadoPagoUtilizavel(): bool
    {
        return $this->credenciaisMercadoPago()['access_token'] !== ''
            || (string) config('mercadopago.access_token', '') !== '';
    }

    /**
     * Gateway configurado E com todas as credenciais preenchidas — só então
     * o PDV troca o Pix manual pela tela de QR Code dinâmico.
     */
    public function gatewayPixAtivo(): bool
    {
        $config = $this->configuracaoGatewayPix();

        if ($config['provedor'] === self::GATEWAY_PIX_MERCADOPAGO) {
            return $this->temMercadoPagoUtilizavel();
        }

        if ($config['provedor'] !== self::GATEWAY_PIX_ITAU) {
            return false;
        }

        $itau = $config['itau'];

        return $itau['client_id'] !== ''
            && $itau['client_secret'] !== ''
            && $itau['chave_pix'] !== ''
            && $itau['tem_certificado'];
    }

    public function itauCertificadoCaminho(): string
    {
        return "gateways/itau/empresa-{$this->getKey()}.crt";
    }

    public function itauChavePrivadaCaminho(): string
    {
        return "gateways/itau/empresa-{$this->getKey()}.key";
    }

    public function temCertificadoItau(): bool
    {
        return Storage::disk('local')->exists($this->itauCertificadoCaminho())
            && Storage::disk('local')->exists($this->itauChavePrivadaCaminho());
    }

    /**
     * Preferências de impressão do cupom PDV (DOM 80mm vs ESC/POS).
     * Guardado em configuracoes->impressao.
     *
     * @return array{modo: string, colunas: int, agente_url: string, auto_imprimir: bool}
     */
    public function configuracaoImpressao(): array
    {
        $modo = (string) Arr::get($this->configuracoes, 'impressao.modo', 'dom');
        if (! in_array($modo, ['dom', 'escpos', 'ambos'], true)) {
            $modo = 'dom';
        }

        $colunas = (int) Arr::get(
            $this->configuracoes,
            'impressao.colunas',
            config('parque.escpos_colunas', 48)
        );
        if (! in_array($colunas, [32, 40, 42, 48], true)) {
            $colunas = 48;
        }

        $agente = (string) Arr::get(
            $this->configuracoes,
            'impressao.agente_url',
            config('parque.escpos_agente_url', 'http://127.0.0.1:9110')
        );

        return [
            'modo' => $modo,
            'colunas' => $colunas,
            'agente_url' => $agente,
            'auto_imprimir' => (bool) Arr::get($this->configuracoes, 'impressao.auto_imprimir', false),
        ];
    }
}
