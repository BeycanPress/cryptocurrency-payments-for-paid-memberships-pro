<?php

use BeycanPress\CryptoPayLite\Models\AbstractTransaction;

class PMPro_Transaction_Model_Lite extends AbstractTransaction 
{
    public $addon = 'lite_pmpro';
    
    public function __construct()
    {
        parent::__construct('lite_pmpro_transaction');
    }
}
