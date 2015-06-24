<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/2/2015
 * Time: 10:12 PM
 */

require_once('BaseExchange.php');
require_once('NonceFactory.php');
require_once(__DIR__ . '/../OrderExecution.php');
require_once(__DIR__ . '/../ConcurrentFile.php');

class OrderDepthItem extends DepthItem
{
    public $orderId;
}

class TestMarket extends BaseExchange
{
    private $logger;

    private $tradeList = array();
    private $book;
    private $orderExecutionLookup = array();

    private $dataStore;

    function __construct($clearBook = true)
    {
        $this->logger = Logger::getLogger(get_class($this));
        $this->book = new OrderBook();

        $sharedFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'TestMarketOrderBook';
        $this->dataStore = new ConcurrentFile($sharedFileName);

        //initialize the file or ourselves
        if($clearBook == true)
            $this->save();
        else
            $this->load();
    }

    function load()
    {
        $data = $this->dataStore->read();
        if($data == null)
            return;

        $this->book = $data[0];
        $this->orderExecutionLookup = $data[1];
        $this->tradeList = $data[2];
    }

    function save()
    {
        $data = array($this->book, $this->orderExecutionLookup, $this->tradeList);
        $this->dataStore->write($data);
    }

    function performSafeOperation($operation, $arguments)
    {
        $ret = null;
        $this->dataStore->lock(false);
        try{
            $this->load();

            $ret = call_user_func_array($operation, $arguments);

            $this->save();
        } catch(Exception $e) {
            $this->logger->error('Exception operating on shared file', $e);
        }
        $this->dataStore->unlock();

        return $ret;
    }

    public function Name()
    {
        return "TestMarket";
    }

    public function balances()
    {
        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = 100000;
        }

        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    /**
     * @return array Provides an array of strings listing supported currency pairs
     */
    public function supportedCurrencyPairs()
    {
        return array(CurrencyPair::BTCUSD);
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @param $pairRate Supply a price for the pair, in case the rate is based on quote currency
     * @return mixed The minimum order size, in the base currency of the pair
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        return 0.00000001;
    }

    public function ticker($pair)
    {
        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = 15;
        $t->ask = 16;
        $t->last = 15.5;
        $t->volume = 1200;

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $ret = array();

        $this->load();

        foreach($this->tradeList as $item)
        {
            if(!$item instanceof Trade)
                throw new Exception();

            if(!$item->timestamp instanceof MongoDate)
                throw new Exception();

            if($item->timestamp->sec > $sinceDate)
                $ret[] = $item;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $this->load();
        return $this->book;
    }

    public function buy($pair, $quantity, $price)
    {
        $args = func_get_args();
        $args[] = OrderType::BUY;
        return $this->performSafeOperation(array($this, 'submitOrder'), $args);
    }

    public function sell($pair, $quantity, $price)
    {
        $args = func_get_args();
        $args[] = OrderType::SELL;
        return $this->performSafeOperation(array($this, 'submitOrder'), $args);
    }

    public function submitOrder($pair, $quantity, $price, $side)
    {
        $ret = $this->createOrderResponse($pair, $quantity, $price, $side);
        if(!$this->isOrderAccepted($ret))
            return $ret;

        $orderId = $this->getOrderID($ret);

        $nf = new NonceFactory();

        $crossSide = &$this->book->asks;
        $priceComparison = function($a, $b){return $a < $b;};
        $placeSide = &$this->book->bids;

        if($side == OrderType::SELL){
            $crossSide = &$this->book->bids;
            $placeSide = &$this->book->asks;

            $priceComparison = function($a, $b){return $a > $b;};
        }

        $remainingQuantity = $quantity;
        $crossSideCount = count($crossSide);
        for($i = 0; $i < $crossSideCount; $i++)
        {
            $item = $crossSide[$i];
            if(!$item instanceof OrderDepthItem)
                throw new Exception();

            if($priceComparison($price, $item->price))
                break;

            $execQty = min($remainingQuantity, $item->quantity);

            //create trades for data feed
            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $nf->get();
            $t->price = $item->price;
            $t->quantity = $execQty;
            $t->timestamp = new MongoDate();
            $t->orderType = $side;
            $this->tradeList[] = $t;

            //create order executions for the matched orders
            $itemExecution = new OrderExecution();
            $itemExecution->orderId = $item->orderId;
            $itemExecution->price = $item->price;
            $itemExecution->quantity = $execQty;
            $itemExecution->timestamp = time();
            $itemExecution->txid = $t->tradeId;
            $this->orderExecutionLookup[$item->orderId][] = $itemExecution;

            $incomingExecution = new OrderExecution();
            $incomingExecution->orderId = $orderId;
            $incomingExecution->price = $item->price;
            $incomingExecution->quantity = $execQty;
            $incomingExecution->timestamp = time();
            $incomingExecution->txid = $t->tradeId;
            $this->orderExecutionLookup[$orderId][] = $incomingExecution;

            //update the order book
            $item->quantity -= $execQty;
            if($item->quantity <= 0)
                unset($crossSide[$i]);

            $remainingQuantity -= $execQty;
            if($remainingQuantity <= 0)
                break;
        }

        $crossSide = array_values($crossSide);

        if($remainingQuantity > 0){
            $di = new OrderDepthItem();
            $di->price = $price;
            $di->quantity = $remainingQuantity;
            $di->timestamp = time();
            $di->orderId = $orderId;

            $inserted = false;
            for ($i = 0; $i < count($placeSide); $i++) {
                $item = $placeSide[$i];
                if (!$item instanceof DepthItem)
                    throw new Exception();

                if ($priceComparison($price, $item->price))
                    continue;

                array_splice($placeSide, $i, 0, array($di));
                $placeSide = array_values($placeSide);
                $inserted = true;
                break;
            }

            if($inserted == false)
                $placeSide[] = $di;
        }

        return $ret;
    }

    private function createOrderResponse($pair, $quantity, $price, $side)
    {
        if(!$this->supports($pair) || $quantity <= 0 || $price <= 0)
            return array(
                'error' => true
            );

        $oid = uniqid($this->Name(),true);
        $this->orderExecutionLookup[$oid] = array();

        return array(
            'orderId' => $oid,
            'pair' => $pair,
            'quantity' => $quantity,
            'price' => $price,
            'side' => $side
        );
    }

    public function activeOrders()
    {
        // TODO: Implement activeOrders() method.
    }

    public function hasActiveOrders()
    {
        // TODO: Implement hasActiveOrders() method.
    }

    public function cancel($orderId)
    {
        return array(
            'orderId' => $orderId,
            'cancelled' => true
        );
    }

    public function isOrderAccepted($orderResponse)
    {
        return isset($orderResponse['orderId']);
    }

    public function isOrderOpen($orderResponse)
    {
        $orderId = $this->getOrderID($orderResponse);

        //traverse the books to see if order has leftovers
        //not the most efficient :-)
        foreach ($this->book->asks as $item) {
            if(!$item instanceof OrderDepthItem)
                throw new Exception();
            if($item->orderId == $orderId)
                return true;
        }
        foreach ($this->book->bids as $item) {
            if(!$item instanceof OrderDepthItem)
                throw new Exception();
            if($item->orderId == $orderId)
                return true;
        }

        return false;
    }

    public function getOrderExecutions($orderResponse)
    {
        $orderId = $this->getOrderID($orderResponse);

        if(array_key_exists($orderId, $this->orderExecutionLookup))
            return $this->orderExecutionLookup[$orderId];

        return array();
    }

    public function tradeHistory($desiredCount)
    {
        // TODO: Implement tradeHistory() method.
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['orderId'];
    }

}