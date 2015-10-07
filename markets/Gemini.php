<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 10/5/2015
 * Time: 10:46 PM
 */
class Gemini extends Bitfinex
{
    public function Name()
    {
        return "Gemini";
    }

    function init()
    {
        $pairs = curl_query($this->getApiUrl() . 'symbols');
        foreach($pairs as $pair){
            $this->supportedPairs[] = strtoupper($pair);
        }
    }

    public function positions()
    {
        return array();
    }

    public function ticker($pair)
    {
        $raw = new OrderBook(curl_query($this->getApiUrl() . 'book' . '/' . $pair .
            '?limit_bids=1&limit_asks=1'));

        $tradeList = curl_query($this->getApiUrl() . 'trades' . '/' . $pair . "?limit_trades=1");

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = (count($raw->bids) > 0 && $raw->bids[0] instanceof DepthItem)? $raw->bids[0]->price : 0;
        $t->ask = (count($raw->asks) > 0 && $raw->asks[0] instanceof DepthItem)? $raw->asks[0]->price : 0;
        $t->last = (count($tradeList) > 0)? $tradeList[0]['price'] : 0;
        $t->volume = 0;

        return $t;
    }

    function getApiUrl()
    {
        return 'https://api.gemini.com/v1/';
    }

    protected function generateHeaders($key, $payload, $signature)
    {
        return array(
            'X-GEMINI-APIKEY: '.$key,
            'X-GEMINI-PAYLOAD: '.$payload,
            'X-GEMINI-SIGNATURE: '.$signature
        );
    }
}