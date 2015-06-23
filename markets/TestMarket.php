<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/2/2015
 * Time: 10:12 PM
 */

require_once('BaseExchange.php');
require_once('NonceFactory.php');

class TestMarket extends BaseExchange
{
    private $tradeList = array();
    private $book;
    private $validOrderIdList = array();
    private $sharedFile;

    function __construct($clearBook = true)
    {
        $this->book = new OrderBook();

        $sharedFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'TestMarketOrderBook';
        $this->sharedFile = fopen($sharedFileName, 'c+');
        if($this->sharedFile === FALSE)
            throw new Exception();

        //initialize the file or ourselves
        flock($this->sharedFile, LOCK_EX);
        try{
            if($clearBook == true)
                $this->writeData();
            else
                $this->readData();
        }catch(Exception $e){

        }
        flock($this->sharedFile, LOCK_UN);
    }

    function __destruct()
    {
        fclose($this->sharedFile);
    }

    function readData()
    {
        $str = fread($this->sharedFile, 1000000);
        if($str == FALSE)
            return;

        $data = unserialize(trim($str));
        $this->book = $data[0];
        $this->validOrderIdList = $data[1];
        $this->tradeList = $data[2];
    }

    function writeData()
    {
        $data = array($this->book, $this->validOrderIdList, $this->tradeList);
        $strData = serialize($data);
        ftruncate($this->sharedFile, 0);
        $ret = fwrite($this->sharedFile, $strData);
        if($ret == FALSE)
            throw new Exception();
        fflush($this->sharedFile);
    }

    function performSafeOperation($operation, $arguments)
    {
        $ret = null;
        flock($this->sharedFile, LOCK_EX);
        try{
            $this->readData();

            $ret = call_user_func_array($operation, $arguments);

            $this->writeData();
        } catch(Exception $e) {

        }
        flock($this->sharedFile, LOCK_UN);

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

        flock($this->sharedFile, LOCK_SH);
        $this->readData();
        flock($this->sharedFile, LOCK_UN);

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
        flock($this->sharedFile, LOCK_SH);
        $this->readData();
        flock($this->sharedFile, LOCK_UN);
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
            if(!$item instanceof DepthItem)
                throw new Exception();

            if($priceComparison($price, $item->price))
                break;

            $execQty = min($remainingQuantity, $item->quantity);

            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $nf->get();
            $t->price = $item->price;
            $t->quantity = $execQty;
            $t->timestamp = new MongoDate();
            $t->orderType = $side;
            $this->tradeList[] = $t;

            $item->quantity -= $execQty;
            if($item->quantity <= 0)
                unset($crossSide[$i]);

            $remainingQuantity -= $execQty;
            if($remainingQuantity <= 0)
                break;
        }

        $crossSide = array_values($crossSide);

        if($remainingQuantity > 0){
            $di = new DepthItem();
            $di->price = $price;
            $di->quantity = $remainingQuantity;
            $di->timestamp = time();

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

        return $this->createOrderResponse($pair, $quantity, $price, $side);
    }

    private function createOrderResponse($pair, $quantity, $price, $side)
    {
        if(!$this->supports($pair) || $quantity <= 0 || $price <= 0)
            return array(
                'error' => true
            );

        $oid = uniqid($this->Name(),true);
        $this->validOrderIdList[] = $oid;

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
        // TODO: Implement isOrderOpen() method.
    }

    public function getOrderExecutions($orderResponse)
    {
        // TODO: Implement getOrderExecutions() method.
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