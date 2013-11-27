<?php

require_once('IReporter.php');

class MongoReporter implements IReporter
{
    private $mongo;
    private $mdb;
    
    public function __construct(){
        global $mongodb_uri;
        
        $this->mongo = new MongoClient($mongodb_uri);
        $this->mdb = $this->mongo->coindata;
    }
    
    public function balance($exchange_name, $currency, $balance){
        $balances = $this->mdb->balance;
        $balance_entry = array(
            'Exchange'=>"$exchange_name",
            'Currency'=>"$currency",
            'Balance'=>"$balance",
            'Timestamp'=>new MongoDate());
        
        $balances->insert($balance_entry);
        return $balance_entry['_id'];
    }

    public function market($exchange_name, $currencyPair, $bid, $ask, $last){
        $markets = $this->mdb->market;
        $market_entry = array(
            'Exchange'=>"$exchange_name",
            'CurrencyPair'=>"$currencyPair",
            'Bid'=>"$bid",
            'Ask'=>"$ask",
            'Last'=>"$last",
            'Timestamp'=>new MongoDate());
        
        $markets->insert($market_entry);
        return $market_entry['_id'];
    }

    public function depth($exchange_name, $currencyPair, $depth){
        $orderbooks = $this->mdb->orderbook;
        $book_entry = array(
            'Exchange'=>"$exchange_name",
            'CurrencyPair'=>"$currencyPair",
            'Depth'=>$depth,
            'Timestamp'=>new MongoDate());
        
        $orderbooks->insert($book_entry);
        return $book_entry['_id'];
    }
    
    public function trades($exchange_name, $currencyPair, $trades){
        $trades = $this->mdb->trades;
        $trades_entry = array(
            'Exchange'=>"$exchange_name",
            'CurrencyPair'=>"$currencyPair",
            'Trades'=>$trades,
            'Timestamp'=>new MongoDate());
        
        $trades->insert($trades_entry);
        return $trades_entry['_id'];
    }

    public function arbitrage($quantity, $buyExchange, $buyLimit, $sellExchange, $sellLimit)
    {
        $arborders = $this->mdb->arborder;
        $arborder_entry = array(
            'Quantity'=>$quantity,
            'BuyExchange'=>"$buyExchange",
            'BuyLimit'=>$buyLimit,
            'SellExchange'=>"$sellExchange",
            'SellLimit'=>$sellLimit,
            'Timestamp'=>new MongoDate());

        $arborders->insert($arborder_entry);
        return $arborder_entry['_id'];
    }

    public function order($exchange, $type, $quantity, $price, $orderResponse, $arbid)
    {
        $order_entry = array(
            'Exchange'=>"$exchange",
            'Type'=>"$type",
            'Quantity'=>$quantity,
            'Price'=>$price,
            'ExchangeResponse'=>$orderResponse,
            'Timestamp'=>new MongoDate());

        $arborders = $this->mdb->arborder;
        $arborders->update(
            array('_id'=>$arbid),
            array('$push' => array("Orders" => $order_entry))
        );
    }

    public function execution($arbId, $market, $txid, $quantity, $price, $timestamp)
    {
        $exec_entry = array(
            'TxId'=>"$txid",
            'Quantity'=>$quantity,
            'Price'=>$price,
            'Timestamp'=>"$timestamp"
        );

        $arborders = $this->mdb->arborder;
        $arborders->update(
            array('_id' => $arbId, "Orders.Exchange" => $market),
            array('$push' => array("Orders.$.Executions" => $exec_entry))
        );
    }

    public function transaction($exchange_name, $id, $type, $currency, $amount, $timestamp)
    {
        $tx_entry = array(
            'Exchange'=>"$exchange_name",
            'TxId'=>"$id",
            'Type'=>"$type",
            'Currency'=>"$currency",
            'Amount'=>$amount,
            'Timestamp'=>$timestamp
        );

        $txdb = $this->mdb->transactions;
        $txdb->update(
            array('TxId' => "$id", 'Exchange' => "$exchange_name"),
            $tx_entry,
            array('upsert'=>true)
        );
    }
}

?>
