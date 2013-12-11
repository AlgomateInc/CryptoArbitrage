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

    public static function Base($strPair){
        return substr($strPair, 0, 3);
    }

    public static function Quote($strPair){
        return substr($strPair, 3, 3);
    }
}

class OrderType{
    const BUY = 'BUY';
    const SELL = 'SELL';
}

class ArbitrageOrder{
    public $currencyPair;
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
    public $currencyPair;
    public $bid;
    public $ask;
    public $last;
}
