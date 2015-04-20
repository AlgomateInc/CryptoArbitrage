<?php

require_once(__DIR__.'/../curl_helper.php');
require_once('BaseExchange.php');
require_once('NonceFactory.php');
require_once('IMarginExchange.php');

class Bitfinex extends BaseExchange implements IMarginExchange, ILifecycleHandler{

    private $key;
    private $secret;
    private $nonceFactory;

    private $supportedPairs = array();
    private $minOrderSizes = array(); //assoc array pair->minordersize

    public function __construct($key, $secret){
        $this->key = $key;
        $this->secret = $secret;

        $this->nonceFactory = new NonceFactory();
    }

    function init()
    {
        $pairs = curl_query($this->getApiUrl() . 'symbols');
        foreach($pairs as $pair){
            $this->supportedPairs[] = strtoupper($pair);
        }

        $minOrderSizes = curl_query($this->getApiUrl() . 'symbols_details');
        foreach($minOrderSizes as $symbolDetail){
            $this->minOrderSizes[strtoupper($symbolDetail['pair'])] = $symbolDetail['minimum_order_size'];
        }
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
            $balances[$curr] = 0;
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

    public function trades($pair, $sinceDate)
    {
        $tradeList = curl_query($this->getApiUrl() . 'trades' . '/' . $pair . "?timestamp=$sinceDate");

        $ret = array();

        foreach($tradeList as $raw) {
            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $raw['tid'];
            $t->price = (float) $raw['price'];
            $t->quantity = (float) $raw['amount'];
            $t->timestamp = new MongoDate($raw['timestamp']);
            $t->orderType = strtoupper($raw['type']);

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $raw = curl_query($this->getApiUrl() . 'book' . '/' . $currencyPair .
            '?limit_bids=50&limit_asks=50&group=1');

        $book = new OrderBook($raw);

        return $book;
    }

    public function buy($pair, $quantity, $price){
        return $this->submitOrder('buy','exchange limit', $pair, $quantity, $price);
    }

    public function sell($pair, $quantity, $price){
        return $this->submitOrder('sell','exchange limit', $pair, $quantity, $price);
    }

    public function long($pair, $quantity, $price){
        return $this->submitOrder('buy','limit', $pair, $quantity, $price);
    }

    public function short($pair, $quantity, $price){
        return $this->submitOrder('sell','limit', $pair, $quantity, $price);
    }

    private function submitOrder($side, $type, $pair, $quantity, $price)
    {
        $result = $this->authQuery('order/new',array(
            'symbol' => strtolower($pair),
            'amount' => "$quantity",
            'price' => "$price",
            'exchange' => 'bitfinex',
            'side' => "$side",
            'type' => "$type"
        ));

        return $result;
    }

    public function cancel($orderId)
    {
        $res = $this->authQuery('order/cancel', array(
            'order_id' => $orderId
        ));

        return $res;
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
        return isset($orderResponse['order_id']) && isset($orderResponse['id']);
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        $os = $this->authQuery('order/status', array('order_id' => $orderResponse['order_id']));

        return $os['is_live'];
    }

    public function getOrderExecutions($orderResponse)
    {
        $trades = $this->tradeHistory(50);

        $orderTx = array();

        foreach($trades as $t){

            if($t['order_id'] == $orderResponse['order_id']){
                $exec = new OrderExecution();
                $exec->txid = $t['tid'];
                $exec->orderId = $t['order_id'];
                $exec->quantity = $t['amount'];
                $exec->price = $t['price'];
                $exec->timestamp = $t['timestamp'];

                $orderTx[] = $exec;
            }
        }

        return $orderTx;
    }

    public function tradeHistory($desiredCount)
    {
        $ret = array();

        //get the last trades for all supported pairs
        foreach($this->supportedCurrencyPairs() as $pair){
            $th = $this->authQuery('mytrades',
                array('limit_trades' => $desiredCount,
                    'symbol' => strtolower($pair)));

            //make a note of the currency pair on each returned item
            //bitfinex does not return this information
            for($i = 0; $i < count($th); $i++){
                $th[$i]['pair'] = $pair;
            }

            //merge with the rest of the history
            $ret = array_merge($ret, $th);
        }

        //sort history descending by timestamp (latest trade first)
        usort($ret, function($a, $b){
            $aTime = $a['timestamp'];
            $bTime = $b['timestamp'];

            if($aTime == $bTime)
                return 0;
            return ($aTime > $bTime)? -1 : 1;
        });

        //cut down to desired size and return
        $ret = array_slice($ret, 0, $desiredCount);
        return $ret;
    }

    /**
     * @return array Provides an array of strings listing supported currency pairs
     */
    public function supportedCurrencyPairs()
    {
        return $this->supportedPairs;
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @return mixed The minimum order size
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        return $this->minOrderSizes[$pair];
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
            'X-BFX-APIKEY: '.$this->key,
            'X-BFX-PAYLOAD: '.$payload,
            'X-BFX-SIGNATURE: '.$sign
        );

        return curl_query($this->getApiUrl() . $method, $payload, $headers);
    }

    public function positions()
    {
        $rawPosList = $this->authQuery('positions');

        $retList = array();
        foreach($rawPosList as $p)
        {
            $pos = new Trade();
            $pos->currencyPair = strtoupper($p['symbol']);
            $pos->exchange = Exchange::Bitfinex;
            $pos->orderType = ($p['amount'] < 0)? OrderType::SELL : OrderType::BUY;
            $pos->price = $p['base'];
            $pos->quantity = (string)abs($p['amount']);
            $pos->timestamp = $p['timestamp'];

            $retList[] = $pos;
        }

        return $retList;
    }

    function getApiUrl()
    {
        return 'https://api.bitfinex.com/v1/';
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['order_id'];
    }
}