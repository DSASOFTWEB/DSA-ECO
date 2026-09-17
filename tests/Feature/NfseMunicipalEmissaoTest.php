<?php

namespace Tests\Feature;

use App\Exceptions\NegocioException;
use App\Models\Cliente;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Quarto;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Fiscal\HospedagemFiscalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NfseMunicipalEmissaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_municipio_padrao_nacional_sem_flag_orienta_habilitar_nacional(): void
    {
        [$empresa, $unidade, $user, $hospedagem] = $this->cenario(
            ibge: '2704500', // Arapiraca AL no catálogo ACBr costuma ser PadraoNacional ou vazio — usamos lookup real
            nacional: false,
        );

        // Força provedor PadraoNacional no fluxo via IBGE conhecido do catálogo.
        $empresa->update(['codigo_municipio_ibge' => '2704607']); // Pilar/AL se PadraoNacional; fallback abaixo

        $catalog = json_decode(file_get_contents(resource_path('fiscal/nfse_municipios.json')), true);
        $ibgePadrao = collect($catalog['municipios'])
            ->filter(fn ($m) => strcasecmp((string) ($m['provedor'] ?? ''), 'PadraoNacional') === 0)
            ->keys()
            ->first();
        $this->assertNotNull($ibgePadrao);
        $empresa->update(['codigo_municipio_ibge' => $ibgePadrao]);

        $this->expectException(NegocioException::class);
        $this->expectExceptionMessage('NFS-e Nacional');

        app(HospedagemFiscalService::class)->emitirNfseServicos($hospedagem->fresh(), $user);
    }

    public function test_provedor_nao_giss_nao_suportado(): void
    {
        [$empresa, $unidade, $user, $hospedagem] = $this->cenario(ibge: '3550308', nacional: false);

        $catalog = json_decode(file_get_contents(resource_path('fiscal/nfse_municipios.json')), true);
        $ibgeOutro = collect($catalog['municipios'])
            ->filter(function ($m) {
                $p = strtolower((string) ($m['provedor'] ?? ''));

                return $p !== '' && $p !== 'giss' && $p !== 'padraonacional';
            })
            ->keys()
            ->first();
        $this->assertNotNull($ibgeOutro);
        $empresa->update(['codigo_municipio_ibge' => $ibgeOutro]);

        $this->expectException(NegocioException::class);
        $this->expectExceptionMessage('ainda não suportado');

        app(HospedagemFiscalService::class)->emitirNfseServicos($hospedagem->fresh(), $user);
    }

    public function test_giss_envio_e_consulta_autoriza(): void
    {
        [$empresa, $unidade, $user, $hospedagem] = $this->cenario(ibge: '2704302', nacional: false);
        $this->anexarCertificado($empresa);

        $envioXml = '<?xml version="1.0"?>'
            .'<EnviarLoteRpsResposta>'
            .'<Protocolo>PROT-GISS-1</Protocolo>'
            .'<DataRecebimento>2026-09-17T12:00:00</DataRecebimento>'
            .'</EnviarLoteRpsResposta>';

        $consultaXml = '<?xml version="1.0"?>'
            .'<ConsultarLoteRpsResposta>'
            .'<ListaNfse><CompNfse><Nfse><InfNfse>'
            .'<Numero>9001</Numero>'
            .'<CodigoVerificacao>ABC123</CodigoVerificacao>'
            .'</InfNfse></Nfse></CompNfse></ListaNfse>'
            .'</ConsultarLoteRpsResposta>';

        Http::fake([
            'ws-homologacao-rtc.giss.com.br/*' => Http::sequence()
                ->push($this->soapEnvelope($envioXml), 200)
                ->push($this->soapEnvelope($consultaXml), 200),
        ]);

        $doc = app(HospedagemFiscalService::class)->emitirNfseServicos($hospedagem->fresh(['quarto', 'cliente']), $user);

        $this->assertSame(DocumentoFiscal::STATUS_AUTORIZADO, $doc->status);
        $this->assertSame(9001, (int) $doc->numero);
        $this->assertSame('ABC123', $doc->chave);
        $this->assertSame('PROT-GISS-1', $doc->protocolo);
        $this->assertNotEmpty($doc->xml_protocolado);
    }

    public function test_giss_lote_em_processamento_persiste_protocolo(): void
    {
        [$empresa, $unidade, $user, $hospedagem] = $this->cenario(ibge: '2704302', nacional: false);
        $this->anexarCertificado($empresa);

        $envioXml = '<?xml version="1.0"?>'
            .'<EnviarLoteRpsResposta><Protocolo>PROT-WAIT</Protocolo></EnviarLoteRpsResposta>';
        $consultaXml = '<?xml version="1.0"?>'
            .'<ConsultarLoteRpsResposta>'
            .'<ListaMensagemRetorno><MensagemRetorno>'
            .'<Codigo>E90</Codigo>'
            .'<Mensagem>A remessa ainda nao foi processada</Mensagem>'
            .'</MensagemRetorno></ListaMensagemRetorno>'
            .'</ConsultarLoteRpsResposta>';

        Http::fake([
            'ws-homologacao-rtc.giss.com.br/*' => Http::sequence()
                ->push($this->soapEnvelope($envioXml), 200)
                ->push($this->soapEnvelope($consultaXml), 200),
        ]);

        $doc = app(HospedagemFiscalService::class)->emitirNfseServicos($hospedagem->fresh(['quarto', 'cliente']), $user);

        $this->assertSame(DocumentoFiscal::STATUS_PROCESSANDO, $doc->status);
        $this->assertSame('PROT-WAIT', $doc->protocolo);
        $this->assertStringContainsString('processamento', mb_strtolower((string) $doc->mensagem_erro));
    }

    public function test_maceio_com_flag_nacional_ainda_usa_giss_nao_sefin(): void
    {
        // E0039: município não parametrizado nos emissores públicos nacionais.
        [$empresa, $unidade, $user, $hospedagem] = $this->cenario(ibge: '2704302', nacional: true);
        $this->anexarCertificado($empresa);

        $envioXml = '<?xml version="1.0"?>'
            .'<EnviarLoteRpsResposta><Protocolo>PROT-AUTO</Protocolo></EnviarLoteRpsResposta>';
        $consultaXml = '<?xml version="1.0"?>'
            .'<ConsultarLoteRpsResposta><ListaNfse><CompNfse><Nfse><InfNfse>'
            .'<Numero>55</Numero><CodigoVerificacao>XYZ</CodigoVerificacao>'
            .'</InfNfse></Nfse></CompNfse></ListaNfse></ConsultarLoteRpsResposta>';

        Http::fake([
            'ws-homologacao-rtc.giss.com.br/*' => Http::sequence()
                ->push($this->soapEnvelope($envioXml), 200)
                ->push($this->soapEnvelope($consultaXml), 200),
            'sefin.*' => Http::response(['erros' => [['Codigo' => 'E0039', 'Descricao' => 'nao']]], 400),
        ]);

        $doc = app(HospedagemFiscalService::class)->emitirNfseServicos($hospedagem->fresh(['quarto', 'cliente']), $user);

        $this->assertSame(DocumentoFiscal::STATUS_AUTORIZADO, $doc->status);
        $this->assertSame(55, (int) $doc->numero);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'giss.com.br'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sefin'));
    }

    /**
     * @return array{0:Empresa,1:Unidade,2:User,3:Hospedagem}
     */
    protected function cenario(string $ibge, bool $nacional): array
    {
        $empresa = Empresa::factory()->create([
            'codigo_municipio_ibge' => $ibge,
            'codigo_servico_hospedagem_lc116' => '09.01.05',
            'codigo_tributacao_municipal_hospedagem' => '010',
            'aliquota_iss_hospedagem' => 2,
            'nfse_nacional_habilitado' => $nacional,
            'nfse_provider' => $nacional ? 'nacional_gov' : 'municipio',
            'ambiente_nfe' => Empresa::AMBIENTE_HOMOLOGACAO,
            'im' => '123456',
            'cnpj' => '22195708400012',
            'numero_serie_nfse' => 1,
            'numero_ultima_nfse' => 0,
        ]);
        $unidade = Unidade::factory()->create([
            'empresa_id' => $empresa->id,
            'cnpj' => $empresa->cnpj,
            'uf' => 'AL',
            'cidade' => 'Maceio',
        ]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'cpf' => '52998224725',
        ]);
        $quarto = Quarto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'numero' => '10',
            'capacidade_maxima' => 2,
            'valor_diaria' => 150,
            'status' => 'ativo',
        ]);
        $hospedagem = Hospedagem::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'quarto_id' => $quarto->id,
            'cliente_id' => $cliente->id,
            'quantidade_hospedes' => 1,
            'quantidade_adultos' => 1,
            'quantidade_criancas' => 0,
            'quantidade_isentos' => 0,
            'valor_diaria' => 150,
            'data_checkin_prevista' => now()->toDateString(),
            'data_checkout_prevista' => now()->addDay()->toDateString(),
            'data_checkin_real' => now(),
            'status' => 'hospedado',
            'registrado_por_id' => $user->id,
        ]);

        return [$empresa, $unidade, $user, $hospedagem];
    }

    protected function anexarCertificado(Empresa $empresa): void
    {
        $pfx = $this->gerarPfx('senha-teste');
        $empresa->update([
            'certificado_arquivo' => $pfx,
            'certificado_senha' => 'senha-teste',
        ]);
        Storage::disk('local')->put($empresa->certificadoCaminho(), $pfx);
    }

    protected function gerarPfx(string $senha): string
    {
        $chave = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new(['commonName' => 'Teste NFSe'], $chave, ['digest_alg' => 'sha256']);
        $certificado = openssl_csr_sign($csr, null, $chave, 365, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($certificado);
        $pfx = '';
        $this->assertTrue(openssl_pkcs12_export($certificado, $pfx, $chave, $senha));

        return $pfx;
    }

    protected function soapEnvelope(string $innerXml): string
    {
        $escaped = htmlspecialchars($innerXml, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Body><RecepcionarLoteRpsResponse>'
            .'<outputXML>'.$escaped.'</outputXML>'
            .'</RecepcionarLoteRpsResponse></soap:Body></soap:Envelope>';
    }
}
