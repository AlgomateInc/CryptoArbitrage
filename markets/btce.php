<?php

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../curl_helper.php');
require_once('IExchange.php');
require_once(__DIR__.'/../OrderExecution.php');

class BtceExchange implements IExchange
{
    public function Name(){
        return "Btce";
    }

    public function balances()
    {
        $btce_info = $this->assertSuccessResponse(btce_query("getInfo"));

        $balances = array();
        $balances[Currency::USD] = $btce_info['return']['funds']['usd'];
        $balances[Currency::BTC] = $btce_info['return']['funds']['btc'];

        return $balances;
    }

    private function getCurrencyPairName($currencyPair)
    {
        switch($currencyPair)
        {
            case CurrencyPair::BTCUSD:
                return 'btc_usd';

            default:
                throw new UnexpectedValueException('Currency pair not supported');
        }
    }

    public function depth($currencyPair)
    {
        return curl_query('https://btc-e.com/api/2/' . $this->getCurrencyPairName($currencyPair) . '/depth');
    }

    public function ticker()
    {
        $rawTick = curl_query('https://btc-e.com/api/2/btc_usd/ticker');

        $t = new Ticker();
        $t->bid = $rawTick['ticker']['sell'];
        $t->ask = $rawTick['ticker']['buy'];
        $t->last = $rawTick['ticker']['last'];

        return $t;
    }

    public function buy($quantity, $price)
    {
        $btce_result = btce_query("Trade", array("pair" => "btc_usd", "type" => "buy",
            "amount" => $quantity, "rate" => $price ));
        return $btce_result;
    }

    public function sell($quantity, $price)
    {
        $btce_result = btce_query("Trade", array("pair" => "btc_usd", "type" => "sell",
            "amount" => $quantity, "rate" => $price ));
        return $btce_result;
    }

    public function activeOrders()
    {
        return btce_query("ActiveOrders", array("pair" => "btc_usd"));
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
        return $this->assertSuccessResponse(btce_query("TradeHistory"));
    }

    public function transactions()
    {
        $response = btce_query("TransHistory", array('count'=>1000));
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

    function assertSuccessResponse($response)
    {
        if($response['success'] != 1)
            throw new Exception($response['error']);

        return $response;
    }
}

function btce_trades(){
    return curl_query('https://btc-e.com/api/2/btc_usd/trades');
}
    
function btce_query($method, array $req = array()) {
    
    global $btce_key;
    global $btce_secret;
    
	// API settings
	$key = $btce_key; // your API-key
	$secret = $btce_secret; // your Secret-key

	$req['method'] = $method;

	static $noncetime = null;
	static $nonce = null;
	if(is_null($noncetime)){ 
		$mt = explode(' ', microtime());
		$noncetime = $mt[1];
	}
	$nonce++;
	$req['nonce'] = $noncetime + $nonce;

	// generate the POST data string
	$post_data = http_build_query($req, '', '&');

	$sign = hash_hmac("sha512", $post_data, $secret);

	// generate the extra headers
	$headers = array(
		'Sign: '.$sign,
		'Key: '.$key,
	);

	return curl_query('https://btc-e.com/tapi/', $post_data, $headers);
}

//$result = btce_query("getInfo");
//$result = btce_query("Trade", array("pair" => "btc_usd", "type" => "buy", "amount" => 1, "rate" => 10)); //buy 1 BTC @ 10 USD

//var_dump($result);
