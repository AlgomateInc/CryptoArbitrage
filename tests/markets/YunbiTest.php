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
