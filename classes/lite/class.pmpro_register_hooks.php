<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

use BeycanPress\CryptoPayLite\Loader;
use BeycanPress\CryptoPayLite\Services;
use BeycanPress\CryptoPayLite\PluginHero\Hook;
use BeycanPress\CryptoPayLite\Pages\TransactionPage;

// @phpcs:ignore
class PMPro_Register_Hooks_Lite
{
    /**
     * @return void
     */
    public function __construct()
    {
        if (class_exists(Loader::class)) {
            Services::registerAddon('pmpro_lite');

            if (is_admin()) {
                new TransactionPage(
                    esc_html__('PMPro transactions', 'cryptopay'),
                    'pmpro_lite',
                    9,
                    [
                        'orderId' => function ($tx) {
                            return '<a href="' . admin_url('admin.php?page=pmpro-orders&order=' . $tx->orderId) . '">' . $tx->orderId . '</a>';
                        }
                    ],
                    true,
                    ['updatedAt']
                );
            }

            Hook::addFilter('init_pmpro_lite', function (object $data) {
                global $pmpro_levels;

                if (!isset($pmpro_levels[$data->params->pmpro->levelId])) {
                    Response::error(esc_html__('The relevant level was not found!', 'pmpro-cryptopay'), 'LEVEL_NOT_FOUND');
                }

                return $data;
            });

            /**
             * @param object $order
             * @param object $level
             * @return void
             */
            function pmpro_check_discount_code_lite(object $order, object &$level): void
            {
                global $wpdb;
                if (isset($order->discountCode)) {
                    $codeCheck = pmpro_checkDiscountCode($order->discountCode, $level->id, true);
                    if ($codeCheck[0] == false) {
                        Response::error(esc_html__('Invalid discount code!', 'pmpro-cryptopay'));
                    }

                    $discountId = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($order->discountCode) . "' LIMIT 1");

                    $discountPrice = $wpdb->get_var("SELECT initial_payment  FROM $wpdb->pmpro_discount_codes_levels WHERE code_id = '" . esc_sql($discountId) . "' LIMIT 1");

                    $level->price = floatval($discountPrice);
                    $level->initial_payment = $level->price;
                    $level->billing_amount  = $level->price;
                }
            }

            Hook::addAction('payment_started_pmpro_lite', function (object $data): void {
                global $pmpro_levels;
                $currentUser = wp_get_current_user();

                $order = new \MemberOrder();
                $level = $pmpro_levels[$data->params->pmpro->levelId];
                pmpro_check_discount_code_lite($data->order, $level);

                if (empty($order->code)) {
                    $order->code = $order->getRandomCode();
                }

                // Set order values.
                $order->membership_id    = $level->id;
                $order->membership_name  = $level->name;
                $order->InitialPayment   = pmpro_round_price($level->initial_payment);
                $order->PaymentAmount    = pmpro_round_price($level->billing_amount);
                $order->ProfileStartDate = date_i18n("Y-m-d\TH:i:s", current_time("timestamp"));
                $order->BillingPeriod    = $level->cycle_period;
                $order->BillingFrequency = $level->cycle_number;
                if ($level->billing_limit) {
                    $order->TotalBillingCycles = $level->billing_limit;
                }

                // Set user info.
                $order->FirstName = get_user_meta($currentUser->ID, 'first_name', true);
                $order->LastName  = get_user_meta($currentUser->ID, 'last_name', true);
                $order->Email     = $currentUser->user_email;
                $order->Address1  = "";
                $order->Address2  = "";

                // Set other values.
                $order->billing          = new \stdClass();
                $order->billing->name    = $order->FirstName . " " . $order->LastName;
                $order->billing->street  = trim($order->Address1 . " " . $order->Address2);
                $order->billing->city    = "";
                $order->billing->state   = "";
                $order->billing->country = "";
                $order->billing->zip     = "";
                $order->billing->phone   = "";

                $order->gateway          = 'cryptopay_lite';
                $order->payment_type     = 'Cryptocurrency';
                $order->setGateway();

                // Set up level var.
                $order->getMembershipLevelAtCheckout();

                // Set tax.
                $initialTax   = $order->getTaxForPrice($order->InitialPayment);
                $recurringTax = $order->getTaxForPrice($order->PaymentAmount);

                // Set amounts.
                $order->initial_amount      = pmpro_round_price((float) $order->InitialPayment + (float) $initialTax);
                $order->subscription_amount = pmpro_round_price((float) $order->PaymentAmount + (float) $recurringTax);

                //just save, the user will go to PayPal to pay
                $order->status        = "review";
                $order->user_id       = $data->userId;
                $order->membership_id = $level->id;
                $order->payment_transaction_id = "PMPRO_CRYPTOPAY_LITE_" . $order->code;
                $order->saveOrder();

                $data->model->update(['orderId' => $order->id], ['hash' => $data->hash]);
            });

            Hook::addAction('payment_finished_pmpro_lite', function (object $data): void {
                global $pmpro_levels, $wpdb;

                $orderId = ($data->model->findOneBy(['hash' => $data->hash]))->orderId;
                $order = new \MemberOrder($orderId);
                if (!$order->id) {
                    return;
                }

                if (!$data->status) {
                    $order->status = "error";
                    $order->saveOrder();
                    return;
                }

                $level = $pmpro_levels[$data->params->pmpro->levelId];
                pmpro_check_discount_code_lite($data->order, $level);

                $startdate = current_time("mysql");
                if (!empty($level->expiration_number)) {
                    if ($level->expiration_period == 'Hour') {
                        $enddate =  date("Y-m-d H:i:s", strtotime("+ " . $level->expiration_number . " " . $level->expiration_period, current_time("timestamp")));
                    } else {
                        $enddate =  date("Y-m-d 23:59:59", strtotime("+ " . $level->expiration_number . " " . $level->expiration_period, current_time("timestamp")));
                    }
                } else {
                    $enddate = "NULL";
                }

                $discountCodeId = "";
                if (isset($data->order->discountCode)) {
                    $codeCheck = pmpro_checkDiscountCode($data->order->discountCode, $level->id, true);

                    if ($codeCheck[0] == false) {
                        $useDiscountCode = false;
                    } else {
                        $useDiscountCode = true;
                    }

                    // update membership_user table.
                    if (!empty($data->order->discountCode) && !empty($useDiscountCode)) {
                        $discountCodeId = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($data->order->discountCode) . "' LIMIT 1");

                        $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discountCodeId . "', '" . $data->userId . "', '" . intval($orderId) . "', '" . current_time("mysql") . "')");
                    }
                }

                $userLevel = array(
                    'user_id'         => $data->userId,
                    'membership_id'   => $level->id,
                    'code_id'         => $discountCodeId,
                    'initial_payment' => pmpro_round_price($level->initial_payment),
                    'billing_amount'  => pmpro_round_price($level->billing_amount),
                    'cycle_number'    => $level->cycle_number,
                    'cycle_period'    => $level->cycle_period,
                    'billing_limit'   => $level->billing_limit,
                    'trial_amount'    => pmpro_round_price($level->trial_amount),
                    'trial_limit'     => $level->trial_limit,
                    'startdate'       => $startdate,
                    'enddate'         => $enddate
                );

                pmpro_changeMembershipLevel($userLevel, $data->userId, 'changed');

                $order->status = "success";
                $order->saveOrder();
            });

            Hook::addFilter('payment_redirect_urls_pmpro_lite', function (object $data) {
                return [
                    'success' => pmpro_url("confirmation", "?level=" . $data->params->pmpro->levelId),
                    'failed' => pmpro_url("account")
                ];
            });
        }
    }
}

new PMPro_Register_Hooks_Lite();
