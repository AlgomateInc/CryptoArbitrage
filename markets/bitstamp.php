<?php

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../curl_helper.php');
require_once('IExchange.php');
require_once(__DIR__.'/../OrderExecution.php');

class BitstampExchange implements IExchange
{
    public function Name(){
        return 'Bitstamp';
    }

    public function balances()
    {
        $bstamp_info = $this->assertSuccessResponse(bitstamp_query('balance'));

        $balances = array();
        $balances[Currency::USD] = $bstamp_info['usd_balance'];
        $balances[Currency::BTC] = $bstamp_info['btc_balance'];

        return $balances;
    }

    public function depth(){
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
        return bitstamp_buy($quantity,$price);
    }

    public function sell($quantity, $price)
    {
        return bitstamp_sell($quantity,$price);
    }

    public function activeOrders()
    {
        return bitstamp_query('open_orders');
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
        $usrTx = bitstamp_query('user_transactions');

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
        $response =  bitstamp_query('user_transactions', array('limit'=>1000));
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

}

function bitstamp_trades(){
    return curl_query('https://www.bitstamp.net/api/transactions/');
}

function bitstamp_buy($quantity, $price){
    return bitstamp_query('buy', array("amount" => $quantity, "price" => $price));
}

function bitstamp_sell($quantity, $price){
    return bitstamp_query('sell', array("amount" => $quantity, "price" => $price));
}

function bitstamp_query($method, array $req = array()) {
    
    global $bitstamp_custid;
    global $bitstamp_key;
    global $bitstamp_secret;
    
	// API settings
    $custid = $bitstamp_custid;
	$key = $bitstamp_key; // your API-key
	$secret = $bitstamp_secret; // your Secret-key

    //generate nonce
    static $noncetime = null;
    static $nonce = null;
    if(is_null($noncetime)){ 
        $mt = explode(' ', microtime());
        $noncetime = $mt[1];
    }
    $nonce++;
    $req['nonce'] = $noncetime + $nonce;
                                      
	// generate the POST data string
	$req['key'] = $key;
	$req['signature'] = strtoupper(hash_hmac("sha256", $req['nonce'] . $custid . $key, $secret));
	$post_data = http_build_query($req, '', '&');

	// our curl handle (initialize if required)
	static $ch = null;
	if (is_null($ch)) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Bitstamp PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	}
	curl_setopt($ch, CURLOPT_URL, 'https://www.bitstamp.net/api/' . $method . '/');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

	// run the query
	$res = curl_exec($ch);
	if ($res === false) throw new Exception('Could not get reply: '.curl_error($ch));
	$dec = json_decode($res, true);
	if ($dec === null) throw new Exception("Invalid data received. Server returned:\n $res");
	return $dec;
}
