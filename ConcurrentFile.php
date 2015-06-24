<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/23/2015
 * Time: 10:50 PM
 */

class ConcurrentFile {
    private $logger;

    private $sharedFile;

    function __construct($fileName)
    {
        $this->logger = Logger::getLogger(get_class($this));

        $this->sharedFile = fopen($fileName, 'c+');
        if($this->sharedFile === FALSE)
            throw new Exception('Could not open shared file!');
    }

    function __destruct()
    {
        fclose($this->sharedFile);
    }

    function read()
    {
        $str = fread($this->sharedFile, 1000000);
        if($str == FALSE)
            return null;

        $data = unserialize(trim($str));
        return $data;
    }

    function readLocked()
    {
        $ret = null;

        $this->lock();
        try{
            $ret = $this->read();
        }catch (Exception $e){
            $this->logger->error('Exception reading trade data from shared file', $e);
        }
        $this->unlock();

        return $ret;
    }

    function write($data)
    {
        $strData = serialize($data);
        ftruncate($this->sharedFile, 0);
        $ret = fwrite($this->sharedFile, $strData);
        if($ret == FALSE)
            throw new Exception();
        fflush($this->sharedFile);
    }

    function writeLocked($data)
    {
        $this->lock(false);
        try{
            $this->write($data);
        }catch (Exception $e){
            $this->logger->error('Exception writing trade data to shared file', $e);
        }
        $this->unlock();
    }

    function lock($shared = true)
    {
        flock($this->sharedFile, ($shared)? LOCK_SH : LOCK_EX);
    }

    function unlock()
    {
        flock($this->sharedFile, LOCK_UN);
    }
}