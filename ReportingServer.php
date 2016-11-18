<?php

require_once('ActionProcess.php');

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 11/18/2016
 * Time: 1:48 AM
 */
class ReportingServer extends ActionProcess
{
    private $socket = null;
    private $address = 'localhost';
    private $port = 9123;

    public function getProgramOptions()
    {
        return array('address:','port:');
    }

    public function processOptions($options)
    {
        if(array_key_exists("address", $options))
            $this->address = $options['address'];
        if(array_key_exists("port", $options))
            $this->port = $options['port'];
    }

    public function init()
    {
        $sock = stream_socket_server("udp://$this->address:$this->port", $errNum, $errorMessage, STREAM_SERVER_BIND);
        if ($sock === false)
            throw new UnexpectedValueException("Could not bind to socket: $errNum $errorMessage");
        $this->socket = $sock;
    }

    public function run()
    {
        if(!$this->reporter instanceof IReporter)
            throw new Exception();

        $pkt = stream_socket_recvfrom($this->socket, 1500);

        $pktJson = json_decode($pkt);
        $err = json_last_error();
        if ($err !== JSON_ERROR_NONE)
            throw new Exception("Invalid data received\nError: $err\nServer returned:\n $pkt");

        $method = $pktJson[0];
        $params = array_slice($pktJson, 1);
        call_user_func_array(array($this->reporter, $method), $params);
    }

    public function shutdown()
    {
        if($this->socket != null)
            fclose($this->socket);
    }
}

$rs = new ReportingServer();
$rs->start();