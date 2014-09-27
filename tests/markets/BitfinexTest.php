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

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue($this->bf->isOrderAccepted($response));
        $this->assertTrue($this->bf->isOrderOpen($response));

        $this->assertNotNull($this->bf->cancel($response['order_id']));
        $this->assertFalse($this->bf->isOrderOpen($response));
    }
}
 