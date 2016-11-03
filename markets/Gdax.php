<?php

require_once(__DIR__.'/../curl_helper.php');
require_once('BaseExchange.php');
require_once('NonceFactory.php');

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 11/3/2016
 * Time: 6:31 AM
 */
class Gdax extends BaseExchange implements ILifecycleHandler
{
    private $key;
    private $secret;
    private $passphrase;

    private $supportedPairs = array();
    private $minOrderSizes = array(); //assoc array pair->minordersize
    private $productId = array(); //assoc array pair->productid

    public function __construct($key, $secret, $passphrase) {
        $this->key = $key;
        $this->secret = $secret;
        $this->passphrase = $passphrase;
    }

    function init()
    {
        $pairs = curl_query($this->getApiUrl() . 'products');
        foreach($pairs as $pairInfo){
            try{
                $pair = $pairInfo['base_currency'] . $pairInfo['quote_currency'];
                CurrencyPair::Base($pair); //checks the format

                $this->supportedPairs[] = strtoupper($pair);
                $this->minOrderSizes[$pair] = $pairInfo['base_min_size'];
                $this->productId[$pair] = $pairInfo['id'];
            }catch(Exception $e){}
        }
    }

    public function Name()
    {
        return 'Gdax';
    }

    public function balances()
    {
        $balance_info = $this->authGetQuery('/accounts');

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = 0;
            foreach($balance_info as $balItem)
                if(strcasecmp($balItem['currency'], $curr) == 0)
                    $balances[$curr] += $balItem['available'];
        }

        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    public function supportedCurrencyPairs()
    {
        return $this->supportedPairs;
    }

    public function minimumOrderSize($pair, $pairRate)
    {
        return $this->minOrderSizes[$pair];
    }

    public function ticker($pair)
    {
        $raw = curl_query($this->getApiUrl() . 'products/' . $this->productId[$pair] . '/ticker');

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['bid'];
        $t->ask = $raw['ask'];
        $t->last = $raw['price'];
        $t->volume = $raw['volume'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $tradeList = curl_query($this->getApiUrl() . 'products/' . $this->productId[$pair] . '/trades');

        $ret = array();

        foreach($tradeList as $raw) {
            $tradeTime = strtotime($raw['time']);
            if($tradeTime < $sinceDate)
                continue;

            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $raw['trade_id'];
            $t->price = (float) $raw['price'];
            $t->quantity = (float) $raw['size'];
            $t->timestamp = new MongoDate();
            $t->orderType = ($raw['side'] == 'buy')? OrderType::SELL : OrderType::BUY;

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $raw = curl_query($this->getApiUrl() . 'products/' . $this->productId[$currencyPair] .
            '/book?level=2');

        $book = new OrderBook($raw);

        return $book;
    }

    public function buy($pair, $quantity, $price)
    {
        // TODO: Implement buy() method.
    }

    public function sell($pair, $quantity, $price)
    {
        // TODO: Implement sell() method.
    }

    public function activeOrders()
    {
        // TODO: Implement activeOrders() method.
    }

    public function hasActiveOrders()
    {
        // TODO: Implement hasActiveOrders() method.
    }

    public function cancel($orderId)
    {
        // TODO: Implement cancel() method.
    }

    public function isOrderAccepted($orderResponse)
    {
        // TODO: Implement isOrderAccepted() method.
    }

    public function isOrderOpen($orderResponse)
    {
        // TODO: Implement isOrderOpen() method.
    }

    public function getOrderExecutions($orderResponse)
    {
        // TODO: Implement getOrderExecutions() method.
    }

    public function tradeHistory($desiredCount)
    {
        // TODO: Implement tradeHistory() method.
    }

    public function getOrderID($orderResponse)
    {
        // TODO: Implement getOrderID() method.
    }

    function getApiUrl()
    {
        return 'https://api.gdax.com/';
    }

    function signature($request_path='', $body='', $timestamp=false, $method='GET')
    {
        $body = is_array($body) ? json_encode($body) : $body;
        $timestamp = $timestamp ? $timestamp : time();

        $what = $timestamp.$method.$request_path.$body;

        return base64_encode(hash_hmac("sha256", $what, base64_decode($this->secret), true));
    }

    function authGetQuery($method) {
        $ts = time();
        $sig = $this->signature($method, '', $ts);

        $headers = array(
            'CB-ACCESS-KEY: ' . $this->key,
            'CB-ACCESS-SIGN: ' . $sig,
            'CB-ACCESS-TIMESTAMP: ' . $ts,
            'CB-ACCESS-PASSPHRASE: ' . $this->passphrase
        );

        return curl_query($this->getApiUrl() . $method, null, $headers);
    }
}