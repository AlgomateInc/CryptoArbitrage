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
        $premium = 0.0225;
        $valueRequired = 1000;

        //get buy or sell from user
        print "Are you looking to buy or sell ($currencyPair)?: ";
        $inputValue = mb_strtoupper(trim(fgets(STDIN)));
        if($inputValue !== OrderType::BUY && $inputValue !== OrderType::SELL){
            print "Error!";
            return;
        }
        $orderType = $inputValue;

        //get the quantity from user
        print "Please enter the required " . (($orderType == OrderType::BUY)? CurrencyPair::Quote($currencyPair)
            : CurrencyPair::Base($currencyPair)) . " amount: ";
        $inputValue = fgets(STDIN);
        if($inputValue !== false) {
            $inputValue = trim($inputValue);
            if(mb_strlen($inputValue) > 0)
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
        $averagePrice /= round(count($targetMarkets), 2);

        //display all the stats
        print "Average Price: $averagePrice\n";
        print "Max Price: $maxPrice\n";
        print "Min Price: $minPrice\n\n";

        if($orderType == OrderType::BUY)
            $this->printBuyPricingInfo($currencyPair, $averagePrice, $premium, $valueRequired);
        else
            $this->printSellPricingInfo($currencyPair, $averagePrice, $premium, $valueRequired);
    }

    function printBuyPricingInfo($currencyPair, $price, $premium, $valueRequired)
    {
        $base = CurrencyPair::Base($currencyPair);
        $quote = CurrencyPair::Quote($currencyPair);

        $priceTarget = Currency::RoundValue($price * (1 + $premium), $quote);
        $sizeTarget = Currency::RoundValue($valueRequired / $priceTarget, $base, PHP_ROUND_HALF_DOWN);
        $value = $priceTarget * $sizeTarget;
        $profit = Currency::RoundValue($valueRequired - ($sizeTarget * $price), $quote);
        print "Desired($quote): $valueRequired\nPrice($quote): $price\nSize($base): $sizeTarget\nFee($quote): $profit\n";
        print "\n";
        print "Effective Price: $priceTarget\nTotal: $value\n";
        print "CSV: $valueRequired,$price,$sizeTarget,$profit";
    }

    function printSellPricingInfo($currencyPair, $price, $premium, $valueRequired)
    {
        $base = CurrencyPair::Base($currencyPair);
        $quote = CurrencyPair::Quote($currencyPair);

        $priceTarget = Currency::RoundValue($price * (1 - $premium), $quote);
        $sizeTarget = Currency::RoundValue($valueRequired * (1 - $premium), $base);
        $value = $price * $sizeTarget;
        $profit = $valueRequired - $sizeTarget;
        print "Desired($base): $valueRequired\nPrice($quote): $price\nSize($base): $sizeTarget\nFee($base): $profit\n";
        print "\n";
        print "Effective Price: $priceTarget\nTotal: $value\n";
        print "CSV: $valueRequired,$price,$sizeTarget,$profit";
    }

    public function shutdown()
    {
        // TODO: Implement shutdown() method.
    }
}

$b = new BulkPriceQuoter();
$b->start();
