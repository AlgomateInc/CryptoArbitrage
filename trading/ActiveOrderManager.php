<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/2/2015
 * Time: 9:33 PM
 */

use CryptoArbitrage\Reporting\IReporter;

use CryptoMarket\Exchange\IExchange;
use CryptoMarket\Record\ActiveOrder;
use CryptoMarket\Record\Order;
use CryptoMarket\Record\OrderExecution;

require_once('ConcurrentFile.php');

class ActiveOrderManager {
    private $logger;

    private $dataStore;

    private $activeOrders = array();
    private $exchanges;
    private $reporter;

    function __construct($fileName, &$exchanges, $reporter)
    {
        $this->logger = Logger::getLogger(get_class($this));

        $this->exchanges = $exchanges;
        $this->reporter = $reporter;

        $this->dataStore = new ConcurrentFile($fileName);

        $this->loadActiveOrders();
    }

    function getCount()
    {
        return count($this->activeOrders);
    }

    function isStrategyActive($strategyId)
    {
        for($i = 0;$i < count($this->activeOrders);$i++) {
            $ao = $this->activeOrders[$i];
            if ($ao instanceof ActiveOrder && $ao->strategyId == $strategyId)
                return true;
        }

        return false;
    }

    /**
     * Returns the list of currently active orders that are associated with inactive strategies
     * @param array $activeStrategyIdList The list of active strategy IDs
     * @return array Returns an array of ActiveOrder
     */
    function getInactiveStrategyOrders(array $activeStrategyIdList)
    {
        $ret = array();

        for($i = 0;$i < count($this->activeOrders);$i++) {
            $ao = $this->activeOrders[$i];
            if ($ao instanceof ActiveOrder && !in_array($ao->strategyId, $activeStrategyIdList))
                $ret[] = $ao;
        }

        return $ret;
    }

    /**
     * Checks if the provided strategy has any active orders. If there are any active orders
     * then the strategy is asked to update itself.
     * @param IStrategy $strategy
     * @return array|null Returns an array of IStrategyOrder updates if the strategy had active orders.
     * This array may be empty, if the strategy chooses not to update. Returns null when there are
     * no active orders for the given strategy.
     */
    function updateActiveStrategy(IStrategy $strategy)
    {
        $updated = false;
        $newStrategyOrderList = array();

        for($i = 0;$i < count($this->activeOrders);$i++) {
            $ao = $this->activeOrders[$i];
            if ($ao instanceof ActiveOrder && $ao->strategyId == $strategy->getStrategyId())
            {
                $updated = true;

                $newIso = $strategy->update($ao);
                if($newIso instanceof IStrategyOrder)
                    $newStrategyOrderList[] = $newIso;
            }
        }

        if(!$updated)
            return null;

        return $newStrategyOrderList;
    }

    function add(ActiveOrder $ao)
    {
        $this->activeOrders[] = $ao;
        $this->saveActiveOrders();
    }

    function saveActiveOrders()
    {
        $this->dataStore->write($this->activeOrders);
    }

    function loadActiveOrders()
    {
        $aoJson = $this->dataStore->read();
        if($aoJson === null)
            return;

        //we have our active list. update it and fix our market references
        $aoCount = count($aoJson);
        for($i = 0;$i < $aoCount;$i++) {
            $ao = $aoJson[$i];
            if ($ao instanceof ActiveOrder && $ao->order instanceof Order) {
                $ao->marketObj = null;
                if (isset($this->exchanges[$ao->order->exchange]))
                    $ao->marketObj = $this->exchanges[$ao->order->exchange];
            }
            else
                unset($aoJson[$i]);
        }

        $this->activeOrders = array_values($aoJson);
    }

    function processActiveOrders()
    {
        $this->dataStore->lock(false);
        try{
            $this->loadActiveOrders();

            $this->process();

            $this->saveActiveOrders();
        }catch (\Exception $e){
            $this->logger->error('Problem processing active orders: ' . $e->getMessage());
        }
        $this->dataStore->unlock();
    }

    private function process()
    {
        $aoCount = count($this->activeOrders);
        for($i = 0;$i < $aoCount;$i++)
        {
            $ao = $this->activeOrders[$i];
            if(!$ao instanceof ActiveOrder){
                unset($this->activeOrders[$i]);
                continue;
            }

            $market = $ao->marketObj;
            $marketResponse = $ao->marketResponse;
            $strategyOrderId = $ao->strategyOrderId;
            $orderId = $ao->orderId;

            if(!$market instanceof IExchange) {
                if($this->reporter instanceof IReporter)
                    $this->reporter->orderMessage($strategyOrderId,
                        $orderId, 'MarketUnavailable', 'Market for order is not available');
                continue;
            }

            //check if the order is done
            if(!$market->isOrderOpen($marketResponse))
            {
                //order complete. remove from list and do post-processing
                unset($this->activeOrders[$i]);
            }

            //get the executions on this order and report them
            $execs = $market->getOrderExecutions($marketResponse);
            foreach($execs as $execItem){
                if(!$execItem instanceof OrderExecution){
                    $this->logger->warn('Execution returned from market, wrong type.');
                    continue;
                }

                if(!array_key_exists($execItem->txid, $ao->executions)) {

                    $ao->executions[$execItem->txid] = $execItem;

                    if ($this->reporter instanceof IReporter)
                        $this->reporter->execution(
                            $strategyOrderId,
                            $orderId,
                            $market->Name(),
                            $execItem->txid,
                            $execItem->quantity,
                            $execItem->price,
                            $execItem->timestamp
                        );
                }
            }
        }

        //we may have removed some orders with unset(). fix indices
        $this->activeOrders = array_values($this->activeOrders);
    }

}
