<?php
/**
 * User: jon
 * Date: 1/17/2017
 * Time: 8:00 PM
 */

require_once('ConfigAccountLoader.php');

class GdaxTest extends PHPUnit_Framework_TestCase {
    protected $mkt;

    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Gdax));
        $this->mkt = $exchanges[Exchange::Gdax];

        if ($this->mkt instanceof ILifecycleHandler) {
            $this->mkt->init();
        }
    }

    public function testSupportedPairs()
    {
        $this->assertTrue($this->mkt instanceof Gdax);
        $known_pairs = array("BTCGBP","BTCEUR","ETHUSD","ETHBTC","LTCUSD", "LTCBTC", "BTCUSD");
        foreach ($known_pairs as $pair) {
            $this->assertTrue($this->mkt->supports($pair));
        }
        $known_pairs_slash = array("BTC/GBP","BTC/EUR","ETH/USD","ETH/BTC","LTC/USD", "LTC/BTC", "BTC/USD");
        foreach ($known_pairs_slash as $pair) {
            $this->assertTrue($this->mkt->supports($pair));
        }
    }

    public function testBalances()
    {
        $this->assertTrue($this->mkt instanceof Gdax);
        $currencies = $this->mkt->supportedCurrencies();
        $ret = $this->mkt->balances();
        foreach($ret as $curr=>$amt) {
            $this->assertTrue(in_array($curr, $currencies));
            $this->assertTrue(is_numeric($amt));
        }
        $this->assertNotEmpty($ret);
    }

    public function testBuyOrderSubmission()
    {
        $this->assertTrue($this->mkt instanceof Gdax);
        $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 0.01, 0.01);
        $this->checkAndCancelOrder($ret);
    }

    public function testSellOrderSubmission()
    {
        $this->assertTrue($this->mkt instanceof Gdax);
        $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 0.01, 1000000);
        $this->checkAndCancelOrder($ret);
    }

    public function testMyTrades()
    {
        $this->assertTrue($this->mkt instanceof Gdax);
        $res = $this->mkt->tradeHistory(1000);
        $this->assertNotNull($res);
        // because of pagination, make sure we get over 100
        $this->assertTrue(count($res) > 100);
    }

    public function testExecutions()
    {
        $this->assertTrue($this->mkt instanceof Gdax);
        $ret = $this->mkt->submitMarketOrder('sell', CurrencyPair::BTCUSD, 0.01, 0.01);
        sleep(1);
        $exec = $this->mkt->getOrderExecutions($ret);
        $this->assertTrue(count($exec) > 0);
        $ret = $this->mkt->submitMarketOrder('buy', CurrencyPair::BTCUSD, 0.01, 0.01);
        sleep(1);
        $exec = $this->mkt->getOrderExecutions($ret);
        $this->assertTrue(count($exec) > 0);
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue($this->mkt->isOrderAccepted($response));
        $this->assertTrue($this->mkt->isOrderOpen($response));

        $this->assertNotNull($this->mkt->cancel($response['id']));
        sleep(1);
        $this->assertFalse($this->mkt->isOrderOpen($response));
    }
}
