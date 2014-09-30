<?php

interface IReporter
{
    public function balance($exchange_name, $currency, $balance);
    public function market($exchange_name, $currencyPair, $bid, $ask, $last, $vol);
    public function depth($exchange_name, $currencyPair, OrderBook $depth);
    public function trades($exchange_name, $currencyPair, $trades);
    public function transaction($exchange_name, $id, $type, $currency, $amount, $timestamp);

    public function arbitrage($quantity, $pair, $buyExchange, $buyLimit, $sellExchange, $sellLimit);
    public function order($exchange, $type, $quantity, $price, $orderResponse, $arbid);
    public function execution($arbId, $market, $txid, $quantity, $price, $timestamp);

    public function trade($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp);
}

?>
