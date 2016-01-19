<?php

require_once(__DIR__.'/../curl_helper.php');
require_once('BaseExchange.php');
require_once('NonceFactory.php');

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 1/15/2016
 * Time: 3:51 AM
 */
class Kraken extends BaseExchange implements ILifecycleHandler
{
    private $key;
    private $secret;
    private $nonceFactory;

    private $currencyMapping = array();
    private $marketMapping = array();

    public function __construct($key, $secret){
        $this->key = $key;
        $this->secret = $secret;

        $this->nonceFactory = new NonceFactory();
    }

    function init()
    {
        $curr = $this->publicQuery('Assets');
        foreach($curr as $krakenName => $currencyInfo)
        {
            $altName = $currencyInfo['altname'];
            if($altName == 'XBT')
                $altName = Currency::BTC;

            $this->currencyMapping[$altName] = $krakenName;
        }

        foreach($this->supportedCurrencyPairs() as $pair)
        {
            $base = CurrencyPair::Base($pair);
            $quote = CurrencyPair::Quote($pair);

            $this->marketMapping[$pair] = $this->currencyMapping[$base] . $this->currencyMapping[$quote];
        }
    }

    public function Name()
    {
        return 'Kraken';
    }

    public function balances()
    {
        $balance_info = $this->privateQuery('Balance');

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = 0;
            foreach($balance_info as $krakenCurrencyName => $amount)
                if(strcasecmp($this->currencyMapping[$curr], $krakenCurrencyName) == 0)
                    $balances[$curr] += $amount;
        }

        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    public function supportedCurrencyPairs()
    {
        return array(CurrencyPair::BTCUSD, CurrencyPair::ETHBTC, CurrencyPair::ETHUSD);
    }

    public function minimumOrderSize($pair, $pairRate)
    {
        return 0.01;
    }

    private function getApiUrl()
    {
        return 'https://api.kraken.com/' . $this->getApiVersion() . '/';
    }

    private function getApiVersion()
    {
        return '0';
    }

    private function publicQuery($endpoint, $post_data = null, $headers = array())
    {
        $res = curl_query($this->getApiUrl() . 'public/' . $endpoint, $post_data, $headers);

        return $this->assertSuccessResponse($res);
    }

    private function privateQuery($endpoint, $request = array())
    {
        if(!$this->nonceFactory instanceof NonceFactory)
            throw new Exception('No way to get nonce!');

        $request['nonce'] = strval($this->nonceFactory->get());

        // build the POST data string
        $postdata = http_build_query($request, '', '&');

        // set API key and sign the message
        $path = '/' . $this->getApiVersion() . '/private/' . $endpoint;
        $sign = hash_hmac('sha512', $path . hash('sha256', $request['nonce'] . $postdata, true), base64_decode($this->secret), true);
        $headers = array(
            'API-Key: ' . $this->key,
            'API-Sign: ' . base64_encode($sign)
        );

        $res = curl_query($this->getApiUrl() . 'private/' . $endpoint, $postdata, $headers);

        return $this->assertSuccessResponse($res);
    }

    protected function assertSuccessResponse($response)
    {
        if(count($response['error']) > 0)
            throw new Exception(json_encode($response['error']));

        return $response['result'];
    }

    public function ticker($pair)
    {
        $krakenPairName = $this->marketMapping[$pair];
        $rawList = $this->publicQuery('Ticker', 'pair=' . $krakenPairName);
        $raw = $rawList[$krakenPairName];

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['b'][0];
        $t->ask = $raw['a'][0];
        $t->last = $raw['c'][0];
        $t->volume = $raw['v'][1];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $krakenPairName = $this->marketMapping[$pair];
        $rawList = $this->publicQuery('Trades', 'pair=' . $krakenPairName);
        $tradeList = $rawList[$krakenPairName];

        $ret = array();

        foreach($tradeList as $raw) {

            if($raw[2] < $sinceDate)
                break;

            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = sha1($raw[0] . $raw[1] . $raw[2]);
            $t->price = (float) $raw[0];
            $t->quantity = (float) $raw[1];
            $t->timestamp = new MongoDate($raw[2]);
            $t->orderType = ($raw[3] == 'b')? OrderType::BUY : OrderType::SELL;

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($pair)
    {
        $krakenPairName = $this->marketMapping[$pair];
        $rawList = $this->publicQuery('Depth', 'pair=' . $krakenPairName);
        $raw = $rawList[$krakenPairName];

        $book = new OrderBook($raw);

        return $book;
    }

    public function buy($pair, $quantity, $price)
    {
        $request['pair'] = $this->marketMapping[$pair];
        $request['type'] = 'buy';
        $request['ordertype'] = 'limit';
        $request['price'] = $price;
        $request['volume'] = $quantity;

        return $this->privateQuery('AddOrder', $request);
    }

    public function sell($pair, $quantity, $price)
    {
        $request['pair'] = $this->marketMapping[$pair];
        $request['type'] = 'sell';
        $request['ordertype'] = 'limit';
        $request['price'] = $price;
        $request['volume'] = $quantity;

        return $this->privateQuery('AddOrder', $request);
    }

    public function activeOrders()
    {
        // TODO: Implement activeOrders() method.
    }

    public function hasActiveOrders()
    {
        // TODO: Implement hasActiveOrders() method.
    }

    public function cancel($orderId)
    {
        $req['txid'] = $orderId;
        return $this->privateQuery('CancelOrder', $req);
    }

    public function isOrderAccepted($orderResponse)
    {
        return isset($orderResponse['txid']) && count($orderResponse['txid']) > 0;
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        $req['txid'] = $this->getOrderID($orderResponse);
        $res = $this->privateQuery('QueryOrders', $req);

        foreach($res as $txid => $txData)
            if($txid == $this->getOrderID($orderResponse) &&
                ($txData['status'] == 'open' || $txData['status'] == 'pending'))
                return true;

        return false;
    }

    public function getOrderExecutions($orderResponse)
    {
        $req['txid'] = $this->getOrderID($orderResponse);
        $req['trades'] = true;
        $res = $this->privateQuery('QueryOrders', $req);

        $orderStatus = $res[$this->getOrderID($orderResponse)];

        $tradeReq['txid'] = implode(',',$orderStatus['trades']);
        $tradeRes = $this->privateQuery('QueryTrades', $tradeReq);

        $orderTx = array();

        foreach($tradeRes as $txid => $t){
            $exec = new OrderExecution();
            $exec->txid = $txid;
            $exec->orderId = $t['ordertxid'];
            $exec->quantity = $t['vol'];
            $exec->price = $t['price'];
            $exec->timestamp = $t['time'];

            $orderTx[] = $exec;
        }

        return $orderTx;
    }

    public function tradeHistory($desiredCount)
    {
        // TODO: Implement tradeHistory() method.
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['txid'][0];
    }
}