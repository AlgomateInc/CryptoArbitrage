<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 1/22/2015
 * Time: 14:19
 */
namespace CryptoArbitrage\Reporting;

use CryptoArbitrage\Reporting\IListener;
use CryptoArbitrage\Reporting\IReporter;

use CryptoMarket\Record\OrderBook;

class SocketReporter implements IReporter, IListener
{
    private $logger = null;

    private $masterSocket = null;
    private $clientSockets = [];

    private $host = null;
    private $port = null;

    const RETRY_TIMEOUT_SECS = 5;
    const RETRY_RECONNECT_COUNT = 2880; //4hrs

    public function __construct($host, $port)
    {
        $this->logger = \Logger::getLogger(get_class($this));

        $this->host = $host;
        $this->port = $port;

        if (($this->masterSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            $this->logger->error("socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n");
        }

        $address = gethostbyname($this->host);
        if (socket_bind($this->masterSocket, $address, $this->port) === false) {
            $this->logger->error("socket_bind() failed: reason: " . socket_strerror(socket_last_error($this->masterSocket)) . "\n");
        }

        socket_set_nonblock($this->masterSocket);
        if (socket_listen($this->masterSocket, 5) === false) {
            $this->logger->error("socket_listen() failed: reason: " . socket_strerror(socket_last_error($this->masterSocket)) . "\n");
        }
    }

    public function __destruct()
    {
        $this->destroySocket($this->masterSocket);
    }

    public function acceptConnection()
    {
        // see if anyone has tried to connect on master socket
        while ($clientSocket = socket_accept($this->masterSocket)) {
            $client = new SocketClient($clientSocket);
            socket_getpeername($clientSocket , $address, $port);
            $this->logger->info("New connection accepted, address [" . $address . "] port [" . $port ."]\n");
            $this->clientSockets[] = $client;
            $this->sendLoginMessage($client);
        }
    }

    private function destroySocket($socket)
    {
        if ($socket != null) {
            socket_shutdown($socket);
            socket_close($socket);
        }
    }

    private function sendLoginMessage($clientSocket)
    {
        $data = array(
            'MessageType' => 'Login',
            'Identifier' => gethostname(),
        );
        $this->sendUnbuffered($clientSocket, $data);
    }

    private function sendToClient($client, $data)
    {
        if ($client->socket == null) {
            return false;
        }
        $client->retryBuffer[] = json_encode($data) . "\n";

        //send all the data in the buffer, delete if too many failed attempts
        while (count($client->retryBuffer) > 0) {
            $msg = array_shift($client->retryBuffer);
            $res = @socket_write($client->socket, $msg);
            if ($res === FALSE) {
                array_unshift($client->retryBuffer, $msg);
                $client->lastRetryTime = time();
                $client->reconnectCount++;
                if ($client->reconnectCount > self::RETRY_RECONNECT_COUNT) {
                    $this->destroySocket($client->socket);
                    return false;
                }
            }
        }
        return true;
    }

    private function send($data)
    {
        foreach ($this->clientSockets as $key=>$client) {
            if (time() > $client->lastRetryTime + self::RETRY_TIMEOUT_SECS) {
                if (false == $this->sendToClient($client, $data)) {
                    unset($this->clientSockets[$key]);
                }
            }
        }
    }

    private function sendUnbuffered($client, $data)
    {
        $msg = json_encode($data) . "\n";

        $res = @socket_write($client->socket, $msg);
        if($res === FALSE){
            $err = socket_last_error($client->socket);
            $errStr = socket_strerror($err);
            throw new \Exception("Socket write failed with code $err: $errStr");
        }
    }


    public function receiveFromSocket($socket)
    {
        $data = @socket_read($socket, 4096, PHP_NORMAL_READ);

        if ($data === FALSE){
            $err = socket_last_error($socket);
            $errStr = socket_strerror($err);
            throw new \Exception("Socket read failed with code $err: $errStr");
        }
        return $data;
    }

    public function receive()
    {
        $data = "";
        foreach ($this->clientSockets as $client) {
            $data .= $this->receiveFromSocket($client->socket) . "\n";
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

    public function publicKey($serverName, $publicKey)
    {
        $data = array(
            'MessageType' => 'PublicKey',
            'ServerName' => $serverName,
            'PublicKey' => $publicKey,
        );
        $this->send($data);
    }
}

