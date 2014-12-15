<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 11:55 PM
 */

class SimpleOrderInstructions implements IStrategyInstructions, IStrategyOrder {
    public $exchange;
    public $currencyPair;
    public $type;
    public $price;
    public $size;

    public function load($data)
    {
        if(isset($data['Exchange']))
            $this->exchange = $data['Exchange'];

        $this->currencyPair = $data['CurrencyPair'];
        $this->type = $data['Type'];
        $this->price = $data['Price'];
        $this->size = $data['Size'];
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