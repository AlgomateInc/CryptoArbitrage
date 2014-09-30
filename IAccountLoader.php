<?php

interface IAccountLoader {
    function getAccounts(array $mktFilter = null);
} 