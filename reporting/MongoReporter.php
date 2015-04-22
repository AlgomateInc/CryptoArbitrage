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
        //1min, 5min, 15min, 1hr, 4hr, 24hr
        $intervals = array(60, 60*5, 60*15, 60*60, 60*60*4, 60*60*24);

        //compute this candle for each interval plus previous candle
        //just in case we missed a few trades before the cutoff
        //(ie computing candle at 11:01, trade came in at 10:59, last update 10:58)
        $now = time();
        foreach($intervals as $iVal)
        {
            $this->computeCandle($now - $iVal, $iVal);
            $this->computeCandle($now, $iVal);
        }
    }

    private function computeCandle($time, $intervalSecs)
    {
        $mdDb = $this->mdb->trades;

        $ops = array(
            array(
                '$match' => array(
                    'timestamp' => array(
                        '$gte' => new MongoDate(floor($time/$intervalSecs)*$intervalSecs),
                        '$lt' => new MongoDate(floor($time/$intervalSecs)*$intervalSecs + $intervalSecs)
                    )
                )
            ),
            array(
                '$sort' => array('timestamp' => -1) //should be sorted same as index
            ),
            array(
                '$group' => array(
                    '_id' => array(
                        'market' => '$exchange',
                        'pair' => '$currencyPair'
                    ),
                    'Open' => array('$last' => '$price'),
                    'High' => array('$max' => '$price'),
                    'Low' => array('$min' => '$price'),
                    'Close' => array('$first' => '$price'),
                    'Volume' => array('$sum' => '$quantity'),
                    'TradeCount' => array('$sum' => 1),
                    'Buys' => array('$sum' => array('$cond' => array(array('$eq' => array('$orderType', 'BUY')), 1, 0))),
                    'Sells' => array('$sum' => array('$cond' => array(array('$eq' => array('$orderType', 'SELL')), 1, 0))),
                    'BuyVolume' => array('$sum' => array('$cond' => array(array('$eq' => array('$orderType', 'BUY')), '$quantity', 0))),
                    'SellVolume' => array('$sum' => array('$cond' => array(array('$eq' => array('$orderType', 'SELL')), '$quantity', 0))),
                    'PxTimesVol' => array('$sum' => array('$multiply' => array('$price', '$quantity')))
                )
            ),
            array(
                '$project' => array(
                    '_id'=>0,
                    'Exchange' => '$_id.market',
                    'CurrencyPair' => '$_id.pair',
                    'Timestamp' => array('$literal' => new MongoDate(floor($time/$intervalSecs)*$intervalSecs)),
                    'Interval' => array('$literal' => $intervalSecs),
                    'Open'=>1, 'High'=>1, 'Low'=>1, 'Close'=>1, 'Volume'=>1,
                    'TradeCount'=>1, 'Buys'=>1, 'Sells'=>1, 'BuyVolume'=>1, 'SellVolume'=>1,
                    'TradeVWAP' => array('$divide' => array('$PxTimesVol', '$Volume'))
                )
            )
        );

        $res = $mdDb->aggregate($ops);

        if($res['ok'] === 0)
            throw new Exception("Aggregation didnt work");

        if(count($res['result']) == 0)
            return;

        ////////////////////////////////
        $candleData = $this->mdb->candles;
        $candleData->ensureIndex(array('Timestamp' => -1, 'Exchange' => 1,
            'CurrencyPair' => 1, 'Interval' => 1), array('unique' => true));

        $batch = new MongoUpdateBatch($candleData);
        foreach($res['result'] as $item){
            $batch->add(array(
                'q' => array('Timestamp' => $item['Timestamp'], 'Exchange' => $item['Exchange'],
                    'CurrencyPair' => $item['CurrencyPair'], 'Interval' => $item['Interval']),
                'u' => $item,
                'upsert' => true
            ));
        }
        $batch->execute();
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
        $tradeCollection = $this->mdb->trades;
        $tradeCollection->ensureIndex(array('timestamp' => -1, 'exchange' => 1,
            'currencyPair' => 1, 'tradeId' => -1), array('unique' => true));

        $batch = new MongoUpdateBatch($tradeCollection);
        foreach($trades as $t){
            if(!$t instanceof Trade)
                throw new Exception('Non-trade passed for reporting!!');

            $batch->add(array(
                'q' => array('timestamp' => $t->timestamp, 'exchange' => $t->exchange,'currencyPair'=>$t->currencyPair,
                    'tradeId'=> $t->tradeId),
                'u' => $t,
                'upsert'=>true
            ));
        }
        $batch->execute();

//        $tradeCollection->batchInsert($trades);
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
