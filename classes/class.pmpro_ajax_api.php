<?php

use \BeycanPress\Http\Request;
use \BeycanPress\Http\Response;

class PMPro_Ajax_Api
{
    public function __construct()
    {
        add_action('wp_ajax_pmpro_cryptopay_use_discount' , [$this, 'pmpro_cryptopay_use_discount']);
        add_action('wp_ajax_nopriv_pmpro_cryptopay_use_discount' , [$this, 'pmpro_cryptopay_use_discount']);
    }

    /**
     * @return void
     */
    public function pmpro_cryptopay_use_discount() : void
    {
		global $pmpro_levels;
        
        check_ajax_referer('pmpro_cryptopay_use_discount', 'nonce');

        $request = new Request();
        $levelId = $request->getParam('levelId');
        $discountCode = $request->getParam('discountCode');

        if (!isset($pmpro_levels[$levelId])) {
            Response::error(esc_html__('Invalid level id!', 'pmpro-cryptopay'));
        } 

        $codeCheck = pmpro_checkDiscountCode($discountCode, $levelId, true);
        if ($codeCheck[0] == false) {
            Response::error(esc_html__('Invalid discount code!', 'pmpro-cryptopay'));
        }

        global $wpdb;
        $discountId = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($discountCode) . "' LIMIT 1");

        $discountPrice = $wpdb->get_var("SELECT initial_payment  FROM $wpdb->pmpro_discount_codes_levels WHERE code_id = '" . esc_sql($discountId) . "' LIMIT 1");

        Response::success(null, [
            'amount' => floatval($discountPrice),
        ]);
    }
}

new PMPro_Ajax_Api();