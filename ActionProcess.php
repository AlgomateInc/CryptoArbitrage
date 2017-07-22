<?php

use CryptoMarket\AccountLoader\ConfigAccountLoader;
use CryptoMarket\AccountLoader\IAccountLoader;
use CryptoMarket\AccountLoader\MongoAccountLoader;
use CryptoMarket\Exchange\ILifecycleHandler;

require_once('ConfigData.php');
\Logger::configure(\ConfigData::LOG4PHP_CONFIG);

require_once('legacy/TestAccountLoader.php');
require_once('reporting/MultiReporter.php');
require_once('reporting/ConsoleReporter.php');
require_once('reporting/MongoReporter.php');
require_once('reporting/FileReporter.php');
require_once('reporting/SocketReporter.php');

abstract class ActionProcess {

    protected $reporter;
    protected $listener;
    protected $requiresListener = false;
    private $monitor = false;
    private $monitor_timeout = 20;
    private $fork = false;
    private $publicKey;
    private $privateKey;

    abstract public function getProgramOptions();
    abstract public function processOptions($options);

    abstract public function init();
    abstract public function run();
    abstract public function shutdown();

    private $accountLoader = null;
    protected $exchanges = array();
    protected $configuredExchanges;
    protected $retryInitExchanges;

    public function getAllProgramOptions()
    {
        $objOptions = $this->getProgramOptions();

        $shortopts = "";
        $longopts = array(
            'console',
            "mongodb::",
            "file:",
            "monitor::",
            "fork",
            'socket:',
            'testmarket',
            'servername:',
            'keypair::'
        );

        if(is_array($objOptions))
            $longopts = array_merge($longopts, $objOptions);

        return $longopts;
    }

    private function processCommandLine($options)
    {
        // Configure reporters
        $this->reporter = new MultiReporter();

        if(array_key_exists("mongodb", $options)) {
            if (isset($options['mongodb']) && $options['mongodb'] !== false) {
                //expect 'servername/databasename' url format
                $mongodb_uri = $options['mongodb'];
                $pos = mb_strrpos($mongodb_uri,'/');
                if ($pos === false) {
                    throw new \Exception('MongoDB database name not specified');
                }
                $mongodb_dbname = mb_substr($mongodb_uri, $pos + 1);
            } else {
                $mongodb_uri = \ConfigData::MONGODB_URI;
                $mongodb_dbname = \ConfigData::MONGODB_DBNAME;
            }

            $this->reporter->add(new MongoReporter($mongodb_uri, $mongodb_dbname));
        }

        if(array_key_exists("file", $options) && isset($options['file']))
            $this->reporter->add(new FileReporter($options['file']));

        if(array_key_exists('socket', $options) && isset($options['socket']))
        {
            $host = parse_url($options['socket'], PHP_URL_HOST);
            $port = parse_url($options['socket'], PHP_URL_PORT);

            if($host != null && $port != null) {
                $sr = new SocketReporter($host, $port, $this->requiresListener);
                $this->reporter->add($sr);

                if($this->requiresListener)
                    $this->listener = $sr;
            }
        }

        if(array_key_exists('console', $options) || $this->reporter->count() == 0)
            $this->reporter->add(new ConsoleReporter());

        ////////////////////////////////
        // Load all the accounts data
        if(array_key_exists('servername', $options) && isset($options['servername'])) {
            $this->serverName = $options['servername'];
        } else {
            $this->serverName = gethostname();
        }

        if(array_key_exists('testmarket', $options)) {
            $this->accountLoader = new TestAccountLoader($this->requiresListener);
        } else {
            if(array_key_exists("mongodb", $options)) {
                $this->accountLoader = new MongoAccountLoader(
                    \ConfigData::MONGODB_URI,
                    \ConfigData::MONGODB_DBNAME,
                    \ConfigData::ACCOUNTS_CONFIG,
                    $this->serverName);
            }
            else {
                $this->accountLoader = new ConfigAccountLoader(\ConfigData::ACCOUNTS_CONFIG);
            }
        }

        $this->checkConfiguration($this->accountLoader);

        ////////////////////////////////
        if(array_key_exists("monitor", $options)){
            $this->monitor = true;

            if(is_numeric($options['monitor']))
                $this->monitor_timeout = intval($options['monitor']);
        }

        if(array_key_exists("fork", $options))
            $this->fork = true;

        if(array_key_exists('keypair', $options)) {
            if(isset($options['keypair']) && $options['keypair'] !== false) {
                $this->privateKey = file_get_contents($options['keypair']);
                $keyPair = openssl_pkey_get_private($this->privateKey);
            } else {
                
                $keyPair = openssl_pkey_new([
                    "digest_alg" => "sha512",
                    "private_key_bits" => 2048,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                ]);
                openssl_pkey_export($keyPair, $this->privateKey);
            }

            // Extract the public key to $publicKey
            $this->publicKey = openssl_pkey_get_details($keyPair)['key'];

            // Encrypt the data to $encrypted using the public key
            //$data = 'plaintext data goes here';
            //openssl_public_encrypt($data, $encrypted, $this->publicKey);
            // Decrypt the data using the private key and store the results in $decrypted
            //openssl_private_decrypt($encrypted, $decrypted, $this->privateKey);
            //echo $decrypted;

            $this->reporter->publicKey($this->serverName, $this->publicKey);
        }

        ////////////////////////////////////

        $this->processOptions($options);
    }

