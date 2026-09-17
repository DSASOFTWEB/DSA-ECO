<?php

namespace NFePHP\EFD\Elements\ICMSIPI;

use NFePHP\EFD\Common\Element;
use stdClass;

class D700 extends Element
{
    const REG = 'D700';
    const LEVEL = 2;
    const PARENT = '';

    protected $parameters = [
        'IND_OPER' => [
            'type'     => 'string',
            'regex'    => '^[0-1]{1}$',
            'required' => true,
            'info'     => 'Indicador do tipo de operação: 0-Entrada; 1-Saída',
            'format'   => ''
        ],
        'IND_EMIT' => [
            'type'     => 'string',
            'regex'    => '^[0-1]{1}$',
            'required' => true,
            'info'     => 'Indicador do emitente: 0-Emissão própria; 1-Terceiros',
            'format'   => ''
        ],
        'COD_PART' => [
            'type'     => 'string',
            'regex'    => '^.{1,60}$',
            'required' => false,
            'info'     => 'Código do participante (campo 02 do Registro 0150)',
            'format'   => ''
        ],
        'COD_MOD' => [
            'type'     => 'string',
            'regex'    => '^(21|22)+$',
            'required' => true,
            'info'     => 'Código do modelo do documento fiscal',
            'format'   => ''
        ],
        'COD_SIT' => [
            'type'     => 'numeric',
            'regex'    => '^(0)([0-9]{1})?$',
            'required' => true,
            'info'     => 'Código da situação do documento fiscal',
            'format'   => ''
        ],
        'SER' => [
            'type'     => 'string',
            'regex'    => '^.{1,4}$',
            'required' => false,
            'info'     => 'Série do documento fiscal',
            'format'   => ''
        ],
        'NUM_DOC' => [
            'type'     => 'numeric',
            'regex'    => '^([0-9]{1,9})?$',
            'required' => true,
            'info'     => 'Número do documento fiscal',
            'format'   => ''
        ],
        'DT_DOC' => [
            'type'     => 'string',
            'regex'    => '^(0[1-9]|[1-2][0-9]|31(?!(?:0[2469]|11))|30(?!02))(0[1-9]|1[0-2])([12]\d{3})$',
            'required' => true,
            'info'     => 'Data da emissão do documento fiscal',
            'format'   => ''
        ],
        'DT_E_S' => [
            'type'     => 'string',
            'regex'    => '^(0[1-9]|[1-2][0-9]|31(?!(?:0[2469]|11))|30(?!02))(0[1-9]|1[0-2])([12]\d{3})$',
            'required' => false,
            'info'     => 'Data da entrada ou da saída',
            'format'   => ''
        ],
        'VL_DOC' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => true,
            'info'     => 'Valor total do documento fiscal',
            'format'   => '15v2'
        ],
        'VL_DESC' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => false,
            'info'     => 'Valor total do desconto',
            'format'   => '15v2'
        ],
        'VL_SERV' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => true,
            'info'     => 'Valor dos serviços tributados pelo ICMS',
            'format'   => '15v2'
        ],
        'VL_SERV_NT' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => false,
            'info'     => 'Valor total dos serviços não-tributados pelo ICMS',
            'format'   => '15v2'
        ],
        'VL_TERC' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => false,
            'info'     => 'Valores cobrados em nome de terceiros',
            'format'   => ''
        ],
        'VL_DA' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => false,
            'info'     => 'Valor de outras despesas indicadas no documento fiscal',
            'format'   => ''
        ],
        'VL_BC_ICMS' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => false,
            'info'     => 'Valor da base de cálculo do ICMS',
            'format'   => '15v2'
        ],
        'VL_ICMS' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => false,
            'info'     => 'Valor do ICMS',
            'format'   => '15v2'
        ],
        'VL_PIS' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => false,
            'info'     => 'Valor do PIS',
            'format'   => '15v2'
        ],
        'VL_COFINS' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => false,
            'info'     => 'Valor da COFINS',
            'format'   => '15v2'
        ],
        'CHV_DOCE' => [
            'type'     => 'numeric',
            'regex'    => '^([0-9]{44})?$',
            'required' => false,
            'info'     => 'Chave do Documento Eletrônico',
            'format'   => ''
        ],
        'FIN_DOCe' => [
            'type'     => 'string',
            'regex'    => '^(0|3|4)$',
            'required' => false,
            'info'     => 'Finalidade da emissão: 0-NFCom Normal; 3-Substituição; 4-Ajuste',
            'format'   => ''
        ],
        'TIP_FAT' => [
            'type'     => 'string',
            'regex'    => '^(0|1|2)$',
            'required' => false,
            'info'     => 'Tipo de faturamento: 0-Normal; 1-Centralizado; 2-Cofaturamento',
            'format'   => ''
        ],
        'COD_MOD_DOC_REF' => [
            'type'     => 'string',
            'regex'    => '^.{2}$',
            'required' => false,
            'info'     => 'Código do modelo do documento fiscal referenciado',
            'format'   => ''
        ],
        'CHV_DOCe_REF' => [
            'type'     => 'numeric',
            'regex'    => '^[0-9]{44}$',
            'required' => false,
            'info'     => 'Chave da nota referenciada',
            'format'   => ''
        ],
        'COD_MUN_DEST' => [
            'type'     => 'numeric',
            'regex'    => '^[0-9]{7}$',
            'required' => false,
            'info'     => 'Código do município do destinatário conforme tabela IBGE',
            'format'   => ''
        ]
    ];

    /**
     * Constructor
     * @param stdClass $std
     * @param stdClass $vigencia
     */
    public function __construct(stdClass $std, stdClass $vigencia = null)
    {
        parent::__construct(self::REG, $vigencia);
        $this->replaceParams(self::REG);
        $this->std = $this->standarize($std);
        $this->postValidation();
    }
}
