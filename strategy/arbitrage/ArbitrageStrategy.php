<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 10/4/2014
 * Time: 9:34 AM
 */

require_once('ArbitrageOrder.php');
require_once(__DIR__ . '/../IStrategy.php');
require_once(__DIR__ . '/../../arbinstructions/ConfigArbInstructionLoader.php');

class ArbitrageStrategy implements IStrategy {

    public static $depth = array();

    public function run($instructions, $markets, $balances)
    {
        $arbLoader = new SingleArbInstructionLoader($instructions);
        $inst = $arbLoader->load();

        //////////////////////////////////////////
        // Check the necessary markets exist and support trading for pair
        //////////////////////////////////////////
        if(!array_key_exists($inst->buyExchange, $markets) || !array_key_exists($inst->sellExchange, $markets)){
            syslog(LOG_WARNING, 'Markets in arbitrage instructions not supported');
            return null;
        }

        $buyMarket = $markets[$inst->buyExchange];
        $sellMarket = $markets[$inst->sellExchange];
        if(!($buyMarket instanceof IExchange && $sellMarket instanceof IExchange))
            return null;

        if(!($buyMarket->supports($inst->currencyPair) && $sellMarket->supports($inst->currencyPair))){
            syslog(LOG_WARNING, 'Markets in arbitrage instructions do not support pair: ' . $inst->currencyPair);
            return null;
        }

        /////////////////////////////////////////////////////////
        // Get the depth for the necessary markets
        // check if we fetched it already. if not, get it
        /////////////////////////////////////////////////////////
        if(!array_key_exists($inst->buyExchange, static::$depth) ||
            !array_key_exists($inst->currencyPair, static::$depth[$inst->buyExchange])){
            static::$depth[$inst->buyExchange][$inst->currencyPair] = $buyMarket->depth($inst->currencyPair);
        }

        if(!array_key_exists($inst->sellExchange, static::$depth) ||
            !array_key_exists($inst->currencyPair, static::$depth[$inst->sellExchange])){
            static::$depth[$inst->sellExchange][$inst->currencyPair] = $sellMarket->depth($inst->currencyPair);;
        }

        $buyDepth = static::$depth[$inst->buyExchange][$inst->currencyPair];
        $sellDepth = static::$depth[$inst->sellExchange][$inst->currencyPair];
        if(!($buyDepth instanceof OrderBook && $sellDepth instanceof OrderBook)){
            syslog(LOG_WARNING, 'Markets returned depth in wrong format: ' .
                $inst->buyExchange . ',' . $inst->sellExchange);
            return null;
        }

        ////////////////////////////////////////////////////////////////
        // Run through all factors on arbitrage instructions and find execution candidates
        ////////////////////////////////////////////////////////////////
        $arbOrderList = array();
        foreach($inst->arbExecutionFactorList as $fctr)
        {
            //calculate optimal order
            $arbOrder = null;
            if($inst->buySideRole == TradingRole::Taker && $inst->sellSideRole == TradingRole::Taker)
                $arbOrder = $this->getOptimalTakerOrder($buyDepth->asks, $sellDepth->bids, $fctr->targetSpreadPct);
            elseif($inst->buySideRole == TradingRole::Maker && $inst->sellSideRole == TradingRole::Maker)
                $arbOrder = $this->getOptimalMakerOrder($buyDepth->bids, $sellDepth->asks, $fctr->targetSpreadPct);
            else
                throw new Exception('Unsupported trading role combination in instructions');

            //once we find an order that can be placed, we queue it up
            if($arbOrder->quantity > 0){
                $arbOrder->currencyPair = $inst->currencyPair;
                $arbOrder->buyExchange = $inst->buyExchange;
                $arbOrder->sellExchange = $inst->sellExchange;
                $arbOrder->executionQuantity = $arbOrder->quantity;

                //reduce the quantity to be executed by our factors
                $arbOrder->executionQuantity = $this->floorp($arbOrder->executionQuantity * $fctr->orderSizeScaling,8);

                //adjust order size based on current dollar limits
                if($arbOrder->executionQuantity * $arbOrder->buyLimit > $fctr->maxUsdOrderSize)
                    $arbOrder->executionQuantity = $this->floorp($fctr->maxUsdOrderSize/$arbOrder->buyLimit, 8);

                //adjust order size based on available balances
                $arbOrder->executionQuantity = min(
                    $arbOrder->executionQuantity,
                    $balances[$arbOrder->sellExchange][CurrencyPair::Base($arbOrder->currencyPair)]);

                $quoteBalance = $balances[$arbOrder->buyExchange][CurrencyPair::Quote($arbOrder->currencyPair)];
                if($quoteBalance < $arbOrder->executionQuantity * $arbOrder->buyLimit)
                    $arbOrder->executionQuantity = $this->floorp($quoteBalance / $arbOrder->buyLimit, 8);

                /////////////////////////
                $arbOrderList[] = $arbOrder;
            }
        }

        //select the target order by picking the one with the largest executable quantity
        $ior = null;
        foreach($arbOrderList as $ao){
            if($ior == null || $ao->executionQuantity > $ior->executionQuantity)
                $ior = $ao;
        }

        //send the order for execution on the market if it meets minimum size
        //TODO: remove hardcoding of minimum size
        if($ior instanceof ArbitrageOrder && $ior->executionQuantity > 0.01)
            return $ior;

        return null;
    }

