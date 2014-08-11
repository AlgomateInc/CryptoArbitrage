<?php

require_once('IReporter.php');

class ConsoleReporter implements IReporter
{
    public function balance($exchange_name, $currency, $balance){
        print("$exchange_name $currency Balance: $balance\n");
    }

    public function market($exchange_name, $currencyPair, $bid, $ask, $last, $vol){
        print("$exchange_name $currencyPair: Bid: $bid, Ask: $ask, Last: $last, Volume: $vol\n");
    }

    public function depth($exchange_name, $currencyPair, $depth){
        print "$exchange_name - $currencyPair\n";

        print "Bid Quantity, Bid Price, Ask Price, Ask Quantity\n";
        for($i = 0; $i < max(count($depth['bids']),count($depth['asks']));$i++){
            if($i < count($depth['bids'])){
                $px = $depth['bids'][$i][0];
                $qty = $depth['bids'][$i][1];
                print "$qty,$px";
            }else
                print ",";

            print ",";

            if($i < count($depth['asks'])){
                $px = $depth['asks'][$i][0];
                $qty = $depth['asks'][$i][1];
                print "$px,$qty";
            }else
                print ",";

            print "\n";
        }
    }
    
    public function trades($exchange_name, $currencyPair, $trades){
        print "$exchange_name - $currencyPair\n";
        var_dump($trades);
    }

    public function trade($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        print "$exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp\n";
    }

    public function arbitrage($quantity, $pair, $buyExchange, $buyLimit, $sellExchange, $sellLimit)
    {
        print "Arbitrage - $pair : BUY $buyExchange @ $buyLimit, SELL $sellExchange @ $sellLimit, SIZE $quantity\n";
    }

    public function order($exchange, $type, $quantity, $price, $orderResponse, $arbid)
    {
        print "Order: $type $exchange $quantity @ $price\n";
        var_dump($orderResponse);
    }

    public function execution($arbId, $market, $txid, $quantity, $price, $timestamp)
    {
        print "Execution $txid: $market, $quantity @ $price; $timestamp\n";
    }

    public function transaction($exchange_name, $id, $type, $currency, $amount, $timestamp)
    {
        print "Transaction $exchange_name: $id, $type, $currency, $amount, $timestamp\n";
    }
}

?>
