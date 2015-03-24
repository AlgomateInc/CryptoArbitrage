<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 11:55 PM
 */

require_once('MarketOrderInstructions.php');

class LimitOrderInstructions extends MarketOrderInstructions {
    public $price;

    public function load($data)
    {
        parent::load($data);

        $this->price = $data['Price'];
    }

    public function getOrders()
    {
        $orders = array();

        $s = new Order();
        $s->currencyPair = $this->currencyPair;
        $s->exchange = $this->exchange;
        $s->orderType = $this->type;
        $s->limit = $this->price;
        $s->quantity = $this->size;

        $orders[] = $s;

        return $orders;
    }
}