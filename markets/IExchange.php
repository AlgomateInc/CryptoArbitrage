<?php

require_once('IAccount.php');

interface IExchange extends IAccount
{
    /**
     * @param string $currencyPair A currency pair to check support for
     * @return bool True if the pair is supported, false otherwise
     */
    public function supports($currencyPair);

    /**
     * @return array Provides an array of strings listing supported currency pairs
     */
    public function supportedCurrencyPairs();

    /**
     * @param $pair String The CurrencyPair pair we want to get minimum order size for
     * @param $pairRate float Supply a price for the pair, in case the rate is based on quote currency
     * @return mixed The minimum order size, in the base currency of the pair
     */
    public function minimumOrderSize($pair, $pairRate);

    /**
     * @return string[] Provides an array of strings listing supported currencies
     */
    public function supportedCurrencies();

    /**
     * @param $pair String The CurrencyPair string that we want ticker information for
     * @return Ticker An object with last tick information about the pair
     */
    public function ticker($pair);

    /**
     * @param $pair String The CurrencyPair string that we want last trade data for
     * @param $sinceDate int The unix timestamp of the date we are interested in
     * @return Trade[] An array of Trade objects, representing each trade that
     * has occurred since sinceDate
     */
    public function trades($pair, $sinceDate);

    /**
     * @param $currencyPair String The CurrencyPair string that we want depth data for
     * @return OrderBook The object containing order book depth data for the pair
     */
    public function depth($currencyPair);

    /**
     * @param $pair String The CurrencyPair string we want to trade
     * @param $quantity float The amount we want to trade
     * @param $price float The price we want to trade at
     * @return mixed The response from the exchange (exchange specific format,
     * use isOrderAccepted/getOrderID/etc. to manipulate)
     */
    public function buy($pair, $quantity, $price);

    /**
     * @param $pair String The CurrencyPair string we want to trade
     * @param $quantity float The amount we want to trade
     * @param $price float The price we want to trade at
     * @return mixed The response from the exchange (exchange specific format,
     * use isOrderAccepted/getOrderID/etc. to manipulate)
     */
    public function sell($pair, $quantity, $price);
    public function activeOrders();
    public function hasActiveOrders();

    /**
     * @param $orderId object Exchange-specific order identifier (can be obtained by
     * calling getOrderID on the original buy/sell exchange response
     * @return mixed object Exchange-specific response
     */
    public function cancel($orderId);

    /**
     * @param $orderResponse object Exchange-specific order response from buy/sell methods
     * @return boolean True if order accepted by exchange, else false
     */
    public function isOrderAccepted($orderResponse);

    /**
     * @param $orderResponse object Exchange-specific order response from buy/sell methods
     * @return boolean True if order still open on exchange, else false
     */
    public function isOrderOpen($orderResponse);

    /**
     * @param $orderResponse object Exchange-specific order response from buy/sell methods
     * @return OrderExecution[] An array of all the executions for this order
     */
    public function getOrderExecutions($orderResponse);

    public function tradeHistory($desiredCount);

    /**
     * @param $orderResponse object Exchange-specific order response from buy/sell methods
     * @return mixed Exchange-specific order identifier
     */
    public function getOrderID($orderResponse);
}

?>