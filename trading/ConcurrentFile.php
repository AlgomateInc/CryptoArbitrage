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
    private $isLocked = false;

    function __construct($fileName)
    {
        $this->logger = Logger::getLogger(get_class($this));

        $this->sharedFile = fopen($fileName, 'c+');
        if($this->sharedFile === FALSE)
            throw new \Exception('Could not open shared file!');
    }

    function __destruct()
    {
        fclose($this->sharedFile);
    }

    function read()
    {
        $data = null;

        $this->lock();
        try{

            rewind($this->sharedFile);
            $fileInfo = fstat($this->sharedFile);

            if($fileInfo['size'] > 0) {
                $str = fread($this->sharedFile, $fileInfo['size']);
                if ($str != FALSE)
                    $data = unserialize(trim($str));
            }

        }catch (\Exception $e){
            $this->logger->error('Exception reading trade data from shared file', $e);
        }
        $this->unlock();

        return $data;
    }

    function write($data)
    {
        $this->lock(false);
        try{
            $strData = serialize($data);
            ftruncate($this->sharedFile, 0);
            rewind($this->sharedFile);
            $ret = fwrite($this->sharedFile, $strData);
            if($ret == FALSE)
                throw new \Exception();
            fflush($this->sharedFile);
        }catch (\Exception $e){
            $this->logger->error('Exception writing trade data to shared file', $e);
        }
        $this->unlock();
    }

    function lock($shared = true)
    {
        if($this->isLocked)
            return;

        flock($this->sharedFile, ($shared)? LOCK_SH : LOCK_EX);
        $this->isLocked = true;
    }

    function unlock()
    {
        if(!$this->isLocked)
            return;

        flock($this->sharedFile, LOCK_UN);
        $this->isLocked = false;
    }
}
