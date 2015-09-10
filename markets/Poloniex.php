<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 7/21/2015
 * Time: 7:46 PM
 */

class Poloniex extends BaseExchange {

    protected $trading_url = "https://poloniex.com/tradingApi";
    protected $public_url = "https://poloniex.com/public";

    private $key;
    private $secret;
    private $nonceFactory;

    public function __construct($key, $secret){
        $this->key = $key;
        $this->secret = $secret;

        $this->nonceFactory = new NonceFactory();
    }

    public function Name()
    {
        return 'Poloniex';
    }

    public function balances()
    {
        $bal = $this->query(
            array(
                'command' => 'returnBalances'
            )
        );

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            if(isset($bal[strtoupper($curr)]))
                $balances[$curr] = $bal[strtoupper($curr)];
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
        return array(CurrencyPair::LTCBTC, CurrencyPair::XMRBTC, CurrencyPair::XCPBTC, CurrencyPair::MAIDBTC);
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @param $pairRate Supply a price for the pair, in case the rate is based on quote currency
     * @return mixed The minimum order size, in the base currency of the pair
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        // TODO: Implement minimumOrderSize() method.
    }

    public function ticker($pair)
    {
        $mktPairName = $this->getCurrencyPairName($pair);
        $prices = curl_query($this->public_url.'?command=returnTicker');

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $prices[$mktPairName]['highestBid'];
        $t->ask = $prices[$mktPairName]['lowestAsk'];
        $t->last = $prices[$mktPairName]['last'];
        $t->volume = $prices[$mktPairName]['quoteVolume'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $mktPairName = $this->getCurrencyPairName($pair);

        $trades = curl_query($this->public_url.'?command=returnTradeHistory&currencyPair='. $mktPairName .
            '&start=' . $sinceDate . '&end=' . time());

        $ret = array();

        foreach($trades as $raw) {
            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = md5($raw['date'] . $raw['type'] . $raw['rate'] . $raw['amount']);
            $t->price = (float) $raw['rate'];
            $t->quantity = (float) $raw['amount'];
            $t->orderType = strtoupper($raw['type']);

            $dt = new DateTime($raw['date']);
            $t->timestamp = new MongoDate($dt->getTimestamp());

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $mktPairName = $this->getCurrencyPairName($currencyPair);
        $rawBook = curl_query($this->public_url.'?command=returnOrderBook&currencyPair='. $mktPairName);
        return new OrderBook($rawBook);
    }

    public function buy($pair, $quantity, $price)
    {
        // TODO: Implement buy() method.
    }

    public function sell($pair, $quantity, $price)
    {
        // TODO: Implement sell() method.
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
        // TODO: Implement cancel() method.
    }

    public function isOrderAccepted($orderResponse)
    {
        // TODO: Implement isOrderAccepted() method.
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
        // TODO: Implement getOrderID() method.
    }

    private function query(array $req = array()) {
        if(!$this->nonceFactory instanceof NonceFactory)
            throw new Exception('No way to get nonce!');

        $req['nonce'] = $this->nonceFactory->get();

        // generate the POST data string
        $post_data = http_build_query($req, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->secret);

        // generate the extra headers
        $headers = array(
            'Key: '.$this->key,
            'Sign: '.$sign,
        );

        return curl_query($this->trading_url, $post_data, $headers);
    }

    private function getCurrencyPairName($pair)
    {
        if(!$this->supports($pair))
            throw new UnexpectedValueException('Currency pair not supported');

        $base = CurrencyPair::Base($pair);
        $quote = CurrencyPair::Quote($pair);

        if($base == Currency::BTC)
            return $base . '_' . $quote;

        if($quote == Currency::BTC)
            return $quote . '_' . $base;

        throw new Exception('Unsupported pair');
    }
}