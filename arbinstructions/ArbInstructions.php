<?php

class ArbInstructions
{
    public $currencyPair;
    public $buyExchange;
    public $sellExchange;
    public $arbExecutionFactorList = array();

    public $buySideRole;
    public $sellSideRole;
}

class ArbExecutionFactor
{
    public $targetSpreadPct;
    public $maxUsdOrderSize;
    public $orderSizeScaling;
}