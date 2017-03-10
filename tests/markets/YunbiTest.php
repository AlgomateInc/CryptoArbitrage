<?php
/**
 * User: jon
 * Date: 1/30/2017
 * Time: 3:00 PM
 */

require_once('ConfigAccountLoader.php');

class YunbiTest extends PHPUnit_Framework_TestCase {
    protected $mkt;

    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Yunbi));
        $this->mkt = $exchanges[Exchange::Yunbi];

        if ($this->mkt instanceof ILifecycleHandler) {
            $this->mkt->init();
        }
    }

    public function testSetup()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $ticker = $this->mkt->ticker(CurrencyPair::BTCCNY);
        $this->assertEquals($ticker->currencyPair, CurrencyPair::BTCCNY);
    }

    public function testSupportedPairs()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $this->assertTrue($this->mkt->supports("1SŦ/CNY"));

        $known_pairs = array("BTCCNY","ETHCNY","DGDCNY","PLSCNY","BTSCNY", 
            "BITCNY/CNY", "DCSCNY", "SC/CNY", "ETCCNY", "1SŦCNY", "REPCNY", 
            "ANSCNY", "ZECCNY", "ZMCCNY", "GNTCNY");
        foreach ($known_pairs as $pair) {
            $this->assertTrue($this->mkt->supports($pair));
        }
        $known_pairs_slash = array("BTC/CNY","ETH/CNY","DGD/CNY","PLS/CNY",
            "BTS/CNY", "DCS/CNY", "ETC/CNY", "1SŦ/CNY", "REP/CNY", 
            "ANS/CNY", "ZEC/CNY", "ZMC/CNY", "GNT/CNY");
        foreach ($known_pairs_slash as $pair) {
            $this->assertTrue($this->mkt->supports($pair));
        }
    }

    public function testPrecisions()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);

        $this->assertEquals(2, $this->mkt->quotePrecision(CurrencyPair::BTCCNY, 1.0));
        $this->assertEquals(3, $this->mkt->quotePrecision(CurrencyPair::BTCCNY, 0.11));
        $this->assertEquals(2, $this->mkt->quotePrecision(CurrencyPair::BTCCNY, 1000.1));
        $this->assertEquals(4, $this->mkt->quotePrecision(CurrencyPair::BTCCNY, 0.0123));
    }

    public function testLivePrecisions()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);

        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $ticker = $this->mkt->ticker($pair);
            $precision = $this->mkt->quotePrecision($pair, $ticker->bid);
            $this->assertEquals($ticker->bid, round($ticker->bid, $precision), "Failure on $ticker->currencyPair");
            $this->assertEquals($ticker->ask, round($ticker->ask, $precision), "Failure on $ticker->currencyPair");
            $this->assertEquals($ticker->last, round($ticker->last, $precision), "Failure on $ticker->currencyPair");
        }
    }

    public function testQuoteLimits()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $ret = $this->mkt->buy("1SŦCNY", 0.01, 0.6021);
    }

    public function testBalances()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $ret = $this->mkt->balances();

        $currencies = $this->mkt->supportedCurrencies();
        foreach($ret as $curr=>$amt) {
            $this->assertTrue(in_array($curr, $currencies));
            $this->assertTrue(is_numeric($amt));
        }
        $this->assertNotEmpty($ret);
    }

    public function testTrades()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);

        $yesterday = time() - 60 * 60 * 24;
        $ret = $this->mkt->trades(CurrencyPair::BTCCNY, $yesterday);
        foreach($ret as $trade) {
            $this->assertEquals($trade->currencyPair, CurrencyPair::BTCCNY);
            $this->assertTrue($trade->timestamp > $yesterday);
        }
    }

    public function testDepth()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $ret = $this->mkt->depth(CurrencyPair::BTCCNY);
        $this->assertNotEmpty($ret);
        // asks should be ascending
        for ($i = 1; $i < count($ret->asks); ++$i) {
            $this->assertGreaterThanOrEqual(floatval($ret->asks[$i-1]->price), floatval($ret->asks[$i]->price));
        }
        // bids should be descending
        for ($i = 1; $i < count($ret->bids); ++$i) {
            $this->assertLessThanOrEqual(floatval($ret->bids[$i-1]->price), floatval($ret->bids[$i]->price));
        }
    }

    public function testBuyOrderSubmission()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $ret = $this->mkt->buy(CurrencyPair::BTCCNY, 0.01, 0.01);
        $this->checkAndCancelOrder($ret);
    }

    public function testSellOrderSubmission()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $ret = $this->mkt->sell(CurrencyPair::BTCCNY, 0.01, 1000000);
        $this->checkAndCancelOrder($ret);
    }

    public function testMyTrades()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $res = $this->mkt->tradeHistory(1000);
        $this->assertNotNull($res);
        // make sure it's correctly ordered
        for ($i = 1; $i < count($res); ++$i) {
            $this->assertTrue($res[$i-1]->timestamp >= $res[$i]->timestamp);
        }
    }

    public function testBTCCNYTrades()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $res = $this->mkt->getTradeHistoryForPair('BTCCNY');
        $this->assertNotNull($res);
        for ($i = 1; $i < count($res); ++$i) {
            $this->assertTrue($res[$i-1]->timestamp >= $res[$i]->timestamp);
        }
    }

    public function testExecutions()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $ret = $this->mkt->sell(CurrencyPair::BTCCNY, 0.01, 0.01);
        sleep(1);
        $exec = $this->mkt->getOrderExecutions($ret);
        $this->assertTrue(count($exec) > 0);
    }

    /* For testing dates in order executions, not always needed */
    /*
    public function testOrderExecution()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        $ret = $this->mkt->getOrderExecutions(array('id'=>404653556));
        var_dump($ret);
    }
     */

    public function test401Error()
    {
        $this->assertTrue($this->mkt instanceof Yunbi);
        for ($i = 0; $i < 10; $i++) {
            $res = $this->mkt->tradeHistory(1000);
            $this->assertNotNull($res);
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue($this->mkt->isOrderAccepted($response));
        $this->assertTrue($this->mkt->isOrderOpen($response));

        $this->assertNotNull($this->mkt->cancel($response['id']));
        $this->assertFalse($this->mkt->isOrderOpen($response));
    }

}
