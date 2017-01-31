<?php

require_once(__DIR__.'/../curl_helper.php');
require_once('BaseExchange.php');
require_once('NonceFactory.php');

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 11/3/2016
 * Time: 6:31 AM
 */
class Gdax extends BaseExchange implements ILifecycleHandler
{
    private $key;
    private $secret;
    private $passphrase;

    private $supportedPairs = array();
    private $minOrderSizes = array(); //assoc array pair->minordersize
    private $productId = array(); //assoc array pair->productid

    public function __construct($key, $secret, $passphrase) {
        $this->key = $key;
        $this->secret = $secret;
        $this->passphrase = $passphrase;
    }

    function init()
    {
        $pairs = curl_query($this->getApiUrl() . '/products');
        foreach($pairs as $pairInfo){
            try{
                $pair = $pairInfo['base_currency'] . $pairInfo['quote_currency'];
                CurrencyPair::Base($pair); //checks the format

                $this->supportedPairs[] = strtoupper($pair);
                $this->minOrderSizes[$pair] = $pairInfo['base_min_size'];
                $this->productId[$pair] = $pairInfo['id'];
            }catch(Exception $e){}
        }
    }

    public function Name()
    {
        return 'Gdax';
    }

    public function balances()
    {
        $balance_info = $this->authQuery('/accounts');

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = 0;
            foreach($balance_info as $balItem)
                if(strcasecmp($balItem['currency'], $curr) == 0)
                    $balances[$curr] += $balItem['available'];
        }

        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    public function supportedCurrencyPairs()
    {
        return $this->supportedPairs;
    }

    public function minimumOrderSize($pair, $pairRate)
    {
        return $this->minOrderSizes[$pair];
    }

