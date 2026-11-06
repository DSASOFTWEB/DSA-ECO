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

    /** Evolution com respostas simuladas, guardando o corpo de cada envio. */
    protected function api(array $respostas): EvolutionApiService
    {
        $pilha = HandlerStack::create(new MockHandler($respostas));
        $pilha->push(Middleware::mapRequest(function ($req) {
            $this->enviados[] = json_decode((string) $req->getBody(), true);

            return $req;
        }));

        return new class($pilha) extends EvolutionApiService
        {
            public function __construct(private HandlerStack $pilha)
            {
                parent::__construct('https://evo.teste', 'chave', 'pousada', 5, 1, 1);
            }

            protected function client(int $timeoutSegundos, array $opcoesBase = []): Client
            {
                return new Client(['handler' => $this->pilha, 'http_errors' => true]);
            }
        };
    }
}
