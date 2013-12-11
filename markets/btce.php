<?php

require_once(__DIR__.'/../curl_helper.php');
require_once(__DIR__.'/../OrderExecution.php');
require_once('BtceStyleExchange.php');

class BtceExchange extends BtceStyleExchange
{
    protected function getAuthQueryUrl(){
        return 'https://btc-e.com/tapi/';
    }

    public function Name(){
        return "Btce";
    }

    public function balances()
    {
        $btce_info = $this->assertSuccessResponse($this->authQuery("getInfo"));

        $balances = array();
        $balances[Currency::USD] = $btce_info['return']['funds']['usd'];
        $balances[Currency::BTC] = $btce_info['return']['funds']['btc'];

        return $balances;
    }

    public function supportedCurrencyPairs(){
        return array(CurrencyPair::BTCUSD);
    }

    public function supports($currencyPair){
        return in_array($currencyPair, $this->supportedCurrencyPairs());
    }

    private function getCurrencyPairName($pair)
    {
        if(!$this->supports($pair))
            throw new UnexpectedValueException('Currency pair not supported');

        return strtolower(CurrencyPair::Base($pair)) . '_' . strtolower(CurrencyPair::Quote($pair));
    }

    public function depth($currencyPair)
    {
        return curl_query('https://btc-e.com/api/2/' . $this->getCurrencyPairName($currencyPair) . '/depth');
    }

    public function ticker($pair)
    {
        $btcePairName = $this->getCurrencyPairName($pair);

        $rawTick = curl_query("https://btc-e.com/api/2/$btcePairName/ticker");

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $rawTick['ticker']['sell'];
        $t->ask = $rawTick['ticker']['buy'];
        $t->last = $rawTick['ticker']['last'];

        return $t;
    }

    public function buy($pair, $quantity, $price)
    {
        $btcePairName = $this->getCurrencyPairName($pair);

        $btce_result = $this->authQuery("Trade", array("pair" => "$btcePairName", "type" => "buy",
            "amount" => $quantity, "rate" => $price ));
        return $btce_result;
    }

    public function sell($pair, $quantity, $price)
    {
        $btcePairName = $this->getCurrencyPairName($pair);

        $btce_result = $this->authQuery("Trade", array("pair" => "$btcePairName", "type" => "sell",
            "amount" => $quantity, "rate" => $price ));
        return $btce_result;
    }

    public function activeOrders()
    {
        return $this->authQuery("ActiveOrders", array("pair" => "btc_usd"));
    }

    public function hasActiveOrders()
    {
        $ao = $this->activeOrders();

        if($ao['success'] == 0 && $ao['error'] == "no orders")
            return false;

        return true;
    }

    public function tradeHistory()
    {
        return $this->assertSuccessResponse($this->authQuery("TradeHistory"));
    }

    public function transactions()
    {
        $response = $this->authQuery("TransHistory", array('count'=>1000));
        $this->assertSuccessResponse($response);

        $transactionList = $response['return'];

        $ret = array();
        foreach($transactionList as $btxid => $btx)
        {
            if($btx['type'] != 1 && $btx['type'] != 2)
                continue;

            $tx = new Transaction();
            $tx->exchange = Exchange::Btce;
            $tx->id = $btxid;
            $tx->type = ($btx['type'] == 1)? TransactionType::Credit: TransactionType::Debit;
            $tx->currency = $btx['currency'];
            $tx->amount = $btx['amount'];
            $tx->timestamp = new MongoDate($btx['timestamp']);

            $ret[] = $tx;
        }

        return $ret;
    }

    public function isOrderAccepted($orderResponse)
    {
        if($orderResponse['success'] == 1){
            return isset($orderResponse['return']) &&
                isset($orderResponse['return']['received']) &&
                isset($orderResponse['return']['order_id']);
        }

        return false;
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        if($orderResponse['return']['remains'] == 0 &&
            $orderResponse['return']['order_id'] == 0)
            return false;

        $ao = $this->activeOrders();
        $orderId = $orderResponse['return']['order_id'];
        return isset($ao['return'][$orderId]);
    }

    public function getOrderExecutions($orderResponse)
    {
        $execList = array();

        $orderId = $orderResponse['return']['order_id'];

        //TODO: figure out how to identify executions when no order id was given by server
        if($orderId == 0)
            return $execList;

        $history = $this->tradeHistory();
        foreach($history['return'] as $key => $item){
            if($item['order_id'] == $orderId)
            {
                $oe = new OrderExecution();
                $oe->txid = $key;
                $oe->orderId = $orderId;
                $oe->quantity = $item['amount'];
                $oe->price = $item['rate'];
                $oe->timestamp = $item['timestamp'];

                $execList[] = $oe;
            }
        }

        return $execList;
    }

}