    public function ticker($pair)
    {
        $raw = curl_query($this->getApiUrl() . '/products/' . $this->productId[$pair] . '/ticker');

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['bid'];
        $t->ask = $raw['ask'];
        $t->last = $raw['price'];
        $t->volume = $raw['volume'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $tradeList = curl_query($this->getApiUrl() . '/products/' . $this->productId[$pair] . '/trades');

        $ret = array();

        foreach($tradeList as $raw) {
            $tradeTime = strtotime($raw['time']);
            if($tradeTime < $sinceDate)
                continue;

            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $raw['trade_id'];
            $t->price = (float) $raw['price'];
            $t->quantity = (float) $raw['size'];
            $t->timestamp = new MongoDate();
            $t->orderType = ($raw['side'] == 'buy')? OrderType::SELL : OrderType::BUY;

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $raw = curl_query($this->getApiUrl() . '/products/' . $this->productId[$currencyPair] .
            '/book?level=2');

        $book = new OrderBook($raw);

        return $book;
    }

    public function buy($pair, $quantity, $price)
    {
        return $this->submitOrder('buy', 'limit', $pair, $quantity, $price);
    }

    public function sell($pair, $quantity, $price)
    {
        return $this->submitOrder('sell', 'limit', $pair, $quantity, $price);
    }

    // Used for testing the order executions
    public function submitMarketOrder($side, $pair, $quantity)
    {
        return $this->submitOrder($side, 'market', $pair, $quantity, 0);
    }

    private function submitOrder($side, $type, $pair, $quantity, $price)
    {
        $req = array(
            'size' => "$quantity",
            'price' => "$price",
            'side' => $side,
            'product_id' => $this->productId[$pair],
            'type' => $type
        );
        return $this->authQuery('/orders', 'POST', $req);
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
        return $this->authQuery('/orders/' . $orderId, 'DELETE');
    }

    public function isOrderAccepted($orderResponse)
    {
        return isset($orderResponse['id']);
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        $os = $this->authQuery('/orders/' . $this->getOrderId($orderResponse));
        if (isset($os['status'])) {
            return $os['status'] === 'open' || $os['status'] === 'pending';
        }
        return false;
    }

    public function getOrderExecutions($orderResponse)
    {
        return $this->getOrderExecutionsOfId($this->getOrderId($orderResponse));
    }

    private function getOrderExecutionsOfId($orderId)
    {
        $ret = array();
        $after_cursor = '';
        do
        {
            $url = '/fills?order_id='.$orderId.$after_cursor;
            $order_fills = $this->authQuery($url, 'GET', '', true);

            // Probably not necessary for this function, but in case an order
            // has over 100 executions, we're safe.
            // See https://docs.gdax.com/#pagination
            if (isset($order_fills['header']['Cb-After'])) {
                $after_cursor = '&after='.$order_fills['header']['Cb-After'];
            } else {
                $after_cursor = '';
            }

            foreach ($order_fills['body'] as $fill) {
                if ($fill['order_id'] === $orderId)
                {
                    $exec = new OrderExecution();
                    $exec->txid = $fill['trade_id'];
                    $exec->orderId = $fill['order_id'];
                    $exec->quantity = $fill['size'];
                    $exec->price = $fill['price'];
                    $exec->timestamp = new MongoDate(strtotime($fill['created_at']));

                    $ret[] = $exec;
                }
            }
        } while ($after_cursor != '');

        return $ret;
    }

    public function tradeHistory($desiredCount)
    {
        $num_fetched = 0;
        $ret = array();
        $after_cursor = '';
        do
        {
            // The header contains a special 'Cb-After' parameter to use in
            // subsequent requests, see https://docs.gdax.com/#pagination
            // Alternatively, this query could get the orders using
            // '/orders?status=all' but this doesn't retrieve the trade id.
            $orders = $this->authQuery('/fills'.$after_cursor, 'GET', '', true);
            if (isset($orders['header']['Cb-After'])) {
                $after_cursor = '?after='.$orders['header']['Cb-After'];
            }
            if(count($orders['body']) === 0)
                break;
            foreach($orders['body'] as $order) {
                $td = new Trade();
                $td->tradeId = $order['trade_id'];
                $td->orderId = $order['order_id'];
                $td->exchange = $this->Name();
                $td->currencyPair = $this->currencyPairOfProductId($order['product_id']);
                $td->orderType = ($order['side'] == 'sell')? OrderType::SELL : OrderType::BUY;
                $td->price = $order['price'];
                $td->quantity = $order['size'];
                $td->timestamp = new MongoDate(strtotime($order['created_at']));

                $ret[] = $td;
                $num_fetched += 1;
                if($num_fetched >= $desiredCount)
                    break;
            }
        }
        while ($num_fetched < $desiredCount);
        return $ret;
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['id'];
    }

    private function getApiUrl()
    {
        return 'https://api.gdax.com';
    }

    private function signature($request_path, $body, $timestamp, $method)
    {
        $what = $timestamp.$method.$request_path.$body;

        return base64_encode(hash_hmac("sha256", $what, base64_decode($this->secret), true));
    }

    private function authQuery($request_path, $method='GET', $body='', $return_headers=false) {
        $ts = time();
        $body = is_array($body) ? json_encode($body) : $body;
        $sig = $this->signature($request_path, $body, $ts, $method);

        $headers = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
            'CB-ACCESS-KEY: ' . $this->key,
            'CB-ACCESS-SIGN: ' . $sig,
            'CB-ACCESS-TIMESTAMP: ' . $ts,
            'CB-ACCESS-PASSPHRASE: ' . $this->passphrase
        );

        return curl_query($this->getApiUrl() . $request_path, $body, $method, $headers, $return_headers);
    }

    // Helper function for converting from the GDAX product id, e.g. "BTC-USD",
    // to the standard representation in the application.
    private function currencyPairOfProductId($productId)
    {
        foreach($this->productId as $pair=>$pid) {
            if ($productId === $pid)
                return $pair;
        }
        throw new Exception("Product id not found: $productId");
    }
}
