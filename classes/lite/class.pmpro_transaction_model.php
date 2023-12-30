<?php

use BeycanPress\CryptoPayLite\Models\AbstractTransaction;

class PMPro_Transaction_Model_Lite extends AbstractTransaction 
{
    public $addon = 'pmpro';
    
    public function __construct()
    {
        parent::__construct('pmpro_transaction');
    }
}
