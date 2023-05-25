<?php

use BeycanPress\CryptoPay\Models\AbstractTransaction;

class PMPro_Transaction_Model extends AbstractTransaction 
{
    public $addon = 'pmpro';
    
    public function __construct()
    {
        parent::__construct('pmpro_transaction');
    }
}
