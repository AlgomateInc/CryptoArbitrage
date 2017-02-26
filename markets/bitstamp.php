<?php

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../curl_helper.php');
require_once('BaseExchange.php');
require_once(__DIR__.'/../OrderExecution.php');
require_once('NonceFactory.php');

class BitstampExchange extends BaseExchange
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

    public function supportedCurrencyPairs(){
        return array(CurrencyPair::BTCUSD);
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @return mixed The minimum order size
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        return 5.0/$pairRate; //minimum is $5
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
        $this->assertValidCurrencyPair($currencyPair);

        $bstamp_depth = curl_query('https://www.bitstamp.net/api/order_book/');

        $bstamp_depth['bids'] = array_slice($bstamp_depth['bids'],0,150);
        $bstamp_depth['asks'] = array_slice($bstamp_depth['asks'],0,150);

        return new OrderBook($bstamp_depth);
    }

    public function ticker($pair)
    {
        $this->assertValidCurrencyPair($pair);

        $raw = curl_query('https://www.bitstamp.net/api/ticker/');

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['bid'];
        $t->ask = $raw['ask'];
        $t->last = $raw['last'];
        $t->volume = $raw['volume'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        return array();
    }

    private function assertValidCurrencyPair($pair){
        if($pair != CurrencyPair::BTCUSD)
            throw new UnexpectedValueException("Currency pair not supported");
    }

    public function buy($pair, $quantity, $price)
    {
        $this->assertValidCurrencyPair($pair);

        return $this->authQuery('buy', array("amount" => $quantity, "price" => $price));
    }

    public function sell($pair, $quantity, $price)
    {
        $this->assertValidCurrencyPair($pair);

        return $this->authQuery('sell', array("amount" => $quantity, "price" => $price));
    }

    public function cancel($orderId)
    {
        return $this->authQuery('cancel_order', array('id' => $orderId));
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

    public function tradeHistory($desiredCount = INF)
    {
        $numFetched = 0;
        $ret = array();

        do
        {
            $res = $this->assertSuccessResponse($this->authQuery('user_transactions',
                array('limit'=>1000, 'offset'=>$numFetched)));
            sleep(1);

            foreach ($res as $od) {

                if($od['type'] != 2)
                    continue;

                $td = new Trade();
                $td->exchange = $this->Name();
                $td->currencyPair = CurrencyPair::BTCUSD;
                $td->orderType = ($od['usd'] > 0)? OrderType::SELL : OrderType::BUY;
                $td->price = $od['btc_usd'];
                $td->quantity = abs($od['btc']);
                $td->timestamp = $od['datetime'];

                $ret[] = $td;
                $numFetched += 1;

                if($numFetched >= $desiredCount)
                    break;
            }

            printf("Fetched $numFetched trade records...\n");
        }
        while ($numFetched < $desiredCount && count($res) == 1000);

        return $ret;
    }

    function authQuery($method, array $req = array()) {
        if(!$this->nonceFactory instanceof NonceFactory)
            throw new Exception('No way to get nonce!');

        // generate the POST data string
        $req['key'] = $this->key;
        $req['nonce'] = $this->nonceFactory->get();
        $req['signature'] = mb_strtoupper(hash_hmac("sha256", $req['nonce'] . $this->custid . $this->key, $this->secret));
        $post_data = http_build_query($req, '', '&');

        return curl_query('https://www.bitstamp.net/api/' . $method . '/', $post_data);
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['id'];
    }
}

