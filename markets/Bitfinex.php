<?php

require_once(__DIR__.'/../curl_helper.php');
require_once('BaseExchange.php');
require_once('NonceFactory.php');

class Bitfinex extends BaseExchange{

    private $key;
    private $secret;
    private $nonceFactory;

    public function __construct($key, $secret){
        $this->key = $key;
        $this->secret = $secret;

        $this->nonceFactory = new NonceFactory();
    }

    public function Name()
    {
        return "Bitfinex";
    }

    public function balances()
    {
        $balance_info = $this->authQuery("balances");

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            foreach($balance_info as $balItem)
                if($balItem['currency'] == strtolower($curr))
                    $balances[$curr] += $balItem['amount'];
        }

        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    public function ticker($pair)
    {
        $raw = curl_query($this->getApiUrl() . 'pubticker' . '/' . $pair);

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['bid'];
        $t->ask = $raw['ask'];
        $t->last = $raw['last_price'];
        $t->volume = $raw['volume'];

        return $t;
    }

    public function depth($currencyPair)
    {
        $raw = curl_query($this->getApiUrl() . 'book' . '/' . $currencyPair .
            '?limit_bids=50&limit_asks=50&group=1');

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

    /**
     * @return array Provides an array of strings listing supported currency pairs
     */
    public function supportedCurrencyPairs()
    {
        return array(CurrencyPair::BTCUSD, CurrencyPair::LTCBTC, CurrencyPair::LTCUSD, CurrencyPair::DRKUSD);
    }

    protected function authQuery($method, array $req = array()) {
        if(!$this->nonceFactory instanceof NonceFactory)
            throw new Exception('No way to get nonce!');

        $req['request'] = '/v1/'.$method;
        $req['nonce'] = strval($this->nonceFactory->get());

        $payload = base64_encode(json_encode($req));
        $sign = hash_hmac('sha384', $payload, $this->secret);

        // generate the extra headers
        $headers = array(
            'X-BFX-APIKEY : '.$this->key,
            'X-BFX-PAYLOAD : '.$payload,
            'X-BFX-SIGNATURE : '.$sign
        );

        return curl_query($this->getApiUrl() . $method, null, $headers);
    }

    function getApiUrl()
    {
        return 'https://api.bitfinex.com/v1/';
    }
}