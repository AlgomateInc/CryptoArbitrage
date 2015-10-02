<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 10/2/2015
 * Time: 11:25 AM
 */
class GenericStrategyOrder implements IStrategyOrder
{
    private $orders = array();
    private $cancels = array();

    public function addOrder(Order $o){
        $this->orders[] = $o;
    }

    public function addCancel(OrderCancel $oc){
        $this->cancels[] = $oc;
    }

    public function getOrders()
    {
        return $this->orders;
    }

    public function getCancels()
    {
        return $this->cancels;
    }
}