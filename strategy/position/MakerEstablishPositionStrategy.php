<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 3/18/2015
 * Time: 10:47 PM
 */

require_once(__DIR__ . '/../BaseStrategy.php');
require_once(__DIR__ . '/../GenericStrategyOrder.php');
require_once('InsideOrderInstructions.php');
require_once('LimitOrderInstructions.php');

/**
 * Class MakerEstablishPositionInstructions
 *
 * This class establishes positions by entering limit orders close to the inside of the bid/ask.
 * The objective is to enter a position without incurring fees and moving the market
 */
class MakerEstablishPositionStrategy extends BaseStrategy {

    public function run($instructions, $markets, $balances)
    {
        $soi = new InsideOrderInstructions();
        $soi->load($instructions);

        $market = $this->findMarket($markets, $soi->exchange, $soi->currencyPair);
        if (!($market instanceof IExchange))
            return null;

        $depth = $market->depth($soi->currencyPair);
        if (!($depth instanceof OrderBook))
            return null;

        if (count($depth->bids) > 0 && count($depth->asks) > 0) {
            $insideBid = $depth->bids[0];
            $insideAsk = $depth->asks[0];

            if ($insideBid instanceof DepthItem && $insideAsk instanceof DepthItem) {
                //default to midpoint price
                $tgtPrice = Currency::FloorValue(($insideAsk->price + $insideBid->price) / 2.0,
                    CurrencyPair::Quote($soi->currencyPair));

                if($soi->pegOrder){
                    //check to see if we can get a minsize that we could work with to peg the order
                    $minCurSize = Currency::GetMinimumValue(CurrencyPair::Quote($soi->currencyPair));
                    $minMarketSize = pow(10, -max($this->numberOfDecimals($insideBid->price),
                        $this->numberOfDecimals($insideAsk->price)));
                    $minSize = max($minCurSize, $minMarketSize);

                    if($minSize > 0 && $minSize < $insideAsk->price - $insideBid->price)
                    {
                        if($soi->type == OrderType::BUY && $insideBid->price + $minSize < $tgtPrice)
                            $tgtPrice = $insideBid->price + $minSize;
                        if($soi->type == OrderType::SELL && $insideAsk->price - $minSize > $tgtPrice)
                            $tgtPrice = $insideAsk->price - $minSize;

                        $tgtPrice = strval(Currency::FloorValue($tgtPrice, CurrencyPair::Quote($soi->currencyPair)));
                    }
                }

                //proceed forming the order if the calculated price is inside the book
                if($tgtPrice > $insideBid->price && $tgtPrice < $insideAsk->price){
                    $instructions['Price'] = $tgtPrice;
                    $ret = new LimitOrderInstructions();
                    $ret->load($instructions);

                    //adjust the size based on our target window
                    //this is so we don't keep putting the same size orders on the book
                    $windowSize = $soi->sizeRangePct / 100.0 * $soi->size;
                    $sizeAdjustment = lcg_value() * $windowSize - $windowSize / 2.0;
                    $ret->size = Currency::FloorValue($ret->size + $sizeAdjustment, CurrencyPair::Base($soi->currencyPair));

                    //check the size against current balance
                    $neededCurrency = ($ret->type == OrderType::BUY)? CurrencyPair::Quote($soi->currencyPair) :
                        CurrencyPair::Base($soi->currencyPair);
                    $neededCurrencyBalance = 0;
                    if(isset($balances[$soi->exchange][$neededCurrency]))
                        $neededCurrencyBalance = $balances[$soi->exchange][$neededCurrency];
                    if($neededCurrencyBalance <= 0)
                        return null;
                    $ret->size = min($ret->size, $neededCurrencyBalance);

                    //check our order against any trigger price
                    if(isset($ret->triggerPrice)){
                        if($ret->type == OrderType::BUY && $ret->price > $ret->triggerPrice)
                            return null;
                        if($ret->type == OrderType::SELL && $ret->price < $ret->triggerPrice)
                            return null;
                    }

                    //check for stop price
                    if(isset($ret->stopPrice)){
                        if($ret->type == OrderType::BUY && $ret->price < $ret->stopPrice)
                            return null;
                        if($ret->type == OrderType::SELL && $ret->price > $ret->stopPrice)
                            return null;
                    }

                    return $ret;
                }
            }
        }

        return null;
    }

    public function update(ActiveOrder $ao)
    {
        $market = $ao->marketObj;
        $order = $ao->order;
        if(!($market instanceof IExchange && $order instanceof Order))
            return null;

        //check if the order is still open. if not, we're done
        if(!$market->isOrderOpen($ao->marketResponse))
            return null;

        //get the depth to see if we are still inside the bid/ask
        $depth = $market->depth($order->currencyPair);
        if (!($depth instanceof OrderBook))
            return null;

        $bid = $this->getInsideBookPrice($depth, OrderType::BUY);
        $ask = $this->getInsideBookPrice($depth, OrderType::SELL);
        if($bid == null || $ask == null)
            return null;

        if($order->limit > $bid && $order->limit < $ask)
            return null;

        //if the market moved away from our order far enough then we want to reconsider
        //for now we'll say that the order is far if it has 10 times the quantity in front of it
        $cancelThreshold = 10.0;
        $vol = $depth->volumeToPrice($order->limit);
        if($order->quantity * $cancelThreshold < $vol){
            $gso = new GenericStrategyOrder();
            $gso->addCancel(new OrderCancel($market->getOrderID($ao->marketResponse),
                $market->Name(), $ao->strategyOrderId));
            return $gso;
        }

        return null;
    }

    private function getInsideBookPrice(OrderBook $depth, $bookSide){
        if (count($depth->bids) > 0 && count($depth->asks) > 0) {
            $insideBid = $depth->bids[0];
            $insideAsk = $depth->asks[0];

            if ($insideBid instanceof DepthItem && $insideAsk instanceof DepthItem) {
                switch($bookSide){
                    case OrderType::BUY:
                        return $insideBid->price;
                    case OrderType::SELL:
                        return $insideAsk->price;
                }
            }
        }

        return null;
    }

    function numberOfDecimals($value)
    {
        if ((int)$value == $value)
        {
            return 0;
        }

        return strlen($value) - strrpos($value, '.') - 1;
    }
}