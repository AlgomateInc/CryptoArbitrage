<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 10:39 PM
 */

require_once('ConfigAccountLoader.php');

class BitstampTest extends PHPUnit_Framework_TestCase {

    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Bitstamp));
        $this->mkt = $exchanges[Exchange::Bitstamp];
        $this->mkt->init();
    }

    public function testDepth()
    {
        $depth = $this->mkt->depth(CurrencyPair::XRPEUR);
        $this->assertNotEmpty($depth);
    }

    public function testTicker()
    {
        $ticker = $this->mkt->ticker(CurrencyPair::XRPEUR);
        $this->assertNotEmpty($ticker);
        $this->assertTrue($ticker instanceof Ticker);
    }

    public function testBalances()
    {
        $balances = $this->mkt->balances();
        $currs = $this->mkt->supportedCurrencies();
        foreach ($currs as $curr) {
            $this->assertArrayHasKey($curr, $balances);
        }
    }

    public function testBTCUSDOrder()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $response = $this->mkt->sell(CurrencyPair::BTCUSD, 0.01, 100000);
        $this->testAndCancelOrder($response);
    }

    public function testBTCEUROrder()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $response = $this->mkt->sell(CurrencyPair::BTCEUR, 0.01, 100000);
        $this->testAndCancelOrder($response);
    }

    public function testActiveOrders()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $response = $this->mkt->sell(CurrencyPair::BTCEUR, 0.01, 100000);
        $this->assertNotEmpty($this->mkt->activeOrders());
        $this->assertTrue($this->mkt->isOrderOpen($response));
        $this->testAndCancelOrder($response);
    }

    public function testOrderExecutions()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $response = $this->mkt->buy(CurrencyPair::BTCEUR, 0.01, 100000);
        sleep(1);
        $exs = $this->mkt->getOrderExecutions($response);
        $this->assertNotEmpty($exs);
        $response = $this->mkt->sell(CurrencyPair::BTCEUR, 0.01, 1);
        sleep(1);
        $exs = $this->mkt->getOrderExecutions($response);
        $this->assertNotEmpty($exs);
    }

    public function testTransactions()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $transactions = $this->mkt->transactions();
        $this->assertNotEmpty($transactions);
        foreach ($transactions as $trans) {
            $this->assertEquals("Bitstamp", $trans->exchange);
            $this->assertTrue($trans instanceof Transaction);
            $this->assertTrue($trans->isValid());
        }
    }

    public function testTradeHistory()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $history = $this->mkt->tradeHistory();
        foreach ($history as $trade) {
            $this->assertEquals("Bitstamp", $trade->exchange);
            $this->assertTrue($trade instanceof Trade);
            $this->assertTrue($trade->isValid());
        }
    }

    private function testAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue($this->mkt->isOrderAccepted($response));

        //give time to bitstamp to put order on book
        sleep(1);

        $this->assertTrue($this->mkt->cancel($response['id']));
    }
}
 
