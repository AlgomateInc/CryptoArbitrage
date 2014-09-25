<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 10:39 PM
 */

require_once('ConfigAccountLoader.php');

class BitstampExchangeTest extends PHPUnit_Framework_TestCase {

    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Bitstamp));
        $this->mkt = $exchanges[Exchange::Bitstamp];
    }

    public function testSubmitAndCancelOrder()
    {
        if($this->mkt instanceof BitstampExchange)
        {
            $response = $this->mkt->buy(CurrencyPair::BTCUSD, 5, 1);

            $this->assertNotNull($response);

            $this->assertTrue($this->mkt->isOrderAccepted($response));

            //give time to bitstamp to put order on book
            sleep(1);

            $this->assertTrue($this->mkt->cancel($response['id']));
        }
    }

}
 