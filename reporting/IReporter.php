<?php

interface IReporter
{
    public function balance($exchange_name, $currency, $balance);
    public function market($exchange_name, $currencyPair, $bid, $ask, $last);
    public function depth($exchange_name, $currencyPair, $depth);
    public function trades($exchange_name, $currencyPair, $trades);

    public function arbitrage($quantity, $buyExchange, $buyLimit, $sellExchange, $sellLimit);
    public function order($exchange, $type, $quantity, $price, $orderResponse, $arbid);
    public function execution($txid, $orderId, $quantity, $price, $timestamp);
}

?>
