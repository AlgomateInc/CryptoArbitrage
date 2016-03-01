<?php

class Exchange{
    const Btce = "Btce";
    const Bitstamp = "Bitstamp";
    const JPMChase = 'JPMChase';
    const Cryptsy = 'Cryptsy';
    const Bitfinex = 'Bitfinex';
    const BitVC = 'BitVC';
    const Poloniex = 'Poloniex';
    const Gemini = 'Gemini';
    const Kraken = 'Kraken';
    const Ethereum = 'Ethereum';
    const Bitcoin = 'Bitcoin';
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

    public static function getPrecision($currency)
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

        return $precision[$currency];
    }

    public static function GetMinimumValue($currency)
    {
        $p = self::getPrecision($currency);
        return pow(10, -$p);
    }

    public static function FloorValue($value, $currency)
    {
        $p = self::getPrecision($currency);

        $mul = pow(10, $p);
        return floor($value * $mul) / $mul;
    }

    public static function RoundValue($value, $currency, $roundMode = PHP_ROUND_HALF_UP)
    {
        $p =  self::getPrecision($currency);

        return round($value, $p, $roundMode);
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
    const ETHUSD = 'ETHUSD';

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

class OrderCancel{
    public $orderId;
    public $exchange;
    public $strategyOrderId;

    /**
     * OrderCancel constructor.
     * @param $orderId
     * @param $exchange
     * @param $strategyOrderId
     */
    public function __construct($orderId, $exchange, $strategyOrderId)
    {
        $this->orderId = $orderId;
        $this->exchange = $exchange;
        $this->strategyOrderId = $strategyOrderId;
    }
}

class ActiveOrder{
    public $order;
    public $marketResponse;
    public $strategyId;
    public $strategyOrderId;
    public $orderId;
    public $executions = array();

    public $marketObj;

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

    public function getOrderBookVolume($pricePercentage)
    {
        $bid = OrderBook::getInsideBookPrice($this, OrderType::BUY);
        $ask = OrderBook::getInsideBookPrice($this, OrderType::SELL);

        if($bid == null || $ask == null)
            return null;

        $midpoint = ($bid + $ask)/2.0;

        $bidVolume = $this->volumeToPrice($midpoint * (1 - $pricePercentage / 100.0));
        $askVolume = $this->volumeToPrice($midpoint * (1 + $pricePercentage / 100.0));

        if($bidVolume == 0 || $askVolume == 0)
            return null;

        return array('bid' => $bid, 'bidVolume' => $bidVolume, 'ask' => $ask, 'askVolume' => $askVolume);
    }

    public static function getInsideBookPrice(OrderBook $depth, $bookSide){
        if (count($depth->bids) > 0 && count($depth->asks) > 0) {
            $insideBid = $depth->bids[0];
            $insideAsk = $depth->asks[0];

            if ($insideBid instanceof DepthItem && $insideAsk instanceof DepthItem) {
                switch($bookSide){
                    case OrderType::BUY:
                        return $insideBid->price;
                    case OrderType::SELL:
                        return $insideAsk->price;
                }
            }
        }

        return null;
    }
}

class DepthItem{
    public $price;
    public $quantity;
    public $timestamp;

    public $stats;
}