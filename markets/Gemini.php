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

        $this->minOrderSizes[CurrencyPair::BTCUSD] = 0.00001;
        $this->minOrderSizes[CurrencyPair::ETHBTC] = 0.001;
        $this->minOrderSizes[CurrencyPair::ETHUSD] = 0.001;
    }

    public function positions()
    {
        return array();
    }

    public function ticker($pair)
    {
        $tickerData = curl_query($this->getApiUrl() . 'pubticker' . '/' . $pair);

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $tickerData['bid'];
        $t->ask = $tickerData['ask'];
        $t->last = $tickerData['last'];
        $t->volume = $tickerData['volume'][CurrencyPair::Base($pair)];

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