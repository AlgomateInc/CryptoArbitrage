<?php

require_once('IReporter.php');
require_once('IStatisticsGenerator.php');

class MongoReporter implements IReporter, IStatisticsGenerator
{
    private $mongo;
    private $mdb;
    
    public function __construct($mongodb_uri)
    {
        //expect 'servername/databasename' url format
        $pos = mb_strrpos($mongodb_uri,'/');
        if($pos === false)
            throw new Exception('MongoDB database name not specified');
        $mongodb_db = mb_substr($mongodb_uri, $pos + 1);

        $this->mongo = new MongoDB\Client($mongodb_uri);
        $this->mdb = $this->mongo->selectDatabase($mongodb_db);
    }

    public function balance($exchange_name, $currency, $balance){
        $balances = $this->mdb->balance;
        $balances->createIndex(array('Timestamp' => -1));

        $balance_entry = array(
            'Exchange'=>"$exchange_name",
            'Currency'=>"$currency",
            'Balance'=>"$balance",
            'Timestamp'=>new MongoDB\BSON\UTCDateTime());
        
        $res = $balances->insertOne($balance_entry);
        return $res->getInsertedId();
    }

    public function fees($exchange_name, $currencyPair, $takerFee, $makerFee)
    {
        $fees = $this->mdb->fees;
        $fees->createIndex(array('Timestamp' => -1, 'Exchange' => 1, 'CurrencyPair' => 1));

        $fee_entry = array(
            'Exchange'    => "$exchange_name",
            'CurrencyPair'=> "$currencyPair",
            'TakerFee'    => "$takerFee",
            'MakerFee'    => "$makerFee",
            'Timestamp'   => new MongoDB\BSON\UTCDateTime());
        
        $res = $fees->insertOne($fee_entry);
        return $res->getInsertedId();
    }

