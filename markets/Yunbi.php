<?php

require_once(__DIR__.'/../curl_helper.php');
require_once('BaseExchange.php');
require_once('NonceFactory.php');

/**
 * User: jon
 * Date: 1/29/2017
 * Time: 11:00 AM
 */
class Yunbi extends BaseExchange implements ILifecycleHandler
{
    private $key;
    private $secret;
    private $nonceFactory;

    private $supportedPairs = array();
    private $productId = array(); //assoc array pair->productid

    public function __construct($key, $secret) {
        $this->key = $key;
        $this->secret = $secret;
        $this->nonceFactory = new NonceFactory();
    }

    function init()
    {
        $pairs = curl_query($this->getApiUrl() . 'markets.json');
        foreach($pairs as $pairInfo){
            try{
                $base = CurrencyPair::Base($pairInfo['name']);
                $quote = CurrencyPair::Quote($pairInfo['name']);
                $pair = CurrencyPair::MakePair($base, $quote);

                $this->supportedPairs[] = $pair;
                $this->productId[$pair] = $pairInfo['id'];
            }catch(Exception $e){}
        }
    }

    public function Name()
    {
        return 'Yunbi';
    }

    public function balances()
    {
        $balance_info = $this->authQuery('members/me.json');

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = 0;
            foreach($balance_info['accounts'] as $balItem)
                if(strcasecmp($balItem['currency'], $curr) == 0)
                    $balances[$curr] += floatval($balItem['balance']);
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
        return 0.01;
    }

