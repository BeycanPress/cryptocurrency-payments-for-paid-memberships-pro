<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

require_once PMPRO_DIR . '/classes/gateways/class.pmprogateway.php';

add_action('init', ['PMProGateway_cryptopay', 'init']);

use BeycanPress\CryptoPay\Helpers;
use BeycanPress\CryptoPay\Payment;
use BeycanPress\CryptoPay\PluginHero\Hook;
use BeycanPress\CryptoPay\Types\Order\OrderType;
use BeycanPress\CryptoPay\Types\Transaction\ParamsType;

// @phpcs:ignore
class PMProGateway_cryptopay extends PMProGateway
{
    /**
     * @param string $gateway
     */
    public string $gateway;

    /**
     * @param string $gateway
     */
    public function __construct(?string $gateway = null)
    {
        return parent::__construct($gateway);
    }

    /**
     * @return void
     */
    public static function init(): void
    {
        if (!is_user_logged_in()) {
            add_filter('pmpro_skip_account_fields', '__return_true');
        }

        add_filter('pmpro_gateways', ['PMProGateway_cryptopay', 'pmpro_gateways']);
        add_filter('pmpro_payment_options', ['PMProGateway_cryptopay', 'pmpro_payment_options']);
        add_filter('pmpro_required_billing_fields', ['PMProGateway_cryptopay', 'pmpro_required_billing_fields']);

        if (pmpro_getOption('gateway') == 'cryptopay') {
            add_filter('pmpro_billing_show_payment_method', '__return_false');
            add_filter('pmpro_include_billing_address_fields', '__return_false');
            add_filter('pmpro_include_payment_information_fields', '__return_false');
            add_action('pmpro_checkout_default_submit_button', ['PMProGateway_cryptopay', 'pmpro_checkout']);
        }
    }

    /**
     * @param array<string,string> $gateways
     * @return array<string,string>
     */
    public static function pmpro_gateways(array $gateways): array
    {
        if (empty($gateways['cryptopay'])) {
            $gateways['cryptopay'] = __('CryptoPay', 'pmpro-cryptopay');
        }

        return $gateways;
    }

    /**
     * @return array<string>
     */
    public static function getGatewayOptions(): array
    {
        return [
            'currency',
            'tax_state',
            'tax_rate'
        ];
    }

    /**
     * @param array<string> $options
     * @return array<string>
     */
    public static function pmpro_payment_options(array $options): array
    {
        return array_merge(self::getGatewayOptions(), $options);
    }

    /**
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public static function pmpro_required_billing_fields(array $fields): array
    {
        unset($fields['bfirstname']);
        unset($fields['blastname']);
        unset($fields['baddress1']);
        unset($fields['bcity']);
        unset($fields['bstate']);
        unset($fields['bzipcode']);
        unset($fields['bphone']);
        unset($fields['bemail']);
        unset($fields['bcountry']);
        unset($fields['CardType']);
        unset($fields['AccountNumber']);
        unset($fields['ExpirationMonth']);
        unset($fields['ExpirationYear']);
        unset($fields['CVV']);
        return $fields;
    }

    /**
     * @return void
     */
    public static function pmpro_checkout(): void
    {
        global $gateway, $pmpro_level, $discount_code;

        if ($gateway == 'cryptopay' && is_user_logged_in()) {
            ?>
            <div id="PMProCryptoPayWrapper">
                <?php
                    Hook::addFilter('lang', function ($lang) {
                        $lang['orderAmount'] = __('Level price:', 'pmpro-cryptopay');
                        return $lang;
                    });
                    echo (new Payment('pmpro'))
                    ->setOrder(OrderType::fromArray([
                        'amount' => (float) $pmpro_level->initial_payment,
                        'currency' => strtoupper(pmpro_getOption('currency'))
                    ]))
                    ->setParams(ParamsType::fromArray([
                        'levelId' => (int) $pmpro_level->id
                    ]))
                    ->setAutoStart(false)
                    ->html(loading:true);

                    self::pmpro_load_scripts();
                ?>
            </div>
            <?php
        } else {
            $discount_code_link = !empty($discount_code) ? '&discount_code=' . $discount_code : '';
            ?>
            <span class="<?php echo esc_attr(pmpro_get_element_class('pmpro_checkout-h3-msg')); ?>"><?php esc_html_e('Already have an account?', 'paid-memberships-pro'); ?> <a href="<?php echo esc_url(wp_login_url(apply_filters('pmpro_checkout_login_redirect', pmpro_url("checkout", "?level=" . $pmpro_level->id . $discount_code_link)))); ?>"><?php esc_html_e('Log in here', 'paid-memberships-pro'); ?></a></span>
            <?php
        }
    }

    /**
     * @return void
     */
    public static function pmpro_load_scripts(): void
    {
        if (!wp_script_is('pmpro_cryptopay_main')) {
            wp_enqueue_script(
                'pmpro_cryptopay_main',
                PMPRO_CRYPTOPAY_URL . 'assets/js/main.js',
                ['jquery', Helpers::getProp('mainJsKey')],
                PMPRO_CRYPTOPAY_VERSION,
                true
            );
            wp_localize_script('pmpro_cryptopay_main', 'PMProCryptoPay', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pmpro_cryptopay_use_discount'),
                'lang' => [
                    'pleaseWait' => __('Please wait...', 'pmpro-cryptopay'),
                ]
            ]);
        }
    }
}
