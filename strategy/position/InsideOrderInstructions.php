<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 3/31/2015
 * Time: 9:33 PM
 */

require_once('MarketOrderInstructions.php');

class InsideOrderInstructions extends MarketOrderInstructions{

    public $sizeRangePct = 0;
    public $pegOrder = false;

    public function load($data)
    {
        parent::load($data);

        if(isset($data['SizeRangePct']))
            $this->sizeRangePct = $data['SizeRangePct'];
        if(isset($data['IsPegged']))
            $this->pegOrder = $data['IsPegged'];
    }

    public function getOrders()
    {
        throw new Exception('getOrders not implemented for inside orders');
    }


}