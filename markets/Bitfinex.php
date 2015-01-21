<?php

require_once(__DIR__.'/../curl_helper.php');
require_once('BaseExchange.php');
require_once('NonceFactory.php');
require_once('IMarginExchange.php');

class Bitfinex extends BaseExchange implements IMarginExchange{

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
        $th = $this->authQuery('mytrades',
            array('limit_trades' => $desiredCount,
            'symbol' => strtolower(CurrencyPair::BTCUSD)));

        return $th;
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

        return curl_query($this->getApiUrl() . $method, $payload, $headers);
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