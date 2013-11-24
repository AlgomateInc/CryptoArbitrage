<?php

require('config.php');
require('reporting/ConsoleReporter.php');
require('reporting/MongoReporter.php');

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

    private function processCommandLine()
    {
        $objOptions = $this->getProgramOptions();

        $shortopts = "";
        $longopts = array(
            "mongodb",
            "monitor",
            "fork"
        );
        $longopts = array_merge($longopts, $objOptions);

        /////////////////////////////////

        $options = getopt($shortopts, $longopts);

        if(array_key_exists("mongodb", $options))
            $this->$reporter = new MongoReporter();
        else
            $this->$reporter = new ConsoleReporter();

        if(array_key_exists("monitor", $options))
            $this->$monitor = true;
        if(array_key_exists("fork", $options))
            $this->$fork = true;

        ////////////////////////////////////

        $this->processOptions($options);
    }

    private function execute()
    {
        try{
            $this->init();
            $this->run();
            $this->shutdown();
        }catch(Exception $e){
            syslog(LOG_ERR, $e);
        }
    }

    public function start()
    {
        $this->processCommandLine();

        ////////////////////////////////////////////////////////
        // Execute process according to setup
        // if not monitoring, run once and exit
        if($this->monitor == false){
            $this->execute();
            exit;
        }

        //if we are here, we are monitoring.
        //fork the process depending on setup and loop
        if($this->$fork){
            $pid = pcntl_fork();

            if($pid == -1){
                die('Could not fork process for monitoring!');
            }else if ($pid){
                //parent process can now exit
                exit;
            }
        }

        //perform the monitoring loop
        do {
            $this->execute();
            sleep($this->$monitor_timeout);
        }while($this->$monitor);

    }
} 