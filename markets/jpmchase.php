<?php

class JPMChase implements IAccount
{
    private $mailbox;
    private $user;
    private $pwd;

    public function __construct($mailbox, $username, $password){
        $this->mailbox = $mailbox;
        $this->user = $username;
        $this->pwd = $password;
    }

    public function Name()
    {
        return 'JPMChase';
    }

    public function balances()
    {
        $conn = imap_open($this->mailbox, $this->user, $this->pwd);
        if($conn === false)
            throw new Exception('Could not connect to IMAP server for JPM balance');

        //default response
        $balances = array();
        $balances[Currency::USD] = 0;

        //get the most recent message to parse
        $msgCount = imap_num_msg($conn);
        if($msgCount > 0)
        {
            $bodyText = imap_fetchbody($conn,$msgCount,1.2);
            if(!strlen($bodyText)>0){
                $bodyText = imap_fetchbody($conn,$msgCount,1);
            }

            //parse message text for balance
            $res = preg_match('/End of day balance: \$([\d,.]+)/', $bodyText, $matches);
            if($res != 1)
                throw new Exception('Message retrieved does not contain account balance: ' . $bodyText);

            $balances[Currency::USD] = str_replace(',','', $matches[1]);
        }

        return $balances;
    }

    public function transactions(){}
}

?>