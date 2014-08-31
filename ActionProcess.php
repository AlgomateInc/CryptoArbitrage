<?php

require_once('common.php');
require_once('ConfigAccountLoader.php');
require_once('reporting/ConsoleReporter.php');
require_once('reporting/MongoReporter.php');
require_once('reporting/FileReporter.php');

abstract class ActionProcess {

    protected $reporter;
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
            "mongodb",
            "file:",
            "monitor::",
            "fork"
        );
        if(is_array($objOptions))
            $longopts = array_merge($longopts, $objOptions);

        /////////////////////////////////

        $options = getopt($shortopts, $longopts);

        if(array_key_exists("mongodb", $options))
            $this->reporter = new MongoReporter();
        elseif(array_key_exists("file", $options) && isset($options['file']))
            $this->reporter = new FileReporter($options['file']);
        else
            $this->reporter = new ConsoleReporter();

        $accountLoader = new ConfigAccountLoader();
        $this->exchanges = $accountLoader->getAccounts();

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

    public function start()
    {
        $this->processCommandLine();

        ////////////////////////////////////////////////////////
        // Execute process according to setup
        // if not monitoring, run once and exit
        if($this->monitor == false){
            try{
                $this->init();
                $this->run();
                $this->shutdown();
            }catch(Exception $e){
                syslog(LOG_ERR, $e);
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
            $this->init();

            do {
                $this->run();
                sleep($this->monitor_timeout);
            }while($this->monitor);

            $this->shutdown();
        }catch(Exception $e){
            syslog(LOG_ERR, $e);
        }

    }
} 