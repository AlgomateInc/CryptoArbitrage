<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 11:21 PM
 */

require_once('ConfigAccountLoader.php');
require_once('MongoAccountLoader.php');

class KrakenTest extends PHPUnit_Framework_TestCase {

    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Kraken));
        $this->mkt = $exchanges[Exchange::Kraken];

        if($this->mkt instanceof ILifecycleHandler)
            $this->mkt->init();
    }

    public function testPrecisions()
    {
        $this->assertTrue($this->mkt instanceof Kraken);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $ticker = $this->mkt->ticker($pair);
            $precision = $this->mkt->quotePrecision($pair, $ticker->bid);
            $this->assertEquals($ticker->bid, round($ticker->bid, $precision));
            $this->assertEquals($ticker->ask, round($ticker->ask, $precision));
            $this->assertEquals($ticker->last, round($ticker->last, $precision));
        }
    }

    public function testOrderSubmitAndCancel()
    {
        if($this->mkt instanceof Kraken)
        {
            $res = $this->mkt->sell(CurrencyPair::ETHBTC, 0.1, 1);

            $this->assertTrue($this->mkt->isOrderAccepted($res));

            $this->assertTrue($this->mkt->isOrderOpen($res));

            $cres = $this->mkt->cancel($this->mkt->getOrderID($res));

            $this->assertTrue($cres['count'] == 1);

            $this->assertFalse($this->mkt->isOrderOpen($res));
        }
    }

    public function testOrderSubmitAndExecute()
    {
        if($this->mkt instanceof Kraken)
        {
            $res = $this->mkt->sell(CurrencyPair::ETHBTC, 0.1, 0.001);

            $this->assertTrue($this->mkt->isOrderAccepted($res));

            sleep(1);

            $this->assertFalse($this->mkt->isOrderOpen($res));

            $oe = $this->mkt->getOrderExecutions($res);

            $this->assertTrue(count($oe) > 1);
        }
    }
}
 
