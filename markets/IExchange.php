<?php

require_once('IAccount.php');

interface IExchange extends IAccount
{
    public function ticker();
    public function depth($currencyPair);
    public function buy($quantity, $price);
    public function sell($quantity, $price);
    public function activeOrders();
    public function hasActiveOrders();

    public function isOrderAccepted($orderResponse);
    public function isOrderOpen($orderResponse);

    public function getOrderExecutions($orderResponse);
}

?>