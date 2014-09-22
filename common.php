<?php

class Exchange{
    const Btce = "Btce";
    const Bitstamp = "Bitstamp";
    const JPMChase = 'JPMChase';
    const Cryptsy = 'Cryptsy';
    const Bitfinex = 'Bitfinex';
}

class Currency{
    const USD = "USD";
    const BTC = "BTC";
    const FTC = 'FTC';
    const LTC = 'LTC';
    const DRK = 'DRK';
}

class CurrencyPair{
    const BTCUSD = "BTCUSD";
    const FTCBTC = 'FTCBTC';
    const LTCBTC = 'LTCBTC';
    const LTCUSD = 'LTCUSD';
    const DRKUSD = 'DRKUSD';

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

class Trade {
    public $exchange;
    public $currency;
    public $orderType;
    public $price;
    public $quantity;
    public $timestamp;
}

class Ticker{
    public $currencyPair;
    public $bid;
    public $ask;
    public $last;
    public $volume;
}

class OrderBook{
    public $bids;
    public $asks;

    public function __construct($rawBook = null)
    {
        if($rawBook == null)
            return;

        $this->asks = array();
        $this->bids = array();

        $bookSides = array(
            array($rawBook['bids'], & $this->bids),
            array($rawBook['asks'], & $this->asks));

        foreach ($bookSides as $bookSideItem) {
            foreach ($bookSideItem[0] as $item) {
                $b = new DepthItem();

                if(isset($item['price']))
                    $b->price = $item['price'];
                else
                    $b->price = $item[0];

                if(isset($item['amount']))
                    $b->quantity = $item['amount'];
                else
                    $b->quantity = $item[1];

                if(isset($item['timestamp']))
                    $b->timestamp = $item['timestamp'];

                $bookSideItem[1][] = $b;
            }
        }
    }
}

class DepthItem{
    public $price;
    public $quantity;
    public $timestamp;
}