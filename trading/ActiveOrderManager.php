<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/2/2015
 * Time: 9:33 PM
 */

class ActiveOrderManager {

    private $fileName;
    private $activeOrders = array();
    private $exchanges;
    private $reporter;

    function __construct($fileName, $exchanges, $reporter)
    {
        $this->fileName = $fileName;
        $this->exchanges = $exchanges;
        $this->reporter = $reporter;

        $this->loadActiveOrders();
    }

    function add(ActiveOrder $ao)
    {
        $this->activeOrders[] = $ao;
        $this->saveActiveOrders();
    }

    function saveActiveOrders()
    {
        file_put_contents($this->fileName, serialize($this->activeOrders));
    }

    function loadActiveOrders()
    {
        if(!file_exists($this->fileName))
            return;

        $aoFileStr = file_get_contents($this->fileName);
        if($aoFileStr === false)
            return;

        $aoJson = unserialize($aoFileStr);
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

            if(!$market instanceof IExchange)
                continue;

            //check if the order is done
            if($market->isOrderOpen($marketResponse))
            {
                //order is still active. our strategy may want to adjust it
                if($ao->strategyObj instanceof IStrategy)
                    $ao->strategyObj->update($ao);
            }
            else
            {
                //order complete. remove from list and do post-processing
                unset($this->activeOrders[$i]);

                //get the executions on this order and report them
                $execs = $market->getOrderExecutions($marketResponse);
                $oid = $market->getOrderID($marketResponse);
                foreach($execs as $execItem){
                    if($this->reporter instanceof IReporter)
                        $this->reporter->execution(
                            $strategyId,
                            $oid,
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
        $this->saveActiveOrders();
    }

}