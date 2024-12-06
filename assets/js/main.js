(($) => {
    $(document).ready(() => {

        let app, order, params, autoStarter;
        if (window.CryptoPayVars || window.CryptoPayLiteVars) {
            app = window.CryptoPayApp || window.CryptoPayLiteApp;
            order = window?.CryptoPayVars?.order || window?.CryptoPayLiteVars?.order;
            params = window?.CryptoPayVars?.params || window?.CryptoPayLiteVars?.params;

            if (!PMProCryptoPay.isLogged) {
                const checkEmailIsExists = async (email) => {
                    return new Promise((resolve) => {
                        $.ajax({
                            method: 'POST',
                            url: PMProCryptoPay.ajax_url,
                            dataType: 'json',
                            data: {
                                email,
                                nonce: PMProCryptoPay.nonce,
                                action: 'pmpro_cryptopay_check_email'
                            },
                            success(response) {
                                resolve(response);
                            },
                            error(error) {
                                alert(error.statusText);
                                resolve(true);
                            },
                        });
                    });
                }

                app.events.add('init', async (ctx) => {
                    const userEmail = $('#user_email').val().trim();
                    const userPass = $('#user_pass').val().trim();
                    if (!userEmail || !userPass) {
                        ctx = Object.assign(ctx, {
                            error: true,
                            message: PMProCryptoPay.lang.pleaseFill
                        });
                        return;
                    }

                    const res = await checkEmailIsExists(userEmail);

                    if (!res.success) {
                        ctx = Object.assign(ctx, {
                            error: true,
                            message: res.message || PMProCryptoPay.lang.emailExists
                        });
                        return;
                    }

                    app.dynamicData.add({
                        createUser: {
                            email: userEmail,
                            password: userPass
                        }
                    });
                    $('#user_email').prop('disabled', true);
                    $('#user_pass').prop('disabled', true);
                }, 'pmpro_checkout');

                app.events.add('paymentReset', () => {
                    $('#user_email').prop('disabled', false);
                    $('#user_pass').prop('disabled', false);
                }, 'pmpro_checkout');
            }

            let startedApp;
            autoStarter = (order, params) => {
                if (!startedApp) {
                    startedApp = app.start(order, params);
                } else {
                    startedApp.reStart(order, params);
                }
            }
        
            autoStarter(order, params);
        }
    
        $("#other_discount_code_button").click(function() {
            var discountCode = jQuery('#other_discount_code').val();
    
            if (!discountCode) return;
            
            var levelId = jQuery('#level').val();
    
            $.ajax({
                method: 'POST',
                url: PMProCryptoPay.ajax_url,
                dataType: 'json',
                data: {
                    levelId,
                    discountCode,
                    nonce: PMProCryptoPay.nonce,
                    action: 'pmpro_cryptopay_use_discount',
                },
                beforeSend() {
                    $("#PMProCryptoPayWrapper #cryptopay").hide();
                    $("#PMProCryptoPayWrapper").append(`
                        <div style="text-align: center;" id="pmpro-cryptopay-please-wait">
                            ${PMProCryptoPay.lang.pleaseWait}
                        </div>
                    `);
                },
                success(response) {
                    if (response.data && response.data.amount) {
                        params.discountCode = discountCode;
                        order.amount = response.data.amount;
                        autoStarter(order, params);
                    }
                    $("#PMProCryptoPayWrapper #cryptopay").show();
                    $("#PMProCryptoPayWrapper #pmpro-cryptopay-please-wait").remove();
                },
                error(error) {
                    alert(error.statusText);
                },
            });
        });
    });
})(jQuery);