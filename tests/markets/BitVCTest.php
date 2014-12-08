<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 1:27 PM
 */

require_once('ConfigAccountLoader.php');

class BitVCTest extends PHPUnit_Framework_TestCase {

    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(Exchange::BitVC));
        $this->mkt = $exchanges[Exchange::BitVC];
    }

    public function testBalances()
    {
        if($this->mkt instanceof BitVC)
        {
            $ret = $this->mkt->balances();

            $this->assertNotEmpty($ret);
        }
    }

    public function testTicker()
    {
        if($this->mkt instanceof BitVC)
        {
            $ret = $this->mkt->ticker(CurrencyPair::BTCCNY);

            $this->assertNotEmpty($ret);
        }
    }

    public function testDepth()
    {
        if($this->mkt instanceof BitVC)
        {
            $ret = $this->mkt->depth(CurrencyPair::BTCCNY);

            $this->assertNotEmpty($ret);
        }
    }
}
 