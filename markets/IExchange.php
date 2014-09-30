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
     * @return array Provides an array of strings listing supported currencies
     */
    public function supportedCurrencies();

    public function ticker($pair);
    public function depth($currencyPair);

    public function buy($pair, $quantity, $price);
    public function sell($pair, $quantity, $price);
    public function activeOrders();
    public function hasActiveOrders();
    public function cancel($orderId);

    public function isOrderAccepted($orderResponse);
    public function isOrderOpen($orderResponse);

    public function getOrderExecutions($orderResponse);

    public function tradeHistory($desiredCount);
}

?>