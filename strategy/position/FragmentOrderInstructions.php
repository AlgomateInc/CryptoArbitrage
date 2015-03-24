<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 11:55 PM
 */

require_once('LimitOrderInstructions.php');

class FragmentOrderInstructions extends LimitOrderInstructions {
    public $priceRangePct;
    public $sizeRangePct;
    public $orderCount;

    public function load($data)
    {
        parent::load($data);

        $this->priceRangePct = $data['PriceRangePct'];
        $this->sizeRangePct = $data['SizeRangePct'];
        $this->orderCount = $data['OrderCount'];
    }

    public function getOrders()
    {
        $orders = array();

        $priceRange = $this->price * ($this->priceRangePct / 100.0);
        $avgSize = $this->size / $this->orderCount;

        for ($i = 0; $i < $this->orderCount; $i++)
        {
            //creates a 'weight' for each order fragment. it will be interpolated across
            // -0.5 to 0.5, with this->orderCount number of steps
            $weight = -($this->orderCount - ($i + 1))/($this->orderCount - 1) + 0.5;

            //calculation of fragment size/price based on weight
            //size is adjusted so larger orders are away from the inside of the book
            $fragPrice = $this->price + $weight * $priceRange;
            $fragSize = $avgSize * (1.0 + ($this->sizeRangePct / 100.0) *
                    ($weight * (($this->type == OrderType::BUY)? -1.0 : 1.0)));

            //put together the order fragment
            $s = new Order();
            $s->currencyPair = $this->currencyPair;
            $s->exchange = $this->exchange;
            $s->orderType = $this->type;
            $s->limit = Currency::FloorValue($fragPrice, CurrencyPair::Quote($this->currencyPair));
            $s->quantity = Currency::FloorValue($fragSize, CurrencyPair::Base($this->currencyPair));

            $orders[] = $s;
        }

        return $orders;
    }
} 