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
    const Gdax = 'Gdax';
    const EthereumClassic = 'EthClassic';
    const Yunbi = 'Yunbi';
}

class Currency{
    // Fiat currencies
    const USD = 'USD';
    const EUR = 'EUR';
    const GBP = 'GBP';
    const CNY = 'CNY';
    const RUR = 'RUR';

    const FIAT_CURRENCIES = array(Currency::USD,
        Currency::EUR,
        Currency::GBP,
        Currency::CNY,
        Currency::RUR);

    // Crypto-currencies
    const BTC = 'BTC';
    const FTC = 'FTC';
    const LTC = 'LTC';
    const DRK = 'DRK';
    const NXT = 'NXT';
    const XMR = 'XMR';
    const XCP = 'XCP';
    const XRP = 'XRP';
    const ETH = 'ETH';
    const DAO = 'DAO';
    const ETC = 'ETC';

    public static function isFiat($currency)
    {
        return in_array($currency, Currency::FIAT_CURRENCIES);
    }

    public static function getPrecision($currency)
    {
        if (Currency::isFiat($currency)) {
            return 2;
        } else {
            return 8;
        }
    }

    public static function GetMinimumValue($currency, $currencyPrecision = INF)
    {
        $p = min(self::getPrecision($currency), $currencyPrecision);
        return pow(10, -$p);
    }

    public static function FloorValue($value, $currency, $currencyPrecision = INF)
    {
        $p = min(self::getPrecision($currency), $currencyPrecision);

        $mul = pow(10, $p);
        return bcdiv(floor(bcmul($value, $mul, $p)), $mul, $p); //bc math lib avoids floating point weirdness
    }

    public static function RoundValue($value, $currency, $roundMode = PHP_ROUND_HALF_UP)
    {
        $p =  self::getPrecision($currency);

        return round($value, $p, $roundMode);
    }
}

class CurrencyPair{
    const BTCUSD = 'BTCUSD';
    const BTCEUR = 'BTCEUR';
    const XRPUSD = 'XRPUSD';
    const XRPEUR = 'XRPEUR';
    const XRPBTC = 'XRPBTC';
    const FTCBTC = 'FTCBTC';
    const LTCBTC = 'LTCBTC';
    const LTCUSD = 'LTCUSD';
    const DRKUSD = 'DRKUSD';
    const NXTBTC = 'NXTBTC';
    const DRKBTC = 'DRKBTC';
    const XMRBTC = 'XMRBTC';
    const XCPBTC = 'XCPBTC';
    const MAIDBTC = 'MAID/BTC';
    const ETHBTC = 'ETHBTC';
    const ETHUSD = 'ETHUSD';
    const DAOETH = 'DAOETH';
    const BTCCNY = 'BTCCNY';
    const ETHCNY = 'ETHCNY';
    const DGDCNY = 'DGDCNY';
    const PLSCNY = 'PLSCNY';
    const BTSCNY = 'BTSCNY';
    const BITCNYCNY = 'BITCNY/CNY';
    const DCSCNY = 'DCSCNY';
    const SCCNY = 'SC/CNY';
    const ETCCNY = 'ETCCNY';
    const FSTCNY = '1SŦCNY'; // ! NB: Variables can't start with numbers
    const REPCNY = 'REPCNY';
    const ANSCNY = 'ANSCNY';
    const ZECCNY = 'ZECCNY';
    const ZMCCNY = 'ZMCCNY';
    const GNTCNY = 'GNTCNY';

    public static function Base($strPair){
        $parts = explode('/', $strPair);
        if (count($parts) == 2 && mb_strlen($parts[0]) >= 2 && mb_strlen($parts[1]) >= 2)
            return $parts[0];

        if (mb_strlen($strPair) == 6)
            return mb_substr($strPair, 0, 3);

        throw new Exception('Unsupported currency pair string');
    }

    public static function Quote($strPair){
        $parts = explode('/', $strPair);
        if (count($parts) == 2 && mb_strlen($parts[0]) >= 2 && mb_strlen($parts[1]) >= 2)
            return $parts[1];

        if (mb_strlen($strPair) == 6)
            return mb_substr($strPair, 3, 3);

        throw new Exception('Unsupported currency pair string');
    }

    public static function MakePair($base, $quote)
    {
        if(mb_strlen($base) != 3 || mb_strlen($quote) != 3)
            return $base . '/' . $quote;

        return $base . $quote;
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
    /** @var string */
    public $exchange;
    /** @var int */
    public $id;
    /** @var string */
    public $type;
    /** @var string */
    public $currency;
    /** @var float */
    public $amount;
    /** @var int or MongoDate */
    public $timestamp;

    public function isValid()
    {
        if (!is_string($this->exchange) || $this->exchange == "") {
            printf("Exchange is empty: ");
            var_dump($this->exchange);
            return false;
        }

        if (!is_int($this->id) || $this->id == 0) {
            printf("Id is empty: ");
            var_dump($this->id);
            return false;
        }

        if (!is_string($this->type) ||
            ($this->type != TransactionType::Credit && $this->type != TransactionType::Debit)) {
            printf("Type is invalid: ");
            var_dump($this->type);
            return false;
        }

        if (!is_string($this->currency) || $this->currency == "") {
            printf("Currency is invalid: ");
            var_dump($this->currency);
            return false;
        }

        if (!is_float($this->amount) || $this->amount == 0.0) {
            printf("Amount is empty: ");
            var_dump($this->amount);
            return false;
        }

        /* Need to make sure that timestamps are always the same type first
        if (!is_int($this->timestamp) || $this->timestamp == 0) {
            printf("Timestamp is empty");
            return false;
        }
         */

        return true;
    }
}

class Trade {
    /** @var int */
    public $tradeId;
    /** @var int */
    public $orderId;
    /** @var string */
    public $exchange;
    /** @var string */
    public $currencyPair;
    /** @var string */
    public $orderType;
    /** @var float */
    public $price;
    /** @var float */
    public $quantity;
    /** @var int or MongoDate */
    public $timestamp;

    public function isValid()
    {
        if (!is_int($this->tradeId) || $this->tradeId == 0) {
            printf("tradeId is empty: ");
            var_dump($this->tradeId);
            return false;
        }

        if (!is_int($this->orderId) || $this->orderId == 0) {
            printf("orderId is empty: ");
            var_dump($this->orderId);
            return false;
        }

        if (!is_string($this->exchange) || $this->exchange == "") {
            printf("Exchange is empty: ");
            var_dump($this->exchange);
            return false;
        }

        if (!is_string($this->currencyPair) || $this->currencyPair == "") {
            printf("currencyPair is empty: ");
            var_dump($this->currencyPair);
            return false;
        }

        if (!is_string($this->orderType) || $this->orderType == "") {
            printf("orderType is empty: ");
            var_dump($this->orderType);
            return false;
        }

        if (!is_float($this->price) || $this->price == 0.0) {
            printf("price is empty: ");
            var_dump($this->price);
            return false;
        }

        if (!is_float($this->quantity) || $this->quantity == 0.0) {
            printf("quantity is empty: ");
            var_dump($this->quantity);
            return false;
        }

        /* Need to make sure that timestamps are always the same type first
        if (!is_int($this->timestamp) || $this->timestamp == 0) {
            printf("timestamp is empty: ");
            var_dump($this->timestamp);
            return false;
        }
         */
        return true;
    }
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
