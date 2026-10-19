<?php

namespace App\Mail;

use App\Models\Empresa;
use App\Models\FechamentoFiscal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Pacote do fechamento mensal de NFC-e para o contador. Anexa o ZIP quando
 * cabe no limite comum dos provedores; sempre manda também o link
 * temporário de download.
 */
class FechamentoFiscalMail extends Mailable
{
    use Queueable, SerializesModels;

    public const LIMITE_ANEXO_BYTES = 20 * 1024 * 1024;

    public function __construct(
        public FechamentoFiscal $fechamento,
        public Empresa $empresa,
        public string $linkDownload,
        public ?string $mensagem = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "NFC-e {$this->fechamento->rotuloCompetencia()} — {$this->empresa->nome}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.fechamento-fiscal', with: [
            'anexado' => $this->cabeNoAnexo(),
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->cabeNoAnexo()) {
            return [];
        }

        return [Attachment::fromStorageDisk('local', $this->fechamento->arquivo_path)->as($this->fechamento->arquivo_nome)->withMime('application/zip')];
    }

    protected function cabeNoAnexo(): bool
    {
        return Storage::disk('local')->exists($this->fechamento->arquivo_path)
            && Storage::disk('local')->size($this->fechamento->arquivo_path) <= self::LIMITE_ANEXO_BYTES;
    }
}
