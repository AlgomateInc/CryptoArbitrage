<?php

require_once('common.php');
require_once('ConfigAccountLoader.php');
require_once('MongoAccountLoader.php');
require_once('reporting/MultiReporter.php');
require_once('reporting/ConsoleReporter.php');
require_once('reporting/MongoReporter.php');
require_once('reporting/FileReporter.php');
require_once('reporting/SocketReporter.php');
require_once('markets/TestMarket.php');

abstract class ActionProcess {

    protected $reporter;
    protected $listener;
    protected $requiresListener = false;
    private $monitor = false;
    private $monitor_timeout = 20;
    private $fork = false;

    abstract public function getProgramOptions();
    abstract public function processOptions($options);

    abstract public function init();
    abstract public function run();
    abstract public function shutdown();

    protected $exchanges;

    private function processCommandLine()
    {
        $objOptions = $this->getProgramOptions();

        $shortopts = "";
        $longopts = array(
            'console',
            "mongodb",
            "file:",
            "monitor::",
            "fork",
            'socket:',
            'testmarket'
        );
        if(is_array($objOptions))
            $longopts = array_merge($longopts, $objOptions);

        /////////////////////////////////

        $options = getopt($shortopts, $longopts);

        /////////////////////////////////
        // Configure reporters
        $this->reporter = new MultiReporter();

        if(array_key_exists("mongodb", $options))
            $this->reporter->add(new MongoReporter());

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
        if(array_key_exists('testmarket', $options))
            $this->exchanges = array('TestMarket' => new TestMarket());
        else
        {
            $accountLoader = null;
            if(array_key_exists("mongodb", $options))
                $accountLoader = new MongoAccountLoader();
            else
                $accountLoader = new ConfigAccountLoader();

            $this->exchanges = $accountLoader->getAccounts();
        }

        ////////////////////////////////
        if(array_key_exists("monitor", $options)){
            $this->monitor = true;

            if(is_numeric($options['monitor']))
                $this->monitor_timeout = intval($options['monitor']);
        }

        if(array_key_exists("fork", $options))
            $this->fork = true;

        ////////////////////////////////////

        $this->processOptions($options);
    }

    private function initialize()
    {
        foreach($this->exchanges as $mkt)
            if($mkt instanceof ILifecycleHandler)
                $mkt->init();

        $this->init();
    }

    public function start()
    {
        syslog(LOG_INFO, get_class($this) . ' is starting');

        try{
            date_default_timezone_set('UTC');

            $this->processCommandLine();
        }catch(Exception $e){
            syslog(LOG_ERR, $e);
            exit(1);
        }

        ////////////////////////////////////////////////////////
        // Execute process according to setup
        // if not monitoring, run once and exit
        if($this->monitor == false){
            try{
                $this->initialize();
                $this->run();
                $this->shutdown();
            }catch(Exception $e){
                syslog(LOG_ERR, $e);
                exit(1);
            }
            exit;
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
            syslog(LOG_INFO, get_class($this) . ' - monitoring starting');
            $this->initialize();

            do {
                $this->run();
                sleep($this->monitor_timeout);
            }while($this->monitor);

            $this->shutdown();
            syslog(LOG_INFO, get_class($this) . ' - monitoring finished');
        }catch(Exception $e){
            syslog(LOG_ERR, $e);
            exit(1);
        }

    }
} 