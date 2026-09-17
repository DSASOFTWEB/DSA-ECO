<?php

namespace NFePHP\EFD\Elements\ICMSIPI;

use NFePHP\EFD\Common\Element;
use stdClass;

class D760 extends Element
{
    const REG = 'D760';
    const LEVEL = 3;
    const PARENT = '';

    protected $parameters = [
        'CST_ICMS' => [
            'type'     => 'string',
            'regex'    => '^[0-9]{3}$',
            'required' => true,
            'info'     => 'Código da Situação Tributária (CST)',
            'format'   => ''
        ],
        'CFOP' => [
            'type'     => 'string',
            'regex'    => '^[5-7][0-9]{3}$',
            'required' => true,
            'info'     => 'Código Fiscal de Operação e Prestação',
            'format'   => ''
        ],
        'ALIQ_ICMS' => [
            'type'     => 'numeric',
            'regex'    => '^\d{1,2}(\.\d{1,2})?$',
            'required' => true,
            'info'     => 'Alíquota do ICMS',
            'format'   => '6v2'
        ],
        'VL_OPR' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => true,
            'info'     => 'Valor total dos itens relacionados aos serviços próprios com destaque de ICMS',
            'format'   => '15v2'
        ],
        'VL_BC_ICMS' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => true,
            'info'     => 'Valor da base de cálculo do ICMS',
            'format'   => '15v2'
        ],
        'VL_ICMS' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => true,
            'info'     => 'Valor do ICMS',
            'format'   => '15v2'
        ],
        'VL_RED_BC' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => false,
            'info'     => 'Valor não tributado devido à redução da base de cálculo do ICMS',
            'format'   => '15v2'
        ],
        'COD_OBS' => [
            'type'     => 'string',
            'regex'    => '^.{0,6}$',
            'required' => false,
            'info'     => 'Código da observação do lançamento fiscal (campo 02 do Registro 0460)',
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
