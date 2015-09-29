<?php

class Exchange{
    const Btce = "Btce";
    const Bitstamp = "Bitstamp";
    const JPMChase = 'JPMChase';
    const Cryptsy = 'Cryptsy';
    const Bitfinex = 'Bitfinex';
    const BitVC = 'BitVC';
    const Poloniex = 'Poloniex';
}

class Currency{
    const USD = "USD";
    const BTC = "BTC";
    const FTC = 'FTC';
    const LTC = 'LTC';
    const DRK = 'DRK';
    const NXT = 'NXT';
    const CNY = 'CNY';
    const XMR = 'XMR';
    const XCP = 'XCP';
    const ETH = 'ETH';

    public static function FloorValue($value, $currency)
    {
        $precision = array(
            static::USD => 2,
            static::BTC => 8,
            static::FTC => 8,
            static::LTC => 8,
            static::DRK => 8,
            static::NXT => 8,
            static::CNY => 2,
            static::XMR => 8,
            static::XCP => 8,
            static::ETH => 8
        );

        $p = $precision[$currency];

        $mul = pow(10, $p);
        return floor($value * $mul) / $mul;
    }
}

class CurrencyPair{
    const BTCUSD = "BTCUSD";
    const FTCBTC = 'FTCBTC';
    const LTCBTC = 'LTCBTC';
    const LTCUSD = 'LTCUSD';
    const DRKUSD = 'DRKUSD';
    const NXTBTC = 'NXTBTC';
    const BTCCNY = 'BTCCNY';
    const DRKBTC = 'DRKBTC';
    const XMRBTC = 'XMRBTC';
    const XCPBTC = 'XCPBTC';
    const MAIDBTC = 'MAID/BTC';
    const ETHBTC = 'ETHBTC';

    public static function Base($strPair){
        if(strlen($strPair) == 6)
            return substr($strPair, 0, 3);

        $parts = explode('/', $strPair);
        if(count($parts) == 2 && strlen($parts[0]) >= 3 && strlen($parts[1]) >= 3)
            return $parts[0];

        throw new Exception('Unsupported currency pair string');
    }

    public static function Quote($strPair){
        if(strlen($strPair) == 6)
            return substr($strPair, 3, 3);

        $parts = explode('/', $strPair);
        if(count($parts) == 2 && strlen($parts[0]) >= 3 && strlen($parts[1]) >= 3)
            return $parts[1];

        throw new Exception('Unsupported currency pair string');
    }
}

class OrderType{
    const BUY = 'BUY';
    const SELL = 'SELL';
}

class Order{
    public $currencyPair;
    public $exchange;
    public $orderType = OrderType::BUY;
    public $limit = 0;
    public $quantity = 0;
}

class ActiveOrder{
    public $order;
    public $marketResponse;
    public $strategyId;
    public $strategyOrderId;
    public $orderId;
    public $executions = array();

    public $marketObj;
    public $strategyObj;

    function __sleep()
    {
        return array('order','marketResponse','strategyId', 'strategyOrderId', 'orderId', 'executions');
    }
}

class TradingRole{
    const Maker = 'Maker';
    const Taker = 'Taker';
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
    public $tradeId;
    public $orderId;
    public $exchange;
    public $currencyPair;
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
    public $bids = array();
    public $asks = array();

    public function __construct($rawBook = null)
    {
        if($rawBook == null)
            return;

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

    public function volumeToPrice($px){
        $volume = 0;

        foreach($this->bids as $item){
            if(!($item instanceof DepthItem))
                break;
            if($px > $item->price)
                break;
            $volume += $item->quantity;
        }

        foreach($this->asks as $item){
            if(!($item instanceof DepthItem))
                break;
            if($px < $item->price)
                break;
            $volume += $item->quantity;
        }

        return $volume;
    }
}

class DepthItem{
    public $price;
    public $quantity;
    public $timestamp;

    public $stats;
}