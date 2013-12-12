<?php

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../curl_helper.php');
require_once('BaseExchange.php');
require_once(__DIR__.'/../OrderExecution.php');
require_once('NonceFactory.php');


abstract class BtceStyleExchange extends BaseExchange {

    abstract protected function getAuthQueryUrl();

    private $key;
    private $secret;
    private $nonceFactory;

    public function __construct($key, $secret){
        $this->key = $key;
        $this->secret = $secret;

        $this->nonceFactory = new NonceFactory();
    }

    protected function assertSuccessResponse($response)
    {
        if($response['success'] != 1)
            throw new Exception($response['error']);

        return $response['return'];
    }

    protected function authQuery($method, array $req = array()) {
        if(!$this->nonceFactory instanceof NonceFactory)
            throw new Exception('No way to get nonce!');

        $req['method'] = $method;
        $req['nonce'] = $this->nonceFactory->get();

        // generate the POST data string
        $post_data = http_build_query($req, '', '&');

        $sign = hash_hmac("sha512", $post_data, $this->secret);

        // generate the extra headers
        $headers = array(
            'Sign: '.$sign,
            'Key: '.$this->key,
        );

        return curl_query($this->getAuthQueryUrl(), $post_data, $headers);
    }

} 