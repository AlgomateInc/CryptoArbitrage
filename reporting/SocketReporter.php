<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 1/22/2015
 * Time: 14:19
 */

use CryptoMarket\Record\OrderBook;

require_once('IListener.php');

class SocketReporter implements IReporter, IListener {

    private $socket = null;
    private $host = null;
    private $port = null;

    private $canListen;

    private $lastRetryTime = 0;
    private $reconnectCount = 0;
    private $retryBuffer = array();
    const RETRY_TIMEOUT_SECS = 5;
    const RETRY_RECONNECT_COUNT = 2880; //4hrs

    public function __construct($host, $port, $listen = false)
    {
        $this->canListen = $listen;

        ///////////////////
        $this->host = $host;
        $this->port = $port;
    }

    function __destruct()
    {
        $this->disconnect();
    }

    private function connect()
    {
        $this->disconnect();

        //create new socket
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if($sock === false){
            $err = socket_last_error();
            $errStr = socket_strerror($err);
            throw new \Exception("Socket creation failed with code $err: $errStr");
        }

        //connect to the destination server
        $address = gethostbyname($this->host);
        $result = @socket_connect($sock, $address, $this->port);
        if($result === false){
            $err = socket_last_error($sock);
            socket_close($sock);
            $errStr = socket_strerror($err);
            throw new \Exception("Socket connection failed with code $err: $errStr");
        }

        $this->socket = $sock;
        $this->sendLoginMessage();
    }

    private function disconnect()
    {
        if($this->socket != null){
            socket_shutdown($this->socket);
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    private function sendLoginMessage()
    {
        $data = array(
            'MessageType' => 'Login',
            'Identifier' => gethostname(),
            'Listener' => $this->canListen
        );
        $this->sendUnbuffered($data);
    }

    private function send($data)
    {
        $this->retryBuffer[] = json_encode($data) . "\n";

        if(time() < $this->lastRetryTime + self::RETRY_TIMEOUT_SECS)
            return;

        //connect the socket if needed
        if($this->socket == null){
            try{
                $this->connect();
            }catch (\Exception $e){
                $this->lastRetryTime = time();
                $this->reconnectCount++;
                if($this->reconnectCount > self::RETRY_RECONNECT_COUNT)
                    throw $e;
                return;
            }
        }

        //send all the data in the buffer
        while(count($this->retryBuffer) > 0)
        {
            $msg = array_shift($this->retryBuffer);

            $res = @socket_write($this->socket, $msg);
            if($res === FALSE)            {
                array_unshift($this->retryBuffer, $msg);
                $this->disconnect();
            }
        }
    }

    private function sendUnbuffered($data)
    {
        $msg = json_encode($data) . "\n";

        $res = @socket_write($this->socket, $msg);
        if($res === FALSE){
            $err = socket_last_error($this->socket);
            $errStr = socket_strerror($err);
            throw new \Exception("Socket write failed with code $err: $errStr");
        }
    }

    public function receive()
    {
        $data = @socket_read($this->socket, 4096, PHP_NORMAL_READ);

        if($data === FALSE){
            $err = socket_last_error($this->socket);
            $errStr = socket_strerror($err);
            throw new \Exception("Socket read failed with code $err: $errStr");
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

    public function fees($exchange_name, $currencyPair, $takerFee, $makerFee)
    {
        $data = array(
            'MessageType' => 'Fees',
            'Exchange' => $exchange_name,
            'CurrencyPair' => $currencyPair,
            'TakerFee' => $takerFee,
            'MakerFee' => $makerFee
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

    public function trades($exchange_name, $currencyPair, array $trades)
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

    public function orderMessage($strategyId, $orderId, $messageCode, $messageText)
    {
        // TODO: Implement orderMessage() method.
    }

    public function trade($exchange_name, $currencyPair, $tradeId, $orderId, $orderType, $price, $quantity, $timestamp)
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
