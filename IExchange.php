<?php

interface IExchange
{
    public function Name();

    public function buy($quantity, $price);
    public function sell($quantity, $price);
    public function activeOrders();
    public function hasActiveOrders();

    public function isOrderAccepted($orderResponse);
    public function isOrderOpen($orderResponse);

    public function getOrderExecutions($orderResponse);
}

?>