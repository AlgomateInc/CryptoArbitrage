<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 11/11/2014
 * Time: 12:03 AM
 */

require_once('IReporter.php');
require_once('IStatisticsGenerator.php');

class MultiReporter implements IReporter, IStatisticsGenerator {

    private $rptList = array();

    public function add(IReporter $rpt)
    {
        $this->rptList[] = $rpt;
    }

    public function count()
    {
        return count($this->rptList);
    }

    //////////////////////////////////////////////////

    public function balance($exchange_name, $currency, $balance)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $rpt->balance($exchange_name,$currency,$balance);
        }
    }

    public function market($exchange_name, $currencyPair, $bid, $ask, $last, $vol)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $rpt->market($exchange_name, $currencyPair, $bid, $ask, $last, $vol);
        }
    }

    public function depth($exchange_name, $currencyPair, OrderBook $depth)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $rpt->depth($exchange_name, $currencyPair, $depth);
        }
    }

    public function trades($exchange_name, $currencyPair, $trades)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $rpt->trades($exchange_name, $currencyPair, $trades);
        }
    }

    public function transaction($exchange_name, $id, $type, $currency, $amount, $timestamp)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $rpt->transaction($exchange_name, $id, $type, $currency, $amount, $timestamp);
        }
    }

    public function arbitrage($quantity, $pair, $buyExchange, $buyLimit, $sellExchange, $sellLimit)
    {
        $ret = null;

        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $r = $rpt->arbitrage($quantity, $pair, $buyExchange, $buyLimit, $sellExchange, $sellLimit);
            if($ret === null)
                $ret = $r;
        }

        return $ret;
    }

    public function strategyOrder($strategyId, $iso)
    {
        $ret = array();

        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $ret[] = array(
                'IReporter' => get_class($rpt),
                'StrategyId' => $rpt->strategyOrder($strategyId, $iso));
        }

        return $ret;
    }

    private function getStrategyOrderIdForReporter($rpt, $strategyIdList)
    {
        //find the right strategyorderid for this reporter (previous call to strategyOrder
        //gave a list, per each reporter)
        if(is_array($strategyIdList))
            foreach($strategyIdList as $rptStrategyInfo){
                if($rptStrategyInfo['IReporter'] == get_class($rpt))
                    return $rptStrategyInfo['StrategyId'];
            }

        //return the argument simply. the user may not have used multireporter at time
        //of generating original strategyorder, so this may be the id itself
        return $strategyIdList;
    }

    public function order($exchange, $type, $quantity, $price, $orderId, $orderResponse, $strategyIdList)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $strategyOrderId = $this->getStrategyOrderIdForReporter($rpt, $strategyIdList);

            $rpt->order($exchange, $type, $quantity, $price, $orderId, $orderResponse, $strategyOrderId);
        }
    }

    public function cancel($strategyIdList, $orderId, $cancelQuantity, $cancelResponse)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $strategyOrderId = $this->getStrategyOrderIdForReporter($rpt, $strategyIdList);

            $rpt->cancel($strategyOrderId, $orderId, $cancelQuantity, $cancelResponse);
        }
   }

    public function execution($strategyIdList, $orderId, $market, $txid, $quantity, $price, $timestamp)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $strategyOrderId = $this->getStrategyOrderIdForReporter($rpt, $strategyIdList);

            $rpt->execution($strategyOrderId, $orderId, $market, $txid, $quantity, $price, $timestamp);
        }
    }

    public function trade($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $rpt->trade($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp);
        }
    }

    public function position($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IReporter)
                throw new Exception('Invalid reporter in multi-reporter');

            $rpt->position($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp);
        }
    }

    public function computeMarketStats()
    {
        $ret = null;

        foreach($this->rptList as $rpt){
            if(!$rpt instanceof IStatisticsGenerator)
                continue;

            $r = $rpt->computeMarketStats();
            if($ret === null)
                $ret = $r;
        }

        return $ret;
    }


}