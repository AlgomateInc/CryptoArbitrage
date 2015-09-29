<?php

require_once('ActionProcess.php');

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 9/17/2015
 * Time: 9:49 PM
 */
class BulkPriceQuoter extends ActionProcess
{

    public function getProgramOptions()
    {
        // TODO: Implement getProgramOptions() method.
    }

    public function processOptions($options)
    {
        // TODO: Implement processOptions() method.
    }

    public function init()
    {
        // TODO: Implement init() method.
    }

    public function run()
    {
        $targetMarkets = array(Exchange::Bitstamp, Exchange::Bitfinex);
        $currencyPair = CurrencyPair::BTCUSD;
        $orderType = OrderType::BUY;
        $premium = 0.0225;
        $valueRequired = 1000;

        //get the quantity from user
        print "Please enter the required USD amount: ";
        $inputValue = fgets(STDIN);
        if($inputValue !== false) {
            $inputValue = trim($inputValue);
            if(strlen($inputValue) > 0)
                $valueRequired = $inputValue;
        }

        //get the pricing
        $averagePrice = 0;
        $maxPrice = 0;
        $minPrice = INF;
        foreach($targetMarkets as $mktName){
            $market = null;
            foreach($this->exchanges as $mkt)
                if($mkt instanceof IExchange && $mkt->Name() == $mktName){
                    $market = $mkt;
                    break;
                }

            if(!$market instanceof IExchange)
                throw new Exception("Desired market not found: $mktName");

            $depth = $market->depth($currencyPair);
            if(!$depth instanceof OrderBook)
                throw new Exception("Could not get market depth for: $mktName");

            $price = ($orderType == OrderType::BUY)? $depth->asks[0]->price : $depth->bids[0]->price;
            print "$mktName ($currencyPair): $price\n";

            /////////////
            $averagePrice += $price;
            $maxPrice = max($maxPrice, $price);
            $minPrice = min($minPrice, $price);
        }
        $averagePrice /= count($targetMarkets);

        //display all the stats
        print "Average Price: $averagePrice\n";
        print "Max Price: $maxPrice\n";
        print "Min Price: $minPrice\n\n";

        $this->printPricingInfo($averagePrice, $premium, $valueRequired);
    }

    function printPricingInfo($price, $premium, $valueRequired)
    {
        $priceTarget = round($price * (1 + $premium), 2);
        $sizeTarget = round($valueRequired / $priceTarget, 8, PHP_ROUND_HALF_DOWN);
        $value = $priceTarget * $sizeTarget;
        $profit = round($valueRequired - ($sizeTarget * $price), 2);
        print "Desired(USD): $valueRequired\nPrice(USD): $price\nSize(BTC): $sizeTarget\nFee(USD): $profit\nTotal: $value\n";
        print "$valueRequired,$price,$sizeTarget,$profit";
    }

    public function shutdown()
    {
        // TODO: Implement shutdown() method.
    }
}

$b = new BulkPriceQuoter();
$b->start();