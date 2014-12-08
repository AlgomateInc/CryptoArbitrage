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
        return array(CurrencyPair::FTCBTC, CurrencyPair::LTCBTC, CurrencyPair::NXTBTC);
    }

    public function ticker($pair)
    {
        $mktResponse = curl_query("http://pubapi.cryptsy.com/api.php?method=singlemarketdata&marketid="
            . $this->marketIdMapping[$pair]);

        $mktInfo = $this->assertSuccessResponse($mktResponse);

        $rawTick = $mktInfo['markets'][CurrencyPair::Base($pair)];

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $rawTick['buyorders'][0]['price'];
        $t->ask = $rawTick['sellorders'][0]['price'];
        $t->last = $rawTick['lasttradeprice'];
        $t->volume = $rawTick['volume'];

        return $t;
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

        return new OrderBook($depth);
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

    public function cancel($orderId)
    {
        return $this->authQuery('cancelorder', array('orderid'=>$orderId));
    }

    public function activeOrders()
    {
        return $this->assertSuccessResponse(
            $this->authQuery('allmyorders')
        );
    }

    public function hasActiveOrders()
    {
        // TODO: Implement hasActiveOrders() method.
    }

    public function isOrderAccepted($orderResponse)
    {
        if($orderResponse['success'] == 1){
            return isset($orderResponse['orderid']);
        }

        return false;
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        $ao = $this->activeOrders();
        for($i = 0; $i< count($ao); $i++)
        {
            if($ao[$i]['orderid'] == $orderResponse['orderid'])
                return true;
        }

        return false;
    }

    public function getOrderExecutions($orderResponse)
    {
        $orderId = $orderResponse['orderid'];

        //get the date to search trade records from
        //do today minus 5 days
        $startDate = date_create('now', timezone_open('UTC'));
        date_sub($startDate, date_interval_create_from_date_string('5 days'));

        $usrTx = $this->assertSuccessResponse(
            $this->authQuery('allmytrades', array('startdate' => date_format($startDate, 'Y-m-d')))
        );

        $orderTx = array();

        //run through all the transactions and find ones related to this order
        for($i = 0; $i< count($usrTx); $i++)
        {
            if($usrTx[$i]['order_id'] == $orderId)
            {
                $exec = new OrderExecution();
                $exec->txid = $usrTx[$i]['tradeid'];
                $exec->orderId = $usrTx[$i]['order_id'];
                $exec->quantity = abs($usrTx[$i]['quantity']);
                $exec->price = abs((float)$usrTx[$i]['tradeprice']);
                $exec->timestamp = $usrTx[$i]['datetime'];

                $orderTx[] = $exec;
            }
        }

        return $orderTx;
    }

    public function tradeHistory($desiredCount)
    {

    }
}