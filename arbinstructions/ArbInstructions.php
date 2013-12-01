<?php

class ArbInstructions
{
    public $currencyPair;
    public $buyExchange;
    public $sellExchange;
    public $arbExecutionFactorList = array();
}

class ArbExecutionFactor
{
    public $targetSpreadPct;
    public $maxUsdOrderSize;
    public $orderSizeScaling;
}