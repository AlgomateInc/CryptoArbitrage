<?php

require_once('IReporter.php');

class MongoReporter implements IReporter
{
    private $mongo;
    private $mdb;
    
    public function __construct(){
        global $mongodb_uri, $mongodb_db;
        
        $this->mongo = new MongoClient($mongodb_uri);
        $this->mdb = $this->mongo->selectDB($mongodb_db);
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

    public function market($exchange_name, $currencyPair, $bid, $ask, $last, $vol){
        $markets = $this->mdb->market;
        $market_entry = array(
            'Exchange'=>"$exchange_name",
            'CurrencyPair'=>"$currencyPair",
            'Bid'=>"$bid",
            'Ask'=>"$ask",
            'Last'=>"$last",
            'Volume'=>"$vol",
            'Timestamp'=>new MongoDate());
        
        $markets->insert($market_entry);
        return $market_entry['_id'];
    }

    public function depth($exchange_name, $currencyPair, OrderBook $depth){
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

    public function trade($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {

    }

    public function position($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        $position_entry = array(
            'Exchange'=>"$exchange_name",
            'CurrencyPair'=>"$currencyPair",
            'Type'=>"$orderType",
            'Quantity'=>"$quantity",
            'Price'=>"$price",
            'Timestamp'=>"$timestamp");

        $positionCollection = $this->mdb->position;
        $positionCollection->update(
            array('Timestamp' => "$timestamp", 'Exchange' => "$exchange_name",'CurrencyPair'=>"$currencyPair",
            'Type'=>"$orderType"),
            $position_entry,
            array('upsert'=>true)
        );
    }

    public function strategyOrder($strategyId, $iso)
    {
        $iso->_t = get_class($iso);

        $strategyOrders = $this->mdb->strategyorder;
        $strategyOrder_entry = array(
            'StrategyID' => $strategyId,
            'Data'=>$iso,
            'Timestamp'=>new MongoDate());

        $strategyOrders->insert($strategyOrder_entry);
        return $strategyOrder_entry['_id'];
    }

    public function order($exchange, $type, $quantity, $price, $orderId, $orderResponse, $arbid)
    {
        $order_entry = array(
            'Exchange'=>"$exchange",
            'Type'=>"$type",
            'OrderID'=>$orderId,
            'Quantity'=>$quantity,
            'Price'=>$price,
            'ExchangeResponse'=>$orderResponse,
            'Timestamp'=>new MongoDate());

        $arborders = $this->mdb->strategyorder;
        $arborders->update(
            array('_id'=>$arbid),
            array('$push' => array("Orders" => $order_entry))
        );
    }

    public function execution($arbId, $orderId, $market, $txid, $quantity, $price, $timestamp)
    {
        $exec_entry = array(
            'TxId'=>"$txid",
            'Quantity'=>$quantity,
            'Price'=>$price,
            'Timestamp'=>"$timestamp"
        );

        $arborders = $this->mdb->strategyorder;
        $arborders->update(
            array('_id' => $arbId, "Orders.OrderID" => $orderId),
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
