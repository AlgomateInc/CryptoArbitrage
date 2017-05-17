<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 11:21 PM
 */

require_once('ConfigAccountLoader.php');

class CryptsyTest extends PHPUnit_Framework_TestCase {

    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::Cryptsy));
        $this->mkt = $exchanges[Exchange::Cryptsy];
    }

    public function testOrderSubmitAndCancel()
    {
        if($this->mkt instanceof Cryptsy)
        {
            $res = $this->mkt->buy(CurrencyPair::LTCBTC, 1, 0.001);

            $this->assertTrue($this->mkt->isOrderAccepted($res));

            $cres = $this->mkt->cancel($res['orderid']);

            $this->assertTrue($cres['success'] == 1);
        }
    }

}
 