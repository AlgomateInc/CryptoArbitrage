<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 3/23/2015
 * Time: 12:48 AM
 */

class MarketOrderInstructions implements IStrategyInstructions, IStrategyOrder {
    public $exchange;
    public $currencyPair;
    public $type;
    public $size;

    public $triggerPrice;

    public function load($data)
    {
        if(isset($data['Exchange']))
            $this->exchange = $data['Exchange'];
        if(isset($data['TriggerPrice']))
            $this->triggerPrice = $data['TriggerPrice'];

        $this->currencyPair = $data['CurrencyPair'];
        $this->type = strtoupper($data['Type']);
        $this->size = $data['Size'];
    }

    public function getOrders()
    {
        $orders = array();

        $s = new Order();
        $s->currencyPair = $this->currencyPair;
        $s->exchange = $this->exchange;
        $s->orderType = $this->type;
        $s->limit = ($this->type == OrderType::BUY)? INF : 0;
        $s->quantity = $this->size;

        $orders[] = $s;

        return $orders;

    }

}