    public function market($exchange_name, $currencyPair, $bid, $ask, $last, $vol){
        $markets = $this->mdb->market;
        $markets->createIndex(array('Timestamp' => -1));

        $market_entry = array(
            'Exchange'=>"$exchange_name",
            'CurrencyPair'=>"$currencyPair",
            'Bid'=>"$bid",
            'Ask'=>"$ask",
            'Last'=>"$last",
            'Volume'=>"$vol",
            'Timestamp'=>new MongoDB\BSON\UTCDateTime());
        
        $res = $markets->insertOne($market_entry);
        return $res->getInsertedId();
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
                        '$gte' => new MongoDB\BSON\UTCDateTime(mongoDateOfPHPDate(floor($time/$intervalSecs)*$intervalSecs)),
                        '$lt' => new MongoDB\BSON\UTCDateTime(mongoDateOfPHPDate(floor($time/$intervalSecs)*$intervalSecs + $intervalSecs))
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
                    'Timestamp' => array('$literal' => new MongoDB\BSON\UTCDateTime(mongoDateOfPHPDate(floor($time/$intervalSecs)*$intervalSecs))),
                    'Interval' => array('$literal' => $intervalSecs),
                    'Open'=>1, 'High'=>1, 'Low'=>1, 'Close'=>1, 'Volume'=>1,
                    'TradeCount'=>1, 'Buys'=>1, 'Sells'=>1, 'BuyVolume'=>1, 'SellVolume'=>1,
                    'TradeVWAP' => array('$cond' => array(array('$eq' => array('$Volume', 0)), '$Open', array('$divide' => array('$PxTimesVol', '$Volume'))))
                )
            )
        );

        $res = $mdDb->aggregate($ops);
        // throws Exceptions so error checking not needed

        // TODO eventually use this $res as a cursor instead of converting to
        // array, but first ensure compatibility with new libraries
        $res_array = $res->toArray();
        if(count($res_array) == 0)
            return;

        ////////////////////////////////
        $candleData = $this->mdb->candles;
        $candleData->createIndex(array('Timestamp' => -1, 'Exchange' => 1,
            'CurrencyPair' => 1, 'Interval' => 1), array('unique' => true));

        $batch = [];
        foreach($res_array as $item){
            $batch[] = [
                'updateOne'=> [
                    ['Timestamp' => $item['Timestamp'],
                     'Exchange' => $item['Exchange'],
                     'CurrencyPair'=> $item['CurrencyPair'],
                     'Interval'=> $item['Interval']], // filter
                    [ '$set' => $item ], // update
                    ['upsert'=>true], // options
                ]
            ];
        }
        $candleData->bulkWrite($batch);
    }

    public function depth($exchange_name, $currencyPair, OrderBook $depth){
        $orderbooks = $this->mdb->orderbook;
        $orderbooks->createIndex(array('Timestamp' => -1));

        $book_entry = array(
            'Exchange'=>"$exchange_name",
            'CurrencyPair'=>"$currencyPair",
            'Depth'=>$depth,
            'OnePercentVolume'=>$depth->getOrderBookVolume(1.0),
            'Timestamp'=>new MongoDB\BSON\UTCDateTime());
        
        $res = $orderbooks->insertOne($book_entry);
        return $res->getInsertedId();
    }
    
    public function trades($exchange_name, $currencyPair, array $trades){
        $tradeCollection = $this->mdb->trades;
        $tradeCollection->createIndex(array('timestamp' => -1, 'exchange' => 1,
            'currencyPair' => 1, 'tradeId' => -1), array('unique' => true));

        $batch = [];
        foreach($trades as $t){
            if(!$t instanceof Trade)
                throw new Exception('Non-trade passed for reporting!!');

            $batch[] = [
                'updateOne'=> [
                    ['timestamp' => $t->timestamp,
                     'exchange' => $t->exchange,
                     'currencyPair'=>$t->currencyPair,
                     'tradeId'=> $t->tradeId], // filter
                    [ '$set' => $t ], // update
                    ['upsert'=>true], // options
                ]
            ];
        }
        $tradeCollection->bulkWrite($batch);
    }

    public function trade($exchange_name, $currencyPair, $tradeId, $orderId, $orderType, $price, $quantity, $timestamp)
    {
        $t = new Trade();
        $t->exchange = $exchange_name;
        $t->currencyPair = $currencyPair;
        $t->tradeId = $tradeId;
        $t->orderId = $orderId;
        $t->orderType = $orderType;
        $t->price = $price;
        $t->quantity = $quantity;
        $t->timestamp = new MongoDB\BSON\UTCDateTime(mongoDateOfPHPDate($timestamp));

        $this->trades($exchange_name, $currencyPair, array($t));
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
        $positionCollection->updateOne(
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
            'Timestamp'=>new MongoDB\BSON\UTCDateTime());

        $res = $strategyOrders->insertOne($strategyOrder_entry);
        return $res->getInsertedId();
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
            'Timestamp'=>new MongoDB\BSON\UTCDateTime());

        $arborders = $this->mdb->strategyorder;
        $arborders->updateOne(
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
        $arborders->updateOne(
            array('_id' => $arbId, "Orders.OrderID" => $orderId),
            array('$push' => array("Orders.$.Executions" => $exec_entry))
        );
    }

    public function orderMessage($strategyId, $orderId, $messageCode, $messageText)
    {
        $messageEntry = array(
            'Code'=>"$messageCode",
            'Message'=>"$messageText",
            'Timestamp'=>microtime(true)
        );

        $strategyOrderDb = $this->mdb->strategyorder;
        $strategyOrderDb->updateOne(
            array('_id' => $strategyId, "Orders.OrderID" => $orderId),
            array('$push' => array("Orders.$.Message" => $messageEntry))
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
        $txdb->updateOne(
            array('TxId' => "$id", 'Exchange' => "$exchange_name"),
            $tx_entry,
            array('upsert'=>true)
        );
    }

    public function cancel($strategyId, $orderId, $cancelQuantity, $cancelResponse)
    {
        $cancelInfo = array(
            'CancelQuantity'=>$cancelQuantity,
            'CancelResponse'=>$cancelResponse,
            'Timestamp'=>new MongoDB\BSON\UTCDateTime(mongoDateOfPHPDate(time()))
        );

        $strategies = $this->mdb->strategyorder;
        $strategies->updateOne(
            array('_id' => $strategyId, "Orders.OrderID" => $orderId),
            array('$set' => array("Orders.$.CancelInfo" => $cancelInfo))
        );
    }
}

?>
