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
    private $currencyMapping = array();
    private $marketMapping = array();

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
        // TODO: Implement balances() method.
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
        // TODO: Implement minimumOrderSize() method.
    }

    private function publicQuery($endpoint, $post_data = null, $headers = array())
    {
        $res = curl_query('https://api.kraken.com/0/public/' . $endpoint, $post_data, $headers);

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

    public function cancel($orderId)
    {
        // TODO: Implement cancel() method.
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

    public function tradeHistory($desiredCount)
    {
        // TODO: Implement tradeHistory() method.
    }

    public function getOrderID($orderResponse)
    {
        // TODO: Implement getOrderID() method.
    }
}