<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 1/22/2015
 * Time: 14:19
 */

require_once(__DIR__ . '/../listener/IListener.php');

class SocketReporter implements IReporter, IListener {

    private $socket = null;
    private $host = null;
    private $port = null;

    private $canListen;

    public function __construct($host, $port, $listen = false)
    {
        $this->canListen = $listen;

        ///////////////////
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
        $result = @socket_connect($sock, $address, $port);
        if($result === false){
            $err = socket_last_error($sock);
            $errStr = socket_strerror($err);
            throw new Exception("Socket connection failed with code $err: $errStr");
        }
        $this->sendLoginMessage();
    }

    function __destruct()
    {
        if($this->socket != null){
            $this->sendLogoffMessage();
            sleep(1); //massive hack...gives server time to process logoff message
            //before we kill socket...ideally we wait for response from server...oh well
            socket_close($this->socket);
        }
    }

    private function sendLoginMessage()
    {
        $data = array(
            'MessageType' => 'Login',
            'Identifier' => gethostname(),
            'Listener' => $this->canListen
        );
        $this->send($data);
    }

    private function sendLogoffMessage()
    {
        $data = array(
            'MessageType' => 'Logoff'
        );
        $this->send($data);
    }

    private function send($data)
    {
        $msg = json_encode($data) . "\n";

        $res = @socket_write($this->socket, $msg);
        if($res === FALSE){
            $err = socket_last_error($this->socket);
            $errStr = socket_strerror($err);
            throw new Exception("Socket write failed with code $err: $errStr");
        }
    }

    public function receive()
    {
        $data = @socket_read($this->socket, 4096, PHP_NORMAL_READ);

        if($data === FALSE){
            $err = socket_last_error($this->socket);
            $errStr = socket_strerror($err);
            throw new Exception("Socket read failed with code $err: $errStr");
        }

        $msg = json_decode($data, true);
        return $msg;
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
        $data = array(
            'MessageType' => 'Trade',
            'Exchange' => $exchange_name,
            'CurrencyPair' => $currencyPair,
            'Trades' => $trades
        );
        $this->send($data);
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
        return $strategyId;
    }

    public function order($exchange, $type, $quantity, $price, $orderId, $orderResponse, $arbid)
    {
        $data = array(
            'MessageType' => 'Order',
            'Exchange' => $exchange,
            'OrderType' => $type,
            'Quantity' => $quantity,
            'Price' => $price,
            'MarketResponse' => $orderResponse,
            'StrategyId' => $arbid
        );

        if($orderId != null){
            $data['OrderId'] = $orderId;
        }

        $this->send($data);
    }

    public function execution($strategyId, $orderId, $market, $txId, $quantity, $price, $timestamp)
    {
        $data = array(
            'MessageType' => 'Execution',
            'StrategyId' => $strategyId,
            'OrderId' => $orderId,
            'Exchange' => $market,
            'ExecutionId' => $txId,
            'Quantity' => $quantity,
            'Price' => $price,
            'Timestamp' => $timestamp
        );

        $this->send($data);
    }

    public function trade($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        // TODO: Implement trade() method.
    }

    public function position($exchange_name, $currencyPair, $orderType, $price, $quantity, $timestamp)
    {
        // TODO: Implement position() method.
    }

    public function cancel($strategyId, $orderId, $cancelQuantity, $cancelResponse)
    {
        $data = array(
            'MessageType' => 'Cancel',
            'StrategyId' => $strategyId,
            'OrderId' => $orderId,
            'Quantity' => $cancelQuantity,
            'MarketResponse' => $cancelResponse
        );

        $this->send($data);
    }
}