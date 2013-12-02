<?php

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../curl_helper.php');
require_once('IExchange.php');
require_once(__DIR__.'/../OrderExecution.php');
require_once('NonceFactory.php');

class BitstampExchange implements IExchange
{
    private $custid;
    private $key;
    private $secret;
    private $nonceFactory;

    public function __construct($custid, $key, $secret){
        $this->custid = $custid;
        $this->key = $key;
        $this->secret = $secret;

        $this->nonceFactory = new NonceFactory();
    }

    public function Name(){
        return 'Bitstamp';
    }

    public function balances()
    {
        $bstamp_info = $this->assertSuccessResponse($this->authQuery('balance'));

        $balances = array();
        $balances[Currency::USD] = $bstamp_info['usd_balance'];
        $balances[Currency::BTC] = $bstamp_info['btc_balance'];

        return $balances;
    }

    public function depth($currencyPair){

        if($currencyPair != CurrencyPair::BTCUSD)
            throw new UnexpectedValueException("Currency pair not supported");

        $bstamp_depth = curl_query('https://www.bitstamp.net/api/order_book/');

        $bstamp_depth['bids'] = array_slice($bstamp_depth['bids'],0,150);
        $bstamp_depth['asks'] = array_slice($bstamp_depth['asks'],0,150);

        return $bstamp_depth;
    }

    public function ticker()
    {
        $raw = curl_query('https://www.bitstamp.net/api/ticker/');

        $t = new Ticker();
        $t->bid = $raw['bid'];
        $t->ask = $raw['ask'];
        $t->last = $raw['last'];

        return $t;
    }

    public function buy($quantity, $price)
    {
        return $this->authQuery('buy', array("amount" => $quantity, "price" => $price));
    }

    public function sell($quantity, $price)
    {
        return $this->authQuery('sell', array("amount" => $quantity, "price" => $price));
    }

    public function activeOrders()
    {
        return $this->authQuery('open_orders');
    }

    public function hasActiveOrders()
    {
        $ao = $this->activeOrders();

        return count($ao) > 0;
    }

    public function isOrderAccepted($orderResponse)
    {
        if(!isset($orderResponse['error'])){
            return isset($orderResponse['id']) && isset($orderResponse['amount']);
        }

        return false;
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        $ao = $this->activeOrders();

        //search the active order list for our order
        $orderId = $orderResponse['id'];
        for($i = 0;$i < count($ao);$i++)
        {
            $order = $ao[$i];

            if($order['id'] == $orderId)
                return true;
        }

        return false;
    }

    public function getOrderExecutions($orderResponse)
    {
        $usrTx = $this->authQuery('user_transactions');

        $orderTx = array();

        for($i = 0; $i< count($usrTx); $i++)
        {
            if($usrTx[$i]['order_id'] == $orderResponse['id'])
            {
                $exec = new OrderExecution();
                $exec->txid = $usrTx[$i]['id'];
                $exec->orderId = $usrTx[$i]['order_id'];
                $exec->quantity = abs($usrTx[$i]['btc']);
                $exec->price = abs((float)$usrTx[$i]['btc_usd']);
                $exec->timestamp = $usrTx[$i]['datetime'];

                $orderTx[] = $exec;
            }
        }

        return $orderTx;
    }

    function assertSuccessResponse($response)
    {
        if(isset($response['error']))
            throw new Exception($response['error']);

        return $response;
    }

    public function transactions()
    {
        $response =  $this->authQuery('user_transactions', array('limit'=>1000));
        $this->assertSuccessResponse($response);

        $ret = array();
        foreach($response as $btx)
        {
            //skip over trades
            if($btx['type'] == 2)
                continue;

            $tx = new Transaction();
            $tx->exchange = Exchange::Bitstamp;
            $tx->id = $btx['id'];
            $tx->type = ($btx['type'] == 0)? TransactionType::Credit : TransactionType::Debit;
            $tx->currency = ($btx['usd'] != 0)? Currency::USD : Currency::BTC;
            $tx->amount = ($btx['usd'] != 0)? $btx['usd'] : $btx['btc'];
            $tx->timestamp = new MongoDate(strtotime($btx['datetime']));

            $ret[] = $tx;
        }

        return $ret;
    }

    function authQuery($method, array $req = array()) {
        if(!$this->nonceFactory instanceof NonceFactory)
            throw new Exception('No way to get nonce!');

        // generate the POST data string
        $req['key'] = $this->key;
        $req['nonce'] = $this->nonceFactory->get();
        $req['signature'] = strtoupper(hash_hmac("sha256", $req['nonce'] . $this->custid . $this->key, $this->secret));
        $post_data = http_build_query($req, '', '&');

        return curl_query('https://www.bitstamp.net/api/' . $method . '/', $post_data);
    }
}

