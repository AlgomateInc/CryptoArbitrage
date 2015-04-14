<?php

require_once('IReporter.php');
require_once('IStatisticsGenerator.php');

class MongoReporter implements IReporter, IStatisticsGenerator
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

    function computeMarketStats()
    {
        $this->computeCandle(60);//1min
        $this->computeCandle(60*5);//5min
        $this->computeCandle(60*15);//15min
        $this->computeCandle(60*60);//1hr
        $this->computeCandle(60*60*4);//4hr
        $this->computeCandle(60*60*24);//24hr
    }

    private function computeCandle($intervalSecs)
    {
        $mdDb = $this->mdb->market;

        $now = time();

        $ops = array(
            array(
                '$match' => array(
                    'Timestamp' => array(
                        '$gte' => new MongoDate(floor($now/$intervalSecs)*$intervalSecs),
                        '$lt' => new MongoDate(floor($now/$intervalSecs)*$intervalSecs + $intervalSecs)
                    )
                )
            ),
            array(
                '$group' => array(
                    '_id' => array(
                        'market' => '$Exchange',
                        'pair' => '$CurrencyPair'
                    ),
                    'Open' => array('$first' => '$Last'),
                    'High' => array('$max' => '$Last'),
                    'Low' => array('$min' => '$Last'),
                    'Close' => array('$last' => '$Last')
                )
            ),
            array(
                '$project' => array(
                    '_id'=>0,
                    'Exchange' => '$_id.market',
                    'CurrencyPair' => '$_id.pair',
                    'Timestamp' => array('$literal' => new MongoDate(floor($now/$intervalSecs)*$intervalSecs)),
                    'Open'=>1, 'High'=>1, 'Low'=>1, 'Close'=>1
                )
            )
        );

        $res = $mdDb->aggregate($ops);

        if($res['ok'] === 0)
            throw new Exception("Aggregation didnt work");

        ////////////////////////////////
        $candleData = $this->mdb->candles;
        foreach ($res['result'] as $item)
        {
            $candleData->update(
                array('Timestamp' => $item['Timestamp'], 'Exchange' => $item['Exchange'],'CurrencyPair' => $item['CurrencyPair']),
                $item,
                array('upsert' => true)
            );
        }
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
