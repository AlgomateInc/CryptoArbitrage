<?php

require_once('BtceStyleExchange.php');

class Cryptsy extends BtceStyleExchange {

    private $marketIdMapping = array();

    public function __construct($key, $secret)
    {
        parent::__construct($key, $secret);

        //get all the open markets so we have the ID mapping
        $markets = $this->assertSuccessResponse($this->authQuery('getmarkets'));
        foreach($this->supportedCurrencyPairs() as $pair)
        {
            foreach($markets as $mkt){
                if($mkt['primary_currency_code'] == CurrencyPair::Base($pair) &&
                    $mkt['secondary_currency_code'] == CurrencyPair::Quote($pair)){
                    $this->marketIdMapping[$pair] = $mkt['marketid'];
                }
            }
        }
    }

    protected function getAuthQueryUrl()
    {
        return 'https://www.cryptsy.com/api';
    }

    public function Name()
    {
        return 'Cryptsy';
    }

    public function balances()
    {
        $info = $this->assertSuccessResponse($this->authQuery("getinfo"));

        $bal = $info['balances_available'];

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = $bal[$curr];
        }

        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    /**
     * @return array Provides an array of strings listing supported currency pairs
     */
    public function supportedCurrencyPairs()
    {
        return array(CurrencyPair::FTCBTC);
    }

    public function ticker($pair)
    {
        // TODO: Implement ticker() method.
    }

    public function depth($currencyPair)
    {
        // TODO: Implement depth() method.
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
}