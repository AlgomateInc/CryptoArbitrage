<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 1/22/2015
 * Time: 14:19
 */

class SocketReporter implements IReporter {

    private $socket = null;
    private $host = null;
    private $port = null;

    public function __construct($host, $port)
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if($sock === false){
            $err = socket_last_error();
            $errStr = socket_strerror($err);
            throw new Exception("Socket creation failed with code $err: $errStr");
        }
        $this->socket = $sock;
        $this->host = $host;
        $this->port = $port;

        //connect to the destination server
        $address = gethostbyname($host);
        $result = socket_connect($sock, $address, $port);
        if($result === false){
            $err = socket_last_error($sock);
            $errStr = socket_strerror($err);
            throw new Exception("Socket connection failed with code $err: $errStr");
        }

    }

    function __destruct()
    {
        if($this->socket != null)
            socket_close($this->socket);
    }

    private function send($data)
    {
        $msg = json_encode($data) . "\n";

        $res = socket_write($this->socket, $msg);
        if($res === FALSE){
            $err = socket_last_error($this->socket);
            $errStr = socket_strerror($err);
            throw new Exception("Socket write failed with code $err: $errStr");
        }
    }

    public function balance($exchange_name, $currency, $balance)
    {
        $data = array(
            'MessageType' => 'Balance',
            'Exchange' => $exchange_name,
            'Currency' => $currency,
            'Balance' => $balance
        );
        $this->send($data);
    }

    public function market($exchange_name, $currencyPair, $bid, $ask, $last, $vol)
    {
        $data = array(
            'MessageType' => 'Market',
            'Exchange' => $exchange_name,
            'CurrencyPair' => $currencyPair,
            'Bid' => $bid,
            'Ask' => $ask,
            'Last' => $last,
            'Volume' => $vol
        );
        $this->send($data);
    }

    public function depth($exchange_name, $currencyPair, OrderBook $depth)
    {
        $data = array(
            'MessageType' => 'Depth',
            'Exchange' => $exchange_name,
            'CurrencyPair' => $currencyPair,
            'Depth' => $depth
        );
        $this->send($data);
    }

    public function trades($exchange_name, $currencyPair, $trades)
    {
        // TODO: Implement trades() method.
    }

    public function transaction($exchange_name, $id, $type, $currency, $amount, $timestamp)
    {
        // TODO: Implement transaction() method.
    }

    public function arbitrage($quantity, $pair, $buyExchange, $buyLimit, $sellExchange, $sellLimit)
    {
        // TODO: Implement arbitrage() method.
    }

    public function strategyOrder($strategyId, $iso)
    {
        // TODO: Implement strategy() method.
    }

    public function order($exchange, $type, $quantity, $price, $orderId, $orderResponse, $arbid)
    {
        // TODO: Implement order() method.
    }

    public function execution($arbId, $orderId, $market, $txid, $quantity, $price, $timestamp)
    {
        // TODO: Implement execution() method.
    }

    public function trade($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        // TODO: Implement trade() method.
    }

    public function position($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        // TODO: Implement position() method.
    }
}