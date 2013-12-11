<?php

require_once('BtceStyleExchange.php');

class Cryptsy extends BtceStyleExchange {

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

        $bal = $info['return']['balances_available'];

        $balances = array();
        $balances[Currency::BTC] = $bal[Currency::BTC];

        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    /**
     * @param string $currencyPair A currency pair to check support for
     * @return bool True if the pair is supported, false otherwise
     */
    public function supports($currencyPair)
    {
        // TODO: Implement supports() method.
    }

    /**
     * @return array Provides an array of strings listing supported currency pairs
     */
    public function supportedCurrencyPairs()
    {
        // TODO: Implement supportedCurrencyPairs() method.
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