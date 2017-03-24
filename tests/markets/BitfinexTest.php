<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 12:15 PM
 */

require_once('ConfigAccountLoader.php');

class BitfinexTest extends PHPUnit_Framework_TestCase {

    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Bitfinex));
        $this->mkt = $exchanges[Exchange::Bitfinex];
        $this->mkt->init();
    }

    public function testPrecision()
    {
        $this->assertTrue($this->mkt instanceof Bitfinex);
        $pair = CurrencyPair::BTCUSD;
        $this->assertEquals(4, $this->mkt->quotePrecision($pair, 1.0));
        $this->assertEquals(5, $this->mkt->quotePrecision($pair, 0.1));
        $this->assertEquals(1, $this->mkt->quotePrecision($pair, 1000.0));
        $this->assertEquals(-2, $this->mkt->quotePrecision($pair, 1000000.0));
    }

    public function testBalances()
    {
        if($this->mkt instanceof Bitfinex)
        {
            $ret = $this->mkt->balances();

            $this->assertNotEmpty($ret);
        }
    }

    public function testMinOrders()
    {
        $this->assertTrue($this->mkt instanceof Bitfinex);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $ticker = $this->mkt->ticker($pair);
            $quotePrecision = $this->mkt->quotePrecision($pair, $ticker->bid);
            $price = round($ticker->bid * 0.9, $quotePrecision);
            $minOrder = $this->mkt->minimumOrderSize($pair, $price);

            $ret = $this->mkt->buy($pair, $minOrder, $price);
            $this->checkAndCancelOrder($ret);
        }
    }

    public function testBasePrecision()
    {
        $this->assertTrue($this->mkt instanceof Bitfinex);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $ticker = $this->mkt->ticker($pair);
            $quotePrecision = $this->mkt->quotePrecision($pair, $ticker->bid);
            $price = round($ticker->bid * 0.9, $quotePrecision);

            $minOrder = $this->mkt->minimumOrderSize($pair, $price);
            $basePrecision = $this->mkt->basePrecision($pair, $ticker->bid);
            $minOrder += bcpow(10, -1 * $basePrecision, $basePrecision);

            $ret = $this->mkt->buy($pair, $minOrder, $price);
            $this->checkAndCancelOrder($ret);
        }
    }

    public function testBuyOrderSubmission()
    {
        if($this->mkt instanceof Bitfinex)
        {
            $response = $this->mkt->buy(CurrencyPair::BTCUSD, 1, 1);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testSellOrderSubmission()
    {
        if($this->mkt instanceof Bitfinex)
        {
            $response = $this->mkt->sell(CurrencyPair::BTCUSD, 1, 10000);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testMyTrades()
    {
        if($this->mkt instanceof Bitfinex)
        {
            $res = $this->mkt->tradeHistory(50);
            $this->assertNotNull($res);
        }
    }

    public function testPublicTrades()
    {
        if($this->mkt instanceof Bitfinex)
        {
            $res = $this->mkt->trades(CurrencyPair::BTCUSD, time()-60);
            $this->assertNotNull($res);
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue($this->mkt->isOrderAccepted($response));
        $this->assertTrue($this->mkt->isOrderOpen($response));

        $this->assertNotNull($this->mkt->cancel($response['order_id']));
        sleep(1);
        $this->assertFalse($this->mkt->isOrderOpen($response));
    }
}
 
