<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/2/2015
 * Time: 9:33 PM
 */

class ActiveOrderManager {
    private $logger;

    private $dataStore;

    private $activeOrders = array();
    private $exchanges;
    private $reporter;

    function __construct($fileName, $exchanges, $reporter)
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
            if ($ao instanceof ActiveOrder && $ao->order instanceof Order)
                $ao->marketObj = $this->exchanges[$ao->order->exchange];
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
        }catch (Exception $e){
            $this->logger->error('Problem processing active orders!', $e);
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
            $strategyId = $ao->strategyId;
            $orderId = $ao->orderId;

            if(!$market instanceof IExchange)
                continue;

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
                            $strategyId,
                            $orderId,
                            $market->Name(),
                            $execItem->txid,
                            $execItem->quantity,
                            $execItem->price,
                            $execItem->timestamp
                        );
                }
            }

            //order is still active. our strategy may want to adjust it
            if($ao->strategyObj instanceof IStrategy)
                $ao->strategyObj->update($ao);

            //check if the order is done
            if(!$market->isOrderOpen($marketResponse))
            {
                //order complete. remove from list and do post-processing
                unset($this->activeOrders[$i]);
            }
        }

        //we may have removed some orders with unset(). fix indices
        $this->activeOrders = array_values($this->activeOrders);
    }

}