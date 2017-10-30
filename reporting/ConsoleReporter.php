<?php
namespace CryptoArbitrage\Reporting;

use CryptoArbitrage\Reporting\IReporter;

use CryptoMarket\Record\OrderBook;

class ConsoleReporter implements IReporter
{
    public function balance($exchange_name, $currency, $balance){
        print("$exchange_name $currency Balance: $balance\n");
    }

    public function fees($exchange_name, $currencyPair, $takerFee, $makerFee)
    {
        print("$exchange_name $currencyPair Taker: $takerFee, Maker: $makerFee");
    }

    public function market($exchange_name, $currencyPair, $bid, $ask, $last, $vol){
        print("$exchange_name $currencyPair: Bid: $bid, Ask: $ask, Last: $last, Volume: $vol\n");
    }

    public function depth($exchange_name, $currencyPair, OrderBook $depth){
        print "$exchange_name - $currencyPair\n";

        print "Bid Quantity, Bid Price, Ask Price, Ask Quantity\n";
        for($i = 0; $i < max(count($depth->bids),count($depth->asks));$i++){
            if($i < count($depth->bids)){
                $px = $depth->bids[$i]->price;
                $qty = $depth->bids[$i]->quantity;
                print "$qty,$px";
            }else
                print ",";

            print ",";

            if($i < count($depth->asks)){
                $px = $depth->asks[$i]->price;
                $qty = $depth->asks[$i]->quantity;
                print "$px,$qty";
            }else
                print ",";

            print "\n";
        }
    }
    
    public function trades($exchange_name, $currencyPair, array $trades){
        print "$exchange_name - $currencyPair - " . json_encode($trades) . "\n";
    }

    public function trade($exchange_name, $currencyPair, $tradeId, $orderId, $orderType, $price, $quantity, $timestamp)
    {
        print "$exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp\n";
    }

    public function position($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        print "Position: $exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp\n";
    }

    public function arbitrage($quantity, $pair, $buyExchange, $buyLimit, $sellExchange, $sellLimit)
    {
        print "Arbitrage - $pair : BUY $buyExchange @ $buyLimit, SELL $sellExchange @ $sellLimit, SIZE $quantity\n";
    }

    public function strategyOrder($strategyId, $iso)
    {
        $data_json = json_encode($iso);
        print "Strategy Order: STRATEGY $strategyId, ORDER $data_json\n";
    }

    public function order($exchange, $type, $quantity, $price, $orderId, $orderResponse, $arbid)
    {
        if($orderId != null)
            print "Order ($orderId): $type $exchange $quantity @ $price\n";
        else {
            print "Order (NOT PLACED): $type $exchange $quantity @ $price\n";
            var_dump($orderResponse);
        }
    }

    public function cancel($strategyId, $orderId, $cancelQuantity, $cancelResponse)
    {
        print "Order ($orderId) quantity cancelled: $cancelQuantity\n";
        var_dump($cancelResponse);
    }

    public function execution($arbId, $orderId, $market, $txid, $quantity, $price, $timestamp)
    {
        print "Execution ($txid) for order $orderId: $market, $quantity @ $price; $timestamp\n";
    }

    public function orderMessage($strategyId, $orderId, $messageCode, $messageText)
    {
        print "Message ($messageCode) for order $orderId: $messageText";
    }

    public function transaction($exchange_name, $id, $type, $currency, $amount, $timestamp)
    {
        print "Transaction $exchange_name: $id, $type, $currency, $amount, $timestamp\n";
    }

    public function publicKey($serverName, $publicKey)
    {
        print "Server Name; [$serverName], Public Key: [$publicKey]";
    }
}

