<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 12:15 PM
 */

require_once('ConfigAccountLoader.php');
require_once('MongoAccountLoader.php');

class PoloniexTest extends PHPUnit_Framework_TestCase {

    protected $bf;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);
        date_default_timezone_set('UTC');

        $cal = new MongoAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Poloniex));
        $this->bf = $exchanges[Exchange::Poloniex];
    }

    public function testBalances()
    {
        if($this->bf instanceof Poloniex)
        {
            $ret = $this->bf->balances();

            $this->assertNotEmpty($ret);
        }
    }

    public function testBuyOrderSubmission()
    {
        if($this->bf instanceof Poloniex)
        {
            $response = $this->bf->buy(CurrencyPair::XCPBTC, 1, 0.0001);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testSellOrderSubmission()
    {
        if($this->bf instanceof Poloniex)
        {
            $response = $this->bf->sell(CurrencyPair::XCPBTC, 1, 1000);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testMyTrades()
    {
        if($this->bf instanceof Poloniex)
        {
            $res = $this->bf->tradeHistory(50);
            $this->assertNotNull($res);
        }
    }

    public function testPublicTrades()
    {
        if($this->bf instanceof Poloniex)
        {
            $res = $this->bf->trades(CurrencyPair::XCPBTC, time()-600);
            $this->assertNotNull($res);
        }
    }

    private function checkAndCancelOrder($response)
    {
        if(!$this->bf instanceof Poloniex)
            return;

        $this->assertNotNull($response);

        $this->assertTrue($this->bf->isOrderAccepted($response));
        $this->assertTrue($this->bf->isOrderOpen($response));

        $this->assertNotNull($this->bf->cancel($this->bf->getOrderID($response)));
        $this->assertFalse($this->bf->isOrderOpen($response));
    }
}
 