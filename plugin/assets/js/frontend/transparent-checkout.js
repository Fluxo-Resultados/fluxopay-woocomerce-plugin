/*global wc_fluxopay_params, S2PDirectPayment, wc_checkout_params */
(function ($) {
    'use strict';

    $(function () {

        var fluxopay_submit = false;


        function fluxoPaySetcardCardBrand(brand) {
            $('#fluxopay-card-form').attr('data-card-brand', brand);
        }

        function fluxoPayGetPriceText(installment) {
            var installmentParsed = 'R$ ' + parseFloat(installment.installmentAmount, 10).toFixed(2).replace('.', ',').toString();

            return installment.quantity + 'x de ' + installmentParsed;
        }

        function fluxoPayGetInstallmentOption(installment) {
            return '<option value="' + installment.quantity + '" data-installment-value="' + installment.installmentAmount + '">' + fluxoPayGetPriceText(installment) + '</option>';
        }

        function ShowMessageError(error) {
            var wrapper = $('#fluxopay-card-form');

            $('.woocommerce-error', wrapper).remove();
            wrapper.prepend('<div class="woocommerce-error" style="margin-bottom: 0.5em !important;">' + error + '</div>');
        }

        function HidePaymentMethods() {
            var paymentMethods = $('#fluxopay-payment-methods');
            if (1 === $('input[type=radio]', paymentMethods).length) {
                paymentMethods.hide();
            }
        }

        function HidePaymentForm(method) {
            // window.alert( method );
            $('.fluxopay-method-form').hide();
            $('#fluxopay-payment-methods li').removeClass('active');
            $('#fluxopay-' + method + '-form').show();
            $('#fluxopay-payment-method-' + method).parent('label').parent('li').addClass('active');
        }

        function Init() {
            HidePaymentMethods();

            $('#fluxopay-payment-form').show();

            HidePaymentForm($('#fluxopay-payment-methods input[type=radio]:checked').val());

            var MaskBehavior = function (val) {
                    return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00009';
                },
                maskOptions = {
                    onKeyPress: function (val, e, field, options) {
                        field.mask(MaskBehavior.apply({}, arguments), options);
                    }
                };
        }

        function fluxoPayformHandler() {
            if (fluxopay_submit) {
                fluxopay_submit = false;

                return true;
            }

            if (!$('#payment_method_fluxopay').is(':checked')) {
                return true;
            }

            if ('card' !== $('body li.payment_method_fluxopay input[name=fluxopay_payment_method]:checked').val()) {
                $('form.checkout, form#order_review').append($('<input name="fluxopay_sender_hash" type="hidden" />').val(S2PDirectPayment.getSenderHash()));

                return true;
            }

            var form = $('form.checkout, form#order_review'),
                cardCardForm = $('#fluxopay-card-form', form),
                error = false,
                errorHtml = '',
                holder = $('#fluxopay-card-holder-name').val(),
                cardNumber = $('#fluxopay-card-number', form).val().replace(/[^\d]/g, ''),
                cvv = $('#fluxopay-card-cvc', form).val(),
                expiration = $('#fluxopay-card-expiry', form).val(),
                expirationMonth = expiration.replace(/[^\d]/g, '').substr(0, 2),
                expirationYear = expiration.replace(/[^\d]/g, '').substr(2),
                installments = $('#fluxopay-card-installments', form),
                today = new Date();

            errorHtml += '<ul>';

            if (2 !== expirationMonth.length || 4 !== expirationYear.length) {
                errorHtml += '<li>' + wc_fluxopay_params.invalid_expiry + '</li>';
                error = true;
            }

            if ((2 === expirationMonth.length && 4 === expirationYear.length) && (expirationMonth > 12 || expirationYear <= (today.getFullYear() - 1) || expirationYear >= (today.getFullYear() + 20) || (expirationMonth < (today.getMonth() + 2) && expirationYear.toString() === today.getFullYear().toString()))) {
                errorHtml += '<li>' + wc_fluxopay_params.expired_date + '</li>';
                error = true;
            }

            if ('0' === installments.val()) {
                errorHtml += '<li>' + wc_fluxopay_params.empty_installments + '</li>';
                error = true;
            }

            errorHtml += '</ul>';
            if (!error) {

                $('input[name=fluxopay_card_hash], input[name=fluxopay_card_hash], input[name=fluxopay_installment_value]', form).remove();

                form.append($('<input name="fluxopay-card-holder-name" type="hidden" />').val(holder));
                form.append($('<input name="fluxopay-card-number" type="hidden" />').val(cardNumber));
                form.append($('<input name="fluxopay-card-expiry-field" type="hidden" />').val(expiration));
                form.append($('<input name="fluxopay-card-cvc" type="hidden" />').val(cvv));
                form.append($('<input name="fluxopay-card-installments" type="hidden" />').val(installments.val()));

                fluxopay_submit = true;
                form.submit();
            } else {
                ShowMessageError(errorHtml);
            }

            return false;
        }

        Init();

        $('body').on('updated_checkout', function () {
            Init();
        });

        $('body').on('click', '#fluxopay-payment-methods input[type=radio]', function () {
            HidePaymentForm($(this).val());
        });

        $('body').on('updated_checkout', function () {
            var field = $('body #fluxopay-card-number');

            if (0 < field.length) {
                field.focusout();
            }
        });

        $('body').on('focus', '#fluxopay-card-number, #fluxopay-card-expiry', function () {
            $('#fluxopay-card-form .woocommerce-error').remove();
        });

        $('form.checkout').on('checkout_place_order_fluxopay', function () {
            return fluxoPayformHandler();
        });

        $('body').on('fluxopay_card_brand', function (event, brand) {
            if ('error' !== brand) {
                S2PDirectPayment.getInstallments({
                    amount: $('body #fluxopay-payment-form').data('cart_total'),
                    brand: brand,
                    success: function (data) {
                        var instalmments = $('body #fluxopay-card-installments');

                        if (false === data.error) {
                            instalmments.empty();
                            instalmments.removeAttr('disabled');
                            instalmments.append('<option value="0">--</option>');

                            $.each(data.installments[brand], function (index, installment) {
                                instalmments.append(fluxoPayGetInstallmentOption(installment));
                            });
                        } else {
                            fluxoPayAddErrorMessage(wc_fluxopay_params.invalid_card);
                        }
                    },
                    error: function () {
                        fluxoPayAddErrorMessage(wc_fluxopay_params.invalid_card);
                    }
                });
            } else {
                fluxoPayAddErrorMessage(wc_fluxopay_params.invalid_card);
            }
        });


        $('form#order_review').submit(function () {
            return fluxoPayformHandler();
        });

    });

}(jQuery));

function MaskcpfCnpj(e) {
    v = e.value;
    v = v.replace(/\D/g, "");

    if (v.length <= 11) { //CPF
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
    } else { //CNPJ
        v = v.replace(/^(\d{2})(\d)/, "$1.$2");
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
        v = v.replace(/\.(\d{3})(\d)/, ".$1/$2");
        v = v.replace(/(\d{4})(\d)/, "$1-$2");
    }

    e.value = v;
};

function IsNumber(evt) {
    evt = (evt) ? evt : window.event;
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    }
    return true;
}

function ExpiryMask(evt, e) {

    evt = (evt) ? evt : window.event;
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    }

    v = e.value;
    v = v.replace(/\D/g, "");
    v = v.replace(/(\d{2})(\d{0})/, "$1/$2");

    e.value = v;

    return true;
}