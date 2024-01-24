<?php

declare(strict_types=1);

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable PSR12.Files.FileHeader
// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable Generic.Files.InlineHTML

/**
 * Plugin Name: Paid Memberships Pro - CryptoPay Gateway
 * Version:     1.0.3
 * Plugin URI:  https://beycanpress.com/cryptopay
 * Description: Adds CryptoPay as a gateway option for Paid Memberships Pro.
 * Author:      BeycanPress LLC
 * Author URI:  https://beycanpress.com
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pmpro-cryptopay
 * Tags: Cryptopay, Cryptocurrency, WooCommerce, WordPress, MetaMask, Trust, Binance, Wallet, Ethereum, Bitcoin, Binance smart chain, Payment, Plugin, Gateway, Moralis, Converter, API, coin market cap, CMC
 * Requires at least: 5.0
 * Tested up to: 6.4.2
 * Requires PHP: 7.4
*/

use BeycanPress\CryptoPay\Loader;
use BeycanPress\CryptoPay\PluginHero\Hook;
use BeycanPress\CryptoPayLite\Loader as LiteLoader;
use BeycanPress\CryptoPayLite\PluginHero\Hook as LiteHook;

define('PMPRO_CRYPTOPAY_FILE', __FILE__);
define('PMPRO_CRYPTOPAY_VERSION', '1.0.3');
define('PMPRO_CRYPTOPAY_URL', plugin_dir_url(__FILE__));

register_activation_hook(PMPRO_CRYPTOPAY_FILE, function (): void {
    if (class_exists(Loader::class)) {
        require_once __DIR__ . '/classes/pro/class.pmpro_transaction_model.php';
        (new PMPro_Transaction_Model())->createTable();
    }
    if (class_exists(LiteLoader::class)) {
        require_once __DIR__ . '/classes/lite/class.pmpro_transaction_model.php';
        (new PMPro_Transaction_Model_Lite())->createTable();
    }
});

/**
 * Add models to the plugin.
 * @return void
 */
function pmpro_cryptopay_addModels(): void
{
    if (class_exists(Loader::class)) {
        require_once __DIR__ . '/classes/pro/class.pmpro_transaction_model.php';
        Hook::addFilter('models', function ($models) {
            return array_merge($models, [
                'pmpro' => new PMPro_Transaction_Model()
            ]);
        });
    }

    if (class_exists(LiteLoader::class)) {
        require_once __DIR__ . '/classes/lite/class.pmpro_transaction_model.php';
        LiteHook::addFilter('models', function ($models) {
            return array_merge($models, [
                'pmpro_lite' => new PMPro_Transaction_Model_Lite()
            ]);
        });
    }
}

pmpro_cryptopay_addModels();

add_action('plugins_loaded', function (): void {
    pmpro_cryptopay_addModels();

    load_plugin_textdomain('pmpro-cryptopay', false, basename(__DIR__) . '/languages');

    if (defined('PMPRO_DIR') == false) {
        add_action('admin_notices', function (): void {
            $class = 'notice notice-error';
            $message = sprintf(esc_html__('Paid Memberships Pro - CryptoPay Gateway: This plugin is an extra feature plugin so it cannot do anything on its own. It needs Paid Memberships Pro to work. You can download Paid Memberships Pro by %s.', 'pmpro-cryptopay'), '<a href="https://wordpress.org/plugins/paid-memberships-pro/" target="_blank">' . esc_html__('clicking here', 'pmpro-cryptopay') . '</a>');
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        });
        return;
    }

    if ((class_exists(Loader::class) || class_exists(LiteLoader::class))) {
        require_once __DIR__ . '/classes/class.pmpro_ajax_api.php';

        if (class_exists(Loader::class)) {
            require_once __DIR__ . '/classes/pro/class.pmpro_register_hooks.php';
            require_once __DIR__ . '/classes/pro/class.pmprogateway_cryptopay.php';
        }

        if (class_exists(LiteLoader::class)) {
            require_once __DIR__ . '/classes/lite/class.pmpro_register_hooks.php';
            require_once __DIR__ . '/classes/lite/class.pmprogateway_cryptopay.php';
        }

        add_action('admin_footer', function (): void {
            ?>
            <script>
                jQuery(document).ready(function() {
                    function customShowHideCryptopayOptions() {
                        function justShowForCryptoPay() {
                            jQuery('.gateway_cryptopay,.gateway_cryptopay_lite').show();
                            jQuery('#gateway_environment').closest('tr').hide();
                            let parent = jQuery('#use_ssl').closest('tr');
                            parent.hide();
                            parent.prev().hide();
                            parent.next().hide();
                            parent.next().next().hide();
                        }
    
                        if (jQuery('#gateway').val() == 'cryptopay' || jQuery('#gateway').val() == 'cryptopay_lite') {
                            justShowForCryptoPay();
                        }
                        jQuery(document).on('change', '#gateway', function() {
                            jQuery('#gateway_environment').closest('tr').show();
                            let parent = jQuery('#use_ssl').closest('tr');
                            parent.show();
                            parent.prev().show();
                            parent.next().show();
                            parent.next().next().show();
                            if (jQuery('#gateway').val() == 'cryptopay' || jQuery('#gateway').val() == 'cryptopay_lite') {
                                justShowForCryptoPay();
                            }
                        });
                    }
                    customShowHideCryptopayOptions();
                });
            </script>
            <?php
        });
    } else {
        add_action('admin_notices', function (): void {
            ?>
                <div class="notice notice-error">
                    <p><?php echo sprintf(esc_html__('Paid Memberships Pro - CryptoPay Gateway: This plugin is an extra feature plugin so it cannot do anything on its own. It needs CryptoPay to work. You can buy CryptoPay by %s.', 'pmpro-cryptopay'), '<a href="https://beycanpress.com/cryptopay/?utm_source=wp_org_plugins&utm_medium=pmpro" target="_blank">' . esc_html__('clicking here', 'pmpro-cryptopay') . '</a>'); ?></p>
                </div>
            <?php
        });
    }
});