    private function checkConfiguration(IAccountLoader $loader)
    {
        $config = $loader->getConfig();

        if($this->configuredExchanges == null){
            $this->configuredExchanges = $config;
            return;
        }

        if($this->configuredExchanges != $config)
            throw new \Exception('Configuration changed');
    }

    private function prepareMarkets(IAccountLoader $loader)
    {
        $this->retryInitExchanges = $this->initializeMarkets($loader->getAccounts());
    }

    private function initializeMarkets(array $marketList)
    {
        $logger = \Logger::getLogger(get_class($this));

        $failedInitExchanges = array();

        foreach($marketList as $name => $mkt)
        {
            if(isset($this->exchanges[$name]))
                continue;

            if ($mkt instanceof ILifecycleHandler) {
                try {
                    $mkt->init();
                } catch (\Exception $e) {
                    $failedInitExchanges[$name] = $mkt;
                    $logger->error('Error initializing market: ' . $e->getMessage());
                    continue;
                }
            }

            $this->exchanges[$name] = $mkt;
        }

        return $failedInitExchanges;
    }

    public function start($options)
    {
        date_default_timezone_set('UTC');
        error_reporting(E_ALL);

        $logger = \Logger::getLogger(get_class($this));
        $logger->info(get_class($this) . ' is starting');

        try{
            $this->processCommandLine($options);
        }catch(\Exception $e){
            $logger->error('Preparation error: ' . $e->getMessage());
            exit(1);
        }

        //if we are here, we are monitoring.
        //fork the process depending on setup and loop
        if($this->fork){
            $pid = pcntl_fork();

            if($pid == -1){
                die('Could not fork process for monitoring!');
            }else if ($pid){
                //parent process can now exit
                exit;
            }
        }

        //perform the monitoring loop
        try{
            $logger->info(get_class($this) . ' - starting');
            $this->prepareMarkets($this->accountLoader);
            $this->init();

            try{
                do {
                    $this->checkConfiguration($this->accountLoader);
                    $this->retryInitExchanges = $this->initializeMarkets($this->retryInitExchanges);
                    $this->run();
                    if($this->monitor)
                        sleep($this->monitor_timeout);
                }while($this->monitor);

                $this->shutdown();
                $logger->info(get_class($this) . ' - finished');
            }catch(\Exception $e){
                $this->shutdown();
                $logger->info(get_class($this) . ' - finished');
                throw $e;
            }

        }catch(\Exception $e){
            $logger->error('ActionProcess runtime error: ' . $e->getMessage());
            exit(1);
        }

    }
} 

