<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome', 'razao_social', 'cnpj', 'logo_path', 'email', 'telefone',
        'plano_saas', 'status', 'trial_termina_em', 'configuracoes',
    ];

    protected $casts = [
        'trial_termina_em' => 'date',
        'configuracoes' => 'array',
    ];

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
