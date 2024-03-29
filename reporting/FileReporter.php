<?php
namespace CryptoArbitrage\Reporting;

use CryptoMarket\Record\OrderBook;

require_once('IReporter.php');

class FileReporter implements IReporter {

    private $file;

    public function __construct($filename){

        $this->file = fopen($filename, 'w');

        $meta_data = stream_get_meta_data($this->file);
        $filename = $meta_data["uri"];
        print "Reporting to $filename\n";
    }

    public function __destruct()
    {
        fclose($this->file);
    }

    public function balance($exchange_name, $currency, $balance)
    {
        // TODO: Implement balance() method.
    }

    public function fees($exchange_name, $currencyPair, $taker, $maker)
    {
        // TODO: Implement fees() method.
    }

    public function market($exchange_name, $currencyPair, $bid, $ask, $last, $vol)
    {
        // TODO: Implement market() method.
    }

    public function depth($exchange_name, $currencyPair, OrderBook $depth)
    {
        // TODO: Implement depth() method.
    }

    public function trades($exchange_name, $currencyPair, array $trades)
    {
        // TODO: Implement trades() method.
    }

    public function transaction($exchange_name, $id, $type, $currency, $amount, $timestamp)
    {
        fwrite($this->file, "$exchange_name, $id, $type, $currency, $amount, $timestamp\n");
    }

    public function arbitrage($quantity, $pair, $buyExchange, $buyLimit, $sellExchange, $sellLimit)
    {
        // TODO: Implement arbitrage() method.
    }

    public function strategyOrder($strategyId, $iso)
    {
        // TODO: Implement strategy() method.
    }

    public function order($exchange, $type, $quantity, $price, $orderId, $orderResponse, $arbid)
    {
        // TODO: Implement order() method.
    }

    public function cancel($strategyId, $orderId, $cancelQuantity, $cancelResponse)
    {

    }

    public function execution($arbId, $orderId, $market, $txid, $quantity, $price, $timestamp)
    {
        // TODO: Implement execution() method.
    }

    public function orderMessage($strategyId, $orderId, $messageCode, $messageText)
    {
        // TODO: Implement orderMessage() method.
    }

    public function trade($exchange_name, $currencyPair, $tradeId, $orderId, $orderType, $price, $quantity, $timestamp)
    {
        fwrite($this->file, "$exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp\n");
    }

    public function position($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        // TODO: Implement position() method.
    }

    public function publicKey($serverName, $publicKey)
    {
        fwrite($this->file, "$serverName, $publicKey\n");
    }
}

