<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/2/2015
 * Time: 9:57 PM
 */

class ExecutionManager {

    private $activeOrderManager;
    private $exchanges;
    private $reporter;

    private $liveTrade = false;

    public function setLiveTrade($liveTrade)
    {
        $this->liveTrade = $liveTrade;
    }

    function __construct(ActiveOrderManager $activeOrderManager, $exchanges, IReporter $reporter)
    {
        $this->activeOrderManager = $activeOrderManager;
        $this->exchanges = $exchanges;
        $this->reporter = $reporter;
    }

    function execute(Order $o, IStrategy $strategy, $strategyOrderId)
    {
        $market = $this->exchanges[$o->exchange];

        if(!$market instanceof IExchange)
            throw new Exception("Cannot trade on non-market: $o->exchange");

        //abort if this is test only
        if($this->liveTrade != true)
            return true;

        //submit the order to the market
        $marketResponse = null;
        if($o->orderType == OrderType::BUY)
            $marketResponse = $market->buy($o->currencyPair, $o->quantity, $o->limit);
        elseif($o->orderType == OrderType::SELL)
            $marketResponse = $market->sell($o->currencyPair, $o->quantity, $o->limit);
        else
            throw new Exception("Unable to execute order type: $o->orderType");

        //get the order id and add to active list if the order was accepted by the market
        $oid = null;
        $orderAccepted = $market->isOrderAccepted($marketResponse);
        if($orderAccepted){
            $oid = $market->getOrderID($marketResponse);

            $ao = new ActiveOrder();
            $ao->marketObj = $market;
            $ao->marketResponse = $marketResponse;
            $ao->order = $o;
            $ao->strategyId = $strategyOrderId;
            $ao->strategyObj = $strategy;
            $ao->orderId = $oid;

            if($this->activeOrderManager instanceof ActiveOrderManager)
                $this->activeOrderManager->add($ao);
        }

        //record the order and market response
        if($this->reporter instanceof IReporter)
            $this->reporter->order($o->exchange, $o->orderType, $o->quantity, $o->limit,
                $oid, $marketResponse, $strategyOrderId);

        return $orderAccepted;
    }

    function cancel($marketName, $orderId, $strategyId)
    {
        if(!$this->reporter instanceof IReporter)
            throw new Exception('Invalid reporter was passed!');

        $market = $this->exchanges[$marketName];

        if($market instanceof IExchange){
            $marketResponse = $market->cancel($orderId);

            $this->reporter->cancel($strategyId, $orderId, 0, $marketResponse);
        }

    }

    function executeStrategy(IStrategy $strategy, IStrategyOrder $iso)
    {
        if(!$this->reporter instanceof IReporter)
            throw new Exception('Invalid reporter was passed!');

        $strategyOrderId = $this->reporter->strategyOrder($strategy->getStrategyId(),$iso);

        //submit orders to the exchanges
        //and report them as we go
        $orders = $iso->getOrders();
        $orderAcceptState = array();
        foreach($orders as $o)
        {
            if(!$o instanceof Order)
                throw new Exception('Strategy returned invalid order');

            $orderAcceptState[] = $this->execute($o, $strategy, $strategyOrderId);
        }

        //if orders failed, we need to take evasive action
        $allOrdersFailed = true;
        $someOrdersFailed = false;
        foreach($orderAcceptState as $orderAccepted){
            $allOrdersFailed = $allOrdersFailed && !$orderAccepted;
            $someOrdersFailed = $someOrdersFailed || !$orderAccepted;
        }

        //TODO:if just some of the orders failed, we need to correct our position
        //TODO:right now, stop trading
        if($someOrdersFailed){
            Logger::getLogger(get_class($this))->error('Position imbalance! Strategy order entry failed.');
            $this->liveTrade = false;
        }
    }

}