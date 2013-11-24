<?php

interface IAccount {

    public function Name();
    public function balances();
    public function transactions($sinceDate);

}