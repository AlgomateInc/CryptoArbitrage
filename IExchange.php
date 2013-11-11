<?php

interface IExchange
{
    public function buy($quantity, $price);
    public function sell($quantity, $price);
    public function activeOrders();
    public function hasActiveOrders();

    public function processTradeResponse($response);
}

?>