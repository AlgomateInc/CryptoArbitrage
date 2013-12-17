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
        $depth = $this->assertSuccessResponse(
            $this->authQuery('depth', array('marketid' => $this->marketIdMapping[$currencyPair]))
        );

        $depth['bids'] = $depth['buy'];
        unset($depth['buy']);
        $depth['asks'] = $depth['sell'];
        unset($depth['sell']);

        return $depth;
    }

    public function buy($pair, $quantity, $price)
    {
        return $this->authQuery('createorder', array(
            'marketid' => $this->marketIdMapping[$pair],
            'ordertype' => 'Buy',
            'quantity' => $quantity,
            'price' => $price
        ));
    }

    public function sell($pair, $quantity, $price)
    {
        return $this->authQuery('createorder', array(
            'marketid' => $this->marketIdMapping[$pair],
            'ordertype' => 'Sell',
            'quantity' => $quantity,
            'price' => $price
        ));
    }

    public function activeOrders()
    {
        return $this->authQuery('allmyorders');
    }

    public function hasActiveOrders()
    {
        // TODO: Implement hasActiveOrders() method.
    }

    public function isOrderAccepted($orderResponse)
    {
        if($orderResponse['success'] == 1){
            return isset($orderResponse['return']['orderid']);
        }

        return false;
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        $ao = $this->activeOrders();
        var_dump($ao);
        $orderId = $orderResponse['return']['orderid'];
        return isset($ao['return'][$orderId]);
    }

    public function getOrderExecutions($orderResponse)
    {
        // TODO: Implement getOrderExecutions() method.
    }
}