<?php

require_once('config.php');
require_once('curl_helper.php');
require_once('IExchange.php');

class BtceExchange implements IExchange
{
    public function buy($quantity, $price)
    {
        return btce_buy($quantity,$price);
    }

    public function sell($quantity, $price)
    {
        return btce_sell($quantity,$price);
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

    public function isOrderAccepted($orderResponse)
    {
        if($orderResponse['success'] == 1){
            return isset($orderResponse['return']) &&
                isset($orderResponse['return']['received']) &&
                isset($orderResponse['return']['order_id']);
        }

        return false;
    }

    function assertSuccessResponse($response)
    {
        if($response['success'] != 1)
            throw new Exception($response['error']);
    }

    public function processTradeResponse($response)
    {
        var_dump($response);
        if($response['success'] == 1){

        }
    }
}

function btce_buy($quantity, $price){
    $btce_result = btce_query("Trade", array("pair" => "btc_usd", "type" => "buy",
        "amount" => $quantity, "rate" => $price ));
    return $btce_result;
}

function btce_sell($quantity, $price){
    $btce_result = btce_query("Trade", array("pair" => "btc_usd", "type" => "sell",
        "amount" => $quantity, "rate" => $price ));
    return $btce_result;
}

function btce_ticker(){
    return curl_query('https://btc-e.com/api/2/btc_usd/ticker');
}

function btce_depth(){
    return curl_query('https://btc-e.com/api/2/btc_usd/depth');
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

	// our curl handle (initialize if required)
	static $ch = null;
	if (is_null($ch)) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; BTCE PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	}
	curl_setopt($ch, CURLOPT_URL, 'https://btc-e.com/tapi/');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

	// run the query
	$res = curl_exec($ch);
	if ($res === false) throw new Exception('Could not get reply: '.curl_error($ch));
	$dec = json_decode($res, true);
	if (!$dec) throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
	return $dec;
}

//$result = btce_query("getInfo");
//$result = btce_query("Trade", array("pair" => "btc_usd", "type" => "buy", "amount" => 1, "rate" => 10)); //buy 1 BTC @ 10 USD

//var_dump($result);