    public function ticker($pair)
    {
        $raw = curl_query($this->getApiUrl() . 'tickers/' . $this->productId[$pair] . '.json');

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['ticker']['buy'];
        $t->ask = $raw['ticker']['sell'];
        $t->last = $raw['ticker']['last'];
        $t->volume = $raw['ticker']['vol'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $tradeList = curl_query($this->getApiUrl() . 'trades.json?market=' . $this->productId[$pair]);

        $ret = array();

        foreach($tradeList as $raw) {
            if($raw['at'] < $sinceDate)
                continue;

            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $raw['id'];
            $t->price = floatval($raw['price']);
            $t->quantity = floatval($raw['volume']);
            $t->timestamp = new MongoDate($raw['at']);
            $t->orderType = ($raw['side'] == 'up')? OrderType::BUY : OrderType::SELL;

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $raw = curl_query($this->getApiUrl() . 'depth.json?market=' . $this->productId[$currencyPair]);

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

    private function submitOrder($side, $type, $pair, $quantity, $price)
    {
        $req = array(
            'volume' => "$quantity",
            'price' => "$price",
            'side' => $side,
            'market' => $this->productId[$pair],
            'ord_type' => $type
        );
        return $this->authQuery('orders.json', 'POST', $req);
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
        return $this->authQuery('order/delete.json', 'POST', array('id'=>$orderId));
    }

    public function isOrderAccepted($orderResponse)
    {
        return isset($orderResponse['id']);
    }

    public function isOrderOpenOfId($orderId)
    {
        $os = $this->authQuery('order.json', 'GET', array('id' => $orderId));
        if (isset($os['state'])) {
            return $os['state'] === 'wait';
        }
        return false;
    }

    public function isOrderOpen($orderResponse)
    {
        if (!$this->isOrderAccepted($orderResponse))
            return false;

        return $this->isOrderOpenOfId($this->getOrderID($orderResponse));
    }

    public function getOrderExecutions($orderResponse)
    {
        return $this->getOrderExecutionsOfId($this->getOrderID($orderResponse));
    }

    private function getOrderExecutionsOfId($orderId)
    {
        $ret = array();
        $order_info = $this->authQuery('order.json', 'GET', array('id' => $orderId));

        foreach ($order_info['trades'] as $fill) {
            $exec = new OrderExecution();
            $exec->txid = $fill['id'];
            $exec->orderId = $orderId;
            $exec->quantity = $fill['volume'];
            $exec->price = $fill['price'];
            $exec->timestamp = new MongoDate(strtotime($fill['created_at']));

            $ret[] = $exec;
        }

        return $ret;
    }

    public function getTradeHistoryForPair($pair, $page=1)
    {
        $ret = array();
        $params = array('market' => $this->productId[$pair],
            'state' => 'done',
            'order_by' => 'desc',
            'page' => $page);
        // big note: we're using the orders.json endpoint here because 
        // trades/my.json is currently unusable and times out constantly
        $orders = $this->authQuery('orders.json', 'GET', $params);
            
        foreach ($orders as $order) {
            $td = new Trade();
            //$td->tradeId = $trade['id']; // not available on the orders api
            $td->orderId = $order['id'];
            $td->exchange = $this->Name();
            $td->currencyPair = $pair;
            $td->orderType = ($order['side'] == 'ask')? OrderType::SELL : OrderType::BUY;
            $td->price = $order['avg_price'];
            $td->quantity = $order['volume'];
            $td->timestamp = new MongoDate(strtotime($order['created_at']));

            $ret[] = $td;
        }
        return $ret;
    }

    public function tradeHistory($desiredCount)
    {
        // Yunbi forces you to specify the market when getting your trades, 
        // so we're forced to hammer their APIs for every currency pair.
        $num_fetched = 0;
        $ret = array();
        $alltrades = array();
        $pagecounters = array();

        // Initialize trades for all currency pairs, throw away empty ones.
        foreach ($this->supportedPairs as $pair) {
            $orders = $this->getTradeHistoryForPair($pair);
            if (count($orders) > 0) {
                $alltrades[$pair] = $orders;
                $pagecounters[$pair] = 1;
            }
        }

        while ($num_fetched < $desiredCount)
        {
            // Find the pair with the latest trade
            $next_pair = null;
            foreach ($alltrades as $pair => $orders) {
                if (isset($next_pair)) {
                    if ($orders[0]->timestamp > $next_trade->timestamp) {
                        $next_pair = $pair;
                    }
                } else {
                    $next_pair = $pair;
                }
            }
            if (is_null($next_pair)) // nothing was found, we're done
                break;

            // Shift the first element off the orders
            $next_trade = array_shift($alltrades[$next_pair]);
            $ret[] = $next_trade;

            // Fetch the next page of trades
            if (empty($alltrades[$next_pair])) {
                $pagecounters[$next_pair]++;
                $next_trades = $this->getTradeHistoryForPair($next_pair, $pagecounters[$next_pair]);
                if (empty($next_trades)) {
                    unset($alltrades[$next_pair]); // none found, unset it
                } else {
                    $alltrades[$next_pair] = $next_trades;
                }
            }

            $num_fetched += 1;
        }
        return $ret;
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['id'];
    }

    private function getApiBase()
    {
        return 'https://yunbi.com';
    }

    private function getApiTail()
    {
        return '/api/v2/';
    }

    private function getApiUrl()
    {
        return $this->getApiBase() . $this->getApiTail();
    }

    private function signature($request_path, $body, $method)
    {
        $what = $method.'|'.$this->getApiTail().$request_path.'|'.$body;

        return hash_hmac("sha256", $what, $this->secret);
    }

    private function authQuery($request_path, $method='GET', $body=array()) {
        // Adapated from https://gist.github.com/lgn21st/5de1995bff6334824406
        $body['tonce'] = $this->nonceFactory->getMilliseconds();
        $body['access_key'] = $this->key;
        ksort($body);
        $body_string = http_build_query($body);
        $sig = $this->signature($request_path, $body_string, $method);

        $body_string .= '&signature='.$sig;
        if ($method=='GET') {
            return curl_query($this->getApiUrl() . $request_path.'?'.$body_string);
        } else {
            return curl_query($this->getApiUrl() . $request_path, $body_string, $method);
        }
    }
}
