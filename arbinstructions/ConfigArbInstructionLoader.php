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
        $arb->currencyPair = $cfgArb['CurrencyPair'];
        $arb->buyExchange = $cfgArb['BuyExchange'];
        $arb->sellExchange = $cfgArb['SellExchange'];

        $arb->buySideRole = $cfgArb['BuySideRole'];
        $arb->sellSideRole = $cfgArb['SellSideRole'];

        foreach($cfgArb['Factors'] as $f)
        {
            $factor = new ArbExecutionFactor();
            $factor->targetSpreadPct = $f['TargetSpreadPct'];
            $factor->maxUsdOrderSize = $f['MaxUsdOrderSize'];
            $factor->orderSizeScaling = $f['OrderSizeScaling'];

            $arb->arbExecutionFactorList[] = $factor;
        }

        $this->arbInst = $arb;
    }

    public function load()
    {
        return $this->arbInst;
    }
}