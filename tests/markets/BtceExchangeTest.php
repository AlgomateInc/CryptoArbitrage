<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 9:43 PM
 */

require_once('ConfigAccountLoader.php');

class BtceExchangeTest extends PHPUnit_Framework_TestCase {

    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Btce));
        $this->mkt = $exchanges[Exchange::Btce];
    }

    public function testOrderSubmitAndCancel()
    {
        if($this->mkt instanceof BtceExchange)
        {
            $res = $this->mkt->buy(CurrencyPair::BTCUSD, 1, 1);

            $this->assertTrue($this->mkt->isOrderAccepted($res));

            $cres = $this->mkt->cancel($res['return']['order_id']);

            $this->assertTrue($cres['success'] == 1);
        }
    }

    public function testTradeHistory()
    {
        if($this->mkt instanceof BtceExchange)
        {
            $res = $this->mkt->tradeHistory(5);

            $this->assertTrue(is_array($res));
            $this->assertCount(5, $res);
        }
    }
}
 