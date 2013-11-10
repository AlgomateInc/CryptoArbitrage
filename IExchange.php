<?php

interface IExchange
{
    public function buy($quantity, $price);
    public function sell($quantity, $price);

    public function processTradeResponse($response);
}

?>