    function getOptimalMakerOrder($buyDepth, $sellDepth, $targetSpreadPct)
    {
        $order = new ArbitrageOrder();

        if(count($buyDepth) > 0 && count($sellDepth) > 0){
            $insideBid = $buyDepth[0];
            $insideAsk = $sellDepth[0];

            if($insideBid instanceof DepthItem && $insideAsk instanceof DepthItem){

                //we're going to adjust the orders so that they are non-executable
                //but placed in the thinnest part of the book around the inside bid/ask
                $fullDepth = array_merge(array_reverse($buyDepth), $sellDepth);

                $minVolume = INF;
                $minVolBid = 0;
                $minVolAsk = 0;

                $halfSpread = $targetSpreadPct / 100.0 / 2.0;
                $startMidPoint = $insideBid->price * (1.0 - $halfSpread);
                $endMidPoint = $insideAsk->price * (1.0 + $halfSpread);
                for($mid = $startMidPoint; $mid <= $endMidPoint; $mid += ($endMidPoint - $startMidPoint)/100.0){

                    //TODO: rounding in this calculation has to be to number of decimals in quote currency (default USD, i.e 2)
                    $lowPx = $this->floorp($mid * (1 - $halfSpread), 2);
                    $highPx = $this->floorp($mid * (1 + $halfSpread), 2);
                    $vol = $this->getVolumeInPriceRange($lowPx, $highPx, $fullDepth);

                    if($vol < $minVolume){
                        $minVolume = $vol;
                        $minVolBid = $lowPx;
                        $minVolAsk = $highPx;
                    }
                }

                //set order limits and size
                //size is set to INF so we hit the quote size limits
                $order->buyLimit = $minVolBid;
                $order->sellLimit = $minVolAsk;
                $order->quantity = ($minVolume == 0)? INF : $minVolume;

            }

        }

        return $order;
    }

    function getVolumeInPriceRange($lowPx, $highPx, $depth)
    {
        $vol = 0;

        foreach($depth as $d)
        {
            if(!$d instanceof DepthItem)
                throw new Exception('Invalid depth type for volume calculation');

            if($d->price >= $lowPx && $d->price <= $highPx)
                $vol += $d->quantity;
        }

        return $vol;
    }

    function getOptimalTakerOrder($buy_depth, $sell_depth, $target_spread_pct)
    {
        $asks = $this->computeDepthStats($buy_depth);
        $bids = $this->computeDepthStats($sell_depth);

        //calculate order size and limits
        $order = new ArbitrageOrder();

        foreach($asks as $askItem){

            if(!$askItem instanceof DepthItem)
                throw new Exception('Invalid order book depth item type');

            $buyPx = $askItem->price;
            $buyQty = $askItem->quantity;

            //check our wavg stat for exit condition
            $buyDiffSum = $askItem->stats[1];
            if($buyDiffSum > 1)
                return $order;

            foreach($bids as $bidItem){

                if(!$bidItem instanceof DepthItem)
                    throw new Exception('Invalid order book depth item type');

                $sellPx = $bidItem->price;
                $sellQty = $bidItem->quantity;

                //check our wavg stat for exit condition
                $sellDiffSum = $bidItem->stats[1];
                if($sellDiffSum > 1)
                    return $order;

                //execute if we are still within the spread target
                if($buyPx * (1 + $target_spread_pct/100) < $sellPx){
                    $execSize = min($buyQty, $sellQty);

                    //update leftover order sizes
                    $askItem->quantity -= $execSize;
                    $buyQty = $askItem->quantity;
                    $bidItem->quantity -= $execSize;

                    //update order limits and size
                    $order->buyLimit = $buyPx;
                    $order->sellLimit = $sellPx;
                    $order->quantity += $execSize;
                    $order->quantity = $this->floorp($order->quantity, 8);
                }

                //if we ran out of buy quantity, exit the sell-side loop
                if($buyQty <= 0)
                    break;
            }
        }

        return $order;
    }

    function computeDepthStats($depth){

        $qtySum = 0; //running depth quantity
        $pxqtySum = 0; //price times quantity, running total
        $wavgDiffSum = 0; //wavg px minus px, running total

        foreach($depth as $item){
            if(!$item instanceof DepthItem)
                throw new Exception('Order book depth not the right type');

            $px = $item->price;
            $qty = $item->quantity;

            //update our running sums
            $qtySum += $qty;
            $pxqtySum += $px*$qty;

            //get our wavg px and update pxdiff sum
            $wavgPx = $pxqtySum/$qtySum;
            $wavgDiffSum += abs($wavgPx - $px);

            //add depth stats to depth array
            $item->stats[] = $wavgPx;
            $item->stats[] = $wavgDiffSum;
        }

        return $depth;
    }

    function floorp($val, $precision)
    {
        $mult = pow(10, $precision);
        return floor($val * $mult) / $mult;
    }

} 