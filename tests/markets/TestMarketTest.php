<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/23/2015
 * Time: 3:11 PM
 */

require_once(__DIR__ . '/../../markets/TestMarket.php');
require_once(__DIR__ . '/../../common.php');

class TestMarketTest extends PHPUnit_Framework_TestCase {

    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $this->mkt = new TestMarket(true);
    }

    public function testEnterOrder()
    {
        if($this->mkt instanceof TestMarket)
        {
            $origCount = count($this->mkt->depth(CurrencyPair::BTCUSD)->bids);

            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 10);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals($origCount + 1, count($finalDepth->bids));
        }
    }

    public function testInsertBuyOrder()
    {
        if($this->mkt instanceof TestMarket)
        {
            $origCount = count($this->mkt->depth(CurrencyPair::BTCUSD)->bids);

            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 20);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 15);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals($origCount + 3, count($finalDepth->bids));

            $this->assertEquals(20, $finalDepth->bids[0]->price);
            $this->assertEquals(15, $finalDepth->bids[1]->price);
            $this->assertEquals(10, $finalDepth->bids[2]->price);
        }
    }

    public function testInsertSellOrder()
    {
        if($this->mkt instanceof TestMarket)
        {
            $origCount = count($this->mkt->depth(CurrencyPair::BTCUSD)->bids);

            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 20);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 15);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals($origCount + 3, count($finalDepth->asks));

            $this->assertEquals(10, $finalDepth->asks[0]->price);
            $this->assertEquals(15, $finalDepth->asks[1]->price);
            $this->assertEquals(20, $finalDepth->asks[2]->price);
        }
    }

    public function testBuyCrossOrder()
    {
        if($this->mkt instanceof TestMarket)
        {
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals(0, count($finalDepth->bids) + count($finalDepth->asks));

            $trades = $this->mkt->trades(CurrencyPair::BTCUSD, 0);

            $this->assertEquals(1, count($trades));
            $this->assertEquals(10, $trades[0]->price);
            $this->assertEquals(10, $trades[0]->quantity);
        }
    }

    public function testSellCrossOrder()
    {
        if($this->mkt instanceof TestMarket)
        {
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals(0, count($finalDepth->bids) + count($finalDepth->asks));

            $trades = $this->mkt->trades(CurrencyPair::BTCUSD, 0);

            $this->assertEquals(1, count($trades));
            $this->assertEquals(10, $trades[0]->price);
            $this->assertEquals(10, $trades[0]->quantity);
        }
    }

    public function testCrossOrderPartialFill()
    {
        if($this->mkt instanceof TestMarket)
        {
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 5, 10);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals(1, count($finalDepth->bids) + count($finalDepth->asks));

            $this->assertEquals(10, $finalDepth->bids[0]->price);
            $this->assertEquals(5, $finalDepth->bids[0]->quantity);

            $trades = $this->mkt->trades(CurrencyPair::BTCUSD, 0);

            $this->assertEquals(1, count($trades));
            $this->assertEquals(10, $trades[0]->price);
            $this->assertEquals(5, $trades[0]->quantity);
        }
    }

    public function testSellCrossOrderMultiplePartialFill()
    {
        if($this->mkt instanceof TestMarket)
        {
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 9);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 8);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 22, 5);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals(1, count($finalDepth->bids) + count($finalDepth->asks));

            $this->assertEquals(8, $finalDepth->bids[0]->price);
            $this->assertEquals(8, $finalDepth->bids[0]->quantity);

            $trades = $this->mkt->trades(CurrencyPair::BTCUSD, 0);

            $this->assertEquals(3, count($trades));
            $this->assertEquals(10, $trades[0]->price);
            $this->assertEquals(10, $trades[0]->quantity);
            $this->assertEquals(9, $trades[1]->price);
            $this->assertEquals(10, $trades[1]->quantity);
            $this->assertEquals(8, $trades[2]->price);
            $this->assertEquals(2, $trades[2]->quantity);
        }
    }

    public function testBuyCrossOrderMultiplePartialFill()
    {
        if($this->mkt instanceof TestMarket)
        {
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 9);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 8);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 22, 15);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals(1, count($finalDepth->bids) + count($finalDepth->asks));

            $this->assertEquals(10, $finalDepth->asks[0]->price);
            $this->assertEquals(8, $finalDepth->asks[0]->quantity);

            $trades = $this->mkt->trades(CurrencyPair::BTCUSD, 0);

            $this->assertEquals(3, count($trades));
            $this->assertEquals(8, $trades[0]->price);
            $this->assertEquals(10, $trades[0]->quantity);
            $this->assertEquals(9, $trades[1]->price);
            $this->assertEquals(10, $trades[1]->quantity);
            $this->assertEquals(10, $trades[2]->price);
            $this->assertEquals(2, $trades[2]->quantity);
        }
    }

    public function testBuyCrossOrderPartialFillWithLeftover()
    {
        if($this->mkt instanceof TestMarket)
        {
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 15);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 11);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 22, 12);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals(2, count($finalDepth->bids) + count($finalDepth->asks));

            $this->assertEquals(12, $finalDepth->bids[0]->price);
            $this->assertEquals(2, $finalDepth->bids[0]->quantity);
            $this->assertEquals(15, $finalDepth->asks[0]->price);
            $this->assertEquals(10, $finalDepth->asks[0]->quantity);

            $trades = $this->mkt->trades(CurrencyPair::BTCUSD, 0);

            $this->assertEquals(2, count($trades));
            $this->assertEquals(10, $trades[0]->price);
            $this->assertEquals(10, $trades[0]->quantity);
            $this->assertEquals(11, $trades[1]->price);
            $this->assertEquals(10, $trades[1]->quantity);
        }
    }

    public function testSellCrossOrderPartialFillWithLeftover()
    {
        if($this->mkt instanceof TestMarket)
        {
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 15);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 14);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 22, 12);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);

            $this->assertNotEmpty($ret);
            $this->assertEquals(2, count($finalDepth->bids) + count($finalDepth->asks));

            $this->assertEquals(10, $finalDepth->bids[0]->price);
            $this->assertEquals(10, $finalDepth->bids[0]->quantity);
            $this->assertEquals(12, $finalDepth->asks[0]->price);
            $this->assertEquals(2, $finalDepth->asks[0]->quantity);

            $trades = $this->mkt->trades(CurrencyPair::BTCUSD, 0);

            $this->assertEquals(2, count($trades));
            $this->assertEquals(15, $trades[0]->price);
            $this->assertEquals(10, $trades[0]->quantity);
            $this->assertEquals(14, $trades[1]->price);
            $this->assertEquals(10, $trades[1]->quantity);
        }
    }

    public function testCrossMarketVisibility()
    {
        if($this->mkt instanceof TestMarket) {
            $otherMarket = new TestMarket(false);

            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);
            $this->assertEquals(1, count($finalDepth->bids) + count($finalDepth->asks));

            $otherMarketDepth = $otherMarket->depth(CurrencyPair::BTCUSD);
            $this->assertEquals(1, count($otherMarketDepth->bids) + count($otherMarketDepth->asks));
        }
    }

    public function testCrossMarketVisibilityTrades()
    {
        if($this->mkt instanceof TestMarket) {
            $otherMarket = new TestMarket(false);

            $ret = $this->mkt->buy(CurrencyPair::BTCUSD, 10, 10);
            $this->assertNotEmpty($ret);
            $ret = $this->mkt->sell(CurrencyPair::BTCUSD, 5, 10);
            $this->assertNotEmpty($ret);

            $finalDepth = $this->mkt->depth(CurrencyPair::BTCUSD);
            $this->assertEquals(1, count($finalDepth->bids) + count($finalDepth->asks));

            $this->assertEquals(10, $finalDepth->bids[0]->price);
            $this->assertEquals(5, $finalDepth->bids[0]->quantity);

            $trades = $this->mkt->trades(CurrencyPair::BTCUSD, 0);

            $this->assertEquals(1, count($trades));
            $this->assertEquals(10, $trades[0]->price);
            $this->assertEquals(5, $trades[0]->quantity);

            $otherMarketDepth = $otherMarket->depth(CurrencyPair::BTCUSD);
            $this->assertEquals(1, count($otherMarketDepth->bids) + count($otherMarketDepth->asks));

            $this->assertEquals(10, $otherMarketDepth->bids[0]->price);
            $this->assertEquals(5, $otherMarketDepth->bids[0]->quantity);

            $otherTrades = $otherMarket->trades(CurrencyPair::BTCUSD, 0);

            $this->assertEquals(1, count($otherTrades));
            $this->assertEquals(10, $otherTrades[0]->price);
            $this->assertEquals(5, $otherTrades[0]->quantity);
        }
    }
}