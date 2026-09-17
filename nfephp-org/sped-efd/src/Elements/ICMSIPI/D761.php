<?php

namespace NFePHP\EFD\Elements\ICMSIPI;

use NFePHP\EFD\Common\Element;
use stdClass;

class D761 extends Element
{
    const REG = 'D761';
    const LEVEL = 4;
    const PARENT = 'D760';

    protected $parameters = [
        'VL_FCP_OP' => [
            'type'     => 'numeric',
            'regex'    => '^\d+(\.\d*)?|\.\d+$',
            'required' => true,
            'info'     => 'Valor do Fundo de Combate à Pobreza (FCP) vinculado à operação própria',
            'format'   => '15v2'
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