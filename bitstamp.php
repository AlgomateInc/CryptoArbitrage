<?php

require_once('config.php');
require_once('curl_helper.php');
require_once('IExchange.php');

class BitstampExchange implements IExchange
{
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

    public function processTradeResponse($response)
    {
        var_dump($response);
        if(!isset($response['error'])){

        }
    }
}

function bitstamp_ticker(){
    return curl_query('https://www.bitstamp.net/api/ticker/');
}

function bitstamp_depth(){
    return curl_query('https://www.bitstamp.net/api/order_book/');
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
	if ($dec === null) throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
	return $dec;
}
