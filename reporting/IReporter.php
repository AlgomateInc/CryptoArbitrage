<?php

interface IReporter
{
    public function balance($exchange_name, $currency, $balance);
    public function fees($exchange_name, $currencyPair, $takerFee, $makerFee);
    public function market($exchange_name, $currencyPair, $bid, $ask, $last, $vol);
    public function depth($exchange_name, $currencyPair, OrderBook $depth);
    public function trade($exchange_name, $currencyPair, $tradeId, $orderId, $orderType, $price, $quantity, $timestamp);
    public function trades($exchange_name, $currencyPair, array $trades); //trades is an array of Trade objects
    public function transaction($exchange_name, $id, $type, $currency, $amount, $timestamp);

    public function strategyOrder($strategyId, $iso);

    public function order($exchange, $type, $quantity, $price, $orderId, $orderResponse, $arbid);
    public function cancel($strategyId, $orderId, $cancelQuantity, $cancelResponse);
    public function execution($arbId, $orderId, $market, $txid, $quantity, $price, $timestamp);
    public function orderMessage($strategyId, $orderId, $messageCode, $messageText);

    public function position($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp);
}

?>
