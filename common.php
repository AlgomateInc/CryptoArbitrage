<?php

class Exchange{
    const Btce = "Btce";
    const Bitstamp = "Bitstamp";
    const JPMChase = 'JPMChase';
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

class TransactionType{
    const Debit = 'DEBIT';
    const Credit = 'CREDIT';
}

class Transaction{
    public $exchange;
    public $id;
    public $type;
    public $currency;
    public $amount;
    public $timestamp;
}

class Ticker{
    public $bid;
    public $ask;
    public $last;
}
