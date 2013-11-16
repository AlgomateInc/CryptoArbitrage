<?php

class ArbInstructions
{
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