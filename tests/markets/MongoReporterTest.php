<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 4/15/2015
 * Time: 1:43 PM
 */

require_once('ConfigAccountLoader.php');

class MongoReporterTest extends PHPUnit_Framework_TestCase {
    protected $mongo;
    protected $mdb;

    public function setUp(){
        global $mongodb_uri, $mongodb_db;

        $this->mongo = new MongoClient($mongodb_uri);
        $this->mdb = $this->mongo->selectDB($mongodb_db);
    }

    public function testCandleGenerationCorrect()
    {
        $candleCollection = $this->mdb->candles;
        $tradesCollection = $this->mdb->trades;

        $candles = $candleCollection->find();
        foreach($candles as $c)
        {
            $date = $c['Timestamp'];
            $interval = $c['Interval'];
            $endDate = new MongoDate($date->sec + $interval, $date->usec);
            $exchange = $c['Exchange'];
            $currencyPair = $c['CurrencyPair'];

            print "Testing ($exchange, $currencyPair, $date->sec, $interval, $endDate->sec)...";

            $trades = $tradesCollection->find(array(
                'exchange'=>$exchange,
                'currencyPair'=>$currencyPair,
                'timestamp'=> array(
                        '$gte' => $date,
                        '$lt' => $endDate
                    )
            ))->sort(array('timestamp' => 1));

            if($c['TradeCount'] != count(iterator_to_array($trades)))
                print "count mismatch: " . $c['TradeCount'] .'!='. count(iterator_to_array($trades));
            print "\n";
            $this->assertCount($c['TradeCount'], $trades);
            //continue;

            ////////////////////////////////
            // check aggregated columns
            $highPx = 0;
            $lowPx = INF;
            $volume = 0;
            $buys = 0;
            $sells = 0;
            $pxTimesVol = 0;
            $openPx = null;
            $closePx = 0;
            foreach($trades as $t)
            {
                if($t['price'] > $highPx)
                    $highPx = $t['price'];
                if($t['price'] < $lowPx)
                    $lowPx = $t['price'];
                if($openPx == null)
                    $openPx = $t['price'];
                $closePx = $t['price'];

                $volume += $t['quantity'];
                $pxTimesVol += $t['quantity'] * $t['price'];
                $buys += ($t['orderType'] == OrderType::BUY)? 1 : 0;
                $sells += ($t['orderType'] == OrderType::SELL)? 1 : 0;
            }
            $this->assertEquals($c['Open'], $openPx);
            $this->assertEquals($c['Close'], $closePx);
            $this->assertEquals($c['High'], $highPx);
            $this->assertEquals($c['Low'], $lowPx);
            $this->assertEquals($c['Volume'], $volume);
            $this->assertEquals($c['Buys'], $buys);
            $this->assertEquals($c['Sells'], $sells);

            if($volume > 0){
                $tradeVwap = $pxTimesVol / $volume;
                $this->assertEquals($c['TradeVWAP'], $tradeVwap);
            }
        }
    }
}
