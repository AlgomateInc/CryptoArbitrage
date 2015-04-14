<?php

require_once(__DIR__.'/../curl_helper.php');
require_once(__DIR__.'/../OrderExecution.php');
require_once('BtceStyleExchange.php');
require_once('ILifecycleHandler.php');

class BtceExchange extends BtceStyleExchange implements ILifecycleHandler
{
    private $supportedPairs = array();
    private $minOrderSize = array(); //associative, pair->size

    protected function getAuthQueryUrl(){
        return 'https://btc-e.com/tapi/';
    }

    public function Name(){
        return "Btce";
    }

    function init()
    {
        $marketsInfo = curl_query('https://btc-e.com/api/3/info');
        foreach($marketsInfo['pairs'] as $pair => $info){
            $pairName = strtoupper(str_replace('_', '', $pair));
            $this->supportedPairs[] = $pairName;
            $this->minOrderSize[$pairName] = $info['min_amount'];
        }
    }

    public function balances()
    {
        $btce_info = $this->assertSuccessResponse($this->authQuery("getInfo"));

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = $btce_info['funds'][strtolower($curr)];
        }

        return $balances;
    }

    public function supportedCurrencyPairs(){
        return array(CurrencyPair::BTCUSD, CurrencyPair::LTCBTC, CurrencyPair::LTCUSD);
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @return mixed The minimum order size
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        return $this->minOrderSize[$pair];
    }

    private function getCurrencyPairName($pair)
    {
        if(!$this->supports($pair))
            throw new UnexpectedValueException('Currency pair not supported');

        return strtolower(CurrencyPair::Base($pair)) . '_' . strtolower(CurrencyPair::Quote($pair));
    }

    public function depth($currencyPair)
    {
        $d = curl_query('https://btc-e.com/api/2/' . $this->getCurrencyPairName($currencyPair) . '/depth');

        return new OrderBook($d);
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
        $t->volume = $rawTick['ticker']['vol_cur'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        return array();
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

    public function tradeHistory($desiredCount = INF)
    {
        $numFetched = 0;
        $ret = array();

        do
        {
            $res = $this->assertSuccessResponse($this->authQuery("TradeHistory", array('from' => "$numFetched")));
            sleep(1);

            foreach ($res as $tid => $od) {
                $td = new Trade();
                $td->tradeId = $tid;
                $td->orderId = $od['order_id'];
                $td->exchange = $this->Name();
                $td->currencyPair = $od['pair'];
                $td->orderType = ($od['type'] == 'sell')? OrderType::SELL : OrderType::BUY;
                $td->price = $od['rate'];
                $td->quantity = $od['amount'];
                $td->timestamp = $od['timestamp'];

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

    public function transactionHistory($desiredCount = INF)
    {
        $numFetched = 0;
        $ret = array();

        do
        {
            $res = $this->assertSuccessResponse($this->authQuery("TransHistory", array('from' => "$numFetched")));
            sleep(1);

            foreach($res as $btxid => $btx)
            {
                $numFetched += 1;
                if($numFetched >= $desiredCount)
                    break;

                if($btx['type'] != 1 && $btx['type'] != 2)
                    continue;

                $tx = new Transaction();
                $tx->exchange = Exchange::Btce;
                $tx->id = $btxid;
                $tx->type = ($btx['type'] == 1)? TransactionType::Credit: TransactionType::Debit;
                $tx->currency = $btx['currency'];
                $tx->amount = $btx['amount'];
                $tx->timestamp = $btx['timestamp'];

                $ret[] = $tx;
            }

            printf("Fetched $numFetched transaction records...\n");
        }
        while ($numFetched < $desiredCount && count($res) == 1000);

        return $ret;
    }

    public function transactions()
    {
        $transactionList = $this->assertSuccessResponse($this->authQuery("TransHistory", array('count'=>1000)));

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

    public function cancel($orderId)
    {
        return $this->assertSuccessResponse(
            $this->authQuery('CancelOrder', array('order_id' => $orderId))
        );
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

    public function getOrderID($orderResponse)
    {
        return $orderResponse['return']['order_id'];
    }

    public function getOrderExecutions($orderResponse)
    {
        $execList = array();

        $orderId = $orderResponse['return']['order_id'];

        //TODO: figure out how to identify executions when no order id was given by server
        if($orderId == 0)
            return $execList;

        $history = $this->tradeHistory(100);
        foreach($history as $td){
            if($td instanceof Trade)
                if($td->orderId == $orderId)
                {
                    $oe = new OrderExecution();
                    $oe->txid = $td->tradeId;
                    $oe->orderId = $orderId;
                    $oe->quantity = $td->quantity;
                    $oe->price = $td->price;
                    $oe->timestamp = $td->timestamp;

                    $execList[] = $oe;
                }
        }

        return $execList;
    }

}

