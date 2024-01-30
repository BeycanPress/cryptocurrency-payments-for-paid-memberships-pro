(($) => {
    $(document).ready(() => {

        let order, params;
        if (window.CryptoPayVars) {
            order = CryptoPayVars.order;
            params = CryptoPayVars.params;
        
            let startedApp;
            const autoStarter = (order, params) => {
                if (!startedApp) {
                    startedApp = window.CryptoPayApp.start(order, params);
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
                        if (window.CryptoPayApp) {
                            params.discountCode = discountCode;
                            order.amount = response.data.amount;
                            autoStarter(order, params);
                        } else if (window.CryptoPayLite) {
                            CryptoPayLiteApp.reset();
                            CryptoPayLite.order.amount = response.data.amount;
                            CryptoPayLite.order.discountCode = discountCode;
                            initCryptoPayLite("cryptopay", CryptoPayLite);
                        }
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