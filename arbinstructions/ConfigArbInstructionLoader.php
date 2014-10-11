<?php

require_once('IArbInstructionLoader.php');
require_once('ArbInstructions.php');

class ConfigArbInstructionLoader implements IArbInstructionLoader {

    private $arbInst;

    public function __construct($configArbInstructions){
        $this->arbInst = array();

        foreach($configArbInstructions as $cfgArb)
        {
            $sail = new SingleArbInstructionLoader($cfgArb);
            $this->arbInst[] = $sail->load();
        }
    }

    public function load()
    {
        return $this->arbInst;
    }
}

class SingleArbInstructionLoader implements IArbInstructionLoader {

    private $arbInst;

    public function __construct($cfgArb)
    {
        $arb = new ArbInstructions();
        $arb->currencyPair = $cfgArb['currencyPair'];
        $arb->buyExchange = $cfgArb['from'];
        $arb->sellExchange = $cfgArb['to'];

        foreach($cfgArb['factors'] as $f)
        {
            $factor = new ArbExecutionFactor();
            $factor->targetSpreadPct = $f['spreadPct'];
            $factor->maxUsdOrderSize = $f['maxUsdSize'];
            $factor->orderSizeScaling = $f['sizeFactor'];

            $arb->arbExecutionFactorList[] = $factor;
        }

        $this->arbInst = $arb;
    }

    public function load()
    {
        return $this->arbInst;
    }
}