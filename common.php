<?php

class Exchange{
    const Btce = "Btce";
    const Bitstamp = "Bitstamp";
}

class Currency{
    const USD = "USD";
    const BTC = "BTC";
}

class CurrencyPair{
    const BTCUSD = "BTCUSD";
}

class OrderType{
    const BUY = 'BUY';
    const SELL = 'SELL';
}

class ArbitrageOrder{
    public $buyExchange;
    public $buyLimit = 0;
    public $sellExchange;
    public $sellLimit = INF;
    public $quantity = 0;
    public $executionQuantity = 0;
}
