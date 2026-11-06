<?php

namespace Tests\Unit;

use App\Exceptions\IntegrationException;
use App\Services\Integrations\EvolutionApiService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * Envio de arquivo pela Evolution: formato v2 e, se recusado, v1; erro
 * com o motivo que a Evolution respondeu.
 */
class EvolutionEnviarDocumentoTest extends TestCase
{
    public array $enviados = [];

    public function test_evolution_v1_recusa_o_formato_v2_e_aceita_o_v1(): void
    {
        $api = $this->api([
            new Response(400, [], json_encode(['status' => 400, 'response' => ['message' => ['mediaMessage is required']]])),
            new Response(201, [], json_encode(['key' => ['id' => 'ABC']])),
        ]);

        $resposta = $api->enviarDocumento('5583999991234', base64_encode('%PDF'), 'Carteirinha.pdf', 'application/pdf', 'Olá');

        $this->assertSame('ABC', $resposta['key']['id']);
        $this->assertCount(2, $this->enviados);
        $this->assertSame('document', $this->enviados[0]['mediatype'], '1º: formato v2');
        $this->assertSame(['mediatype' => 'document', 'fileName' => 'Carteirinha.pdf', 'caption' => 'Olá', 'media' => base64_encode('%PDF')], $this->enviados[1]['mediaMessage'], '2º: formato v1');
        $this->assertSame('5583999991234', $this->enviados[1]['number']);
    }

    public function test_erro_mostra_o_motivo_da_evolution(): void
    {
        $api = $this->api([
            new Response(400, [], '{"response":{"message":["number is not a whatsapp"]}}'),
            new Response(400, [], '{"response":{"message":["number is not a whatsapp"]}}'),
        ]);

        try {
            $api->enviarDocumento('5583999991234', 'eA==', 'a.pdf', 'application/pdf');
            $this->fail('Deveria falhar');
        } catch (IntegrationException $e) {
            $this->assertSame('WhatsApp: Evolution respondeu HTTP 400. Detalhe: number is not a whatsapp', $e->getMessage());
        }
    }

    public function test_instancia_inexistente_nao_tenta_outro_formato(): void
    {
        $api = $this->api([new Response(404, [], '{"message":"instance not found"}')]);

        try {
            $api->enviarDocumento('5583999991234', 'eA==', 'a.pdf', 'application/pdf');
            $this->fail('Deveria falhar');
        } catch (IntegrationException $e) {
            $this->assertStringStartsWith('WhatsApp: instância não encontrada na Evolution', $e->getMessage());
            $this->assertCount(1, $this->enviados);
        }
    }

    public function test_redirecionamento_http_para_https_mantem_o_post(): void
    {
        $api = $this->api([
            new Response(301, ['Location' => 'https://evo.teste/message/sendMedia/pousada']),
            new Response(201, [], '{"key":{"id":"OK"}}'),
        ], 'http://evo.teste/');

        $this->assertSame('OK', $api->enviarDocumento('5583999991234', 'eA==', 'a.pdf', 'application/pdf')['key']['id']);
        $this->assertSame(['POST', 'POST'], $this->metodos, 'não vira GET no redirecionamento');
        $this->assertSame('5583999991234', $this->enviados[1]['number'], 'corpo reenviado');
        $this->assertSame('http://evo.teste/message/sendMedia/pousada', $this->urls[0], 'barra final da URL ignorada');
    }

    public function test_cannot_get_explica_o_problema_do_https(): void
    {
        $api = $this->api([new Response(404, [], '{"status":404,"error":"Not Found","response":{"message":["Cannot GET /message/sendText/pousada"]}}')]);

        try {
            $api->enviarDocumento('5583999991234', 'eA==', 'a.pdf', 'application/pdf');
            $this->fail('Deveria falhar');
        } catch (IntegrationException $e) {
            $this->assertStringContainsString('Use a URL com https://', $e->getMessage());
        }
    }

    public array $metodos = [];

    public array $urls = [];

    /** Evolution com respostas simuladas, guardando o corpo de cada envio. */
    protected function api(array $respostas, string $url = 'https://evo.teste'): EvolutionApiService
    {
        $pilha = HandlerStack::create(new MockHandler($respostas));
        $pilha->push(Middleware::mapRequest(function ($req) {
            $this->enviados[] = json_decode((string) $req->getBody(), true);
            $this->metodos[] = $req->getMethod();
            $this->urls[] = (string) $req->getUri();

            return $req;
        }));

        return new class($pilha, $url) extends EvolutionApiService
        {
            public function __construct(private HandlerStack $pilha, string $url)
            {
                parent::__construct($url, 'chave', 'pousada', 5, 1, 1);
            }

            // Mesmas opções do client real, só trocando o transporte pelo simulado.
            protected function client(int $timeoutSegundos, array $opcoesBase = []): Client
            {
                return new Client(['handler' => $this->pilha] + parent::client($timeoutSegundos, $opcoesBase)->getConfig());
            }
        };
    }
}
