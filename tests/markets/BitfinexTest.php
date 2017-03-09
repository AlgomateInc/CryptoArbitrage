<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 12:15 PM
 */

require_once('ConfigAccountLoader.php');

class BitfinexTest extends PHPUnit_Framework_TestCase {

    protected $bf;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Bitfinex));
        $this->bf = $exchanges[Exchange::Bitfinex];
        $this->bf->init();
    }

    public function testPrecision()
    {
        $this->assertTrue($this->bf instanceof Bitfinex);
        $pair = CurrencyPair::BTCUSD;
        $this->assertEquals(4, $this->bf->quotePrecision($pair, 1.0));
        $this->assertEquals(5, $this->bf->quotePrecision($pair, 0.1));
        $this->assertEquals(1, $this->bf->quotePrecision($pair, 1000.0));
        $this->assertEquals(-2, $this->bf->quotePrecision($pair, 1000000.0));
    }

    public function testBalances()
    {
        if($this->bf instanceof Bitfinex)
        {
            $ret = $this->bf->balances();

            $this->assertNotEmpty($ret);
        }
    }

    public function testBuyOrderSubmission()
    {
        if($this->bf instanceof Bitfinex)
        {
            $response = $this->bf->buy(CurrencyPair::BTCUSD, 1, 1);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testSellOrderSubmission()
    {
        if($this->bf instanceof Bitfinex)
        {
            $response = $this->bf->sell(CurrencyPair::BTCUSD, 1, 10000);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testMyTrades()
    {
        if($this->bf instanceof Bitfinex)
        {
            $res = $this->bf->tradeHistory(50);
            $this->assertNotNull($res);
        }
    }

    public function testPublicTrades()
    {
        if($this->bf instanceof Bitfinex)
        {
            $res = $this->bf->trades(CurrencyPair::BTCUSD, time()-60);
            $this->assertNotNull($res);
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue($this->bf->isOrderAccepted($response));
        $this->assertTrue($this->bf->isOrderOpen($response));

        $this->assertNotNull($this->bf->cancel($response['order_id']));
        $this->assertFalse($this->bf->isOrderOpen($response));
    }
}
 
