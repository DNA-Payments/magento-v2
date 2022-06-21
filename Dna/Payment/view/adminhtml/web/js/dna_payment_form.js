/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/*browser:true*/
/*global define*/
define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'mage/storage',
    'dnaPaymentsHostedFields'
], function ($, alert, $t, storage, hostedFields) {
    'use strict';

    let orderId = null;
    let paymentResponse = null;
    let isLoading = false;

    const $selector = $('#edit_form');
    const startLoader = () => {
        isLoading = true;
        $('body').trigger('processStart');
    };
    const stopLoader = () => {
        isLoading = false;
        $('body').trigger('processStop');
    };

    const error = (message, shouldStopLoder = false) => {
        alert({
            title: $t('Payment Unsuccessful'),
            content: message
        });
        if (shouldStopLoder) {
            stopLoader();
        }
    };

    $.widget('mage.dnaPaymentForm', {
        options: {
            code: 'dna_payment',
            paymentMethod: '',
            hostedFieldsInstance: null,
        },

        _disable: function () {
            $selector.find('.admin__payment-methods > .admin__field-option > input[type="radio"]').attr('disabled', 'disabled');
            $selector.find('.order-items button').attr('disabled', 'disabled');
            
            $selector.find('.order-items button')
                .attr('disabled', 'disabled')
                .addClass('tooltip-toggle').each(function () {
                    addTooltip($(this), $t('Editing the order is unavailable. Please cancel this order and create a new one.'));
                });

            $selector.find('.admin__payment-methods > .admin__field-option > input[type="radio"]')
                .attr('disabled', 'disabled')
                .each((function () {
                    const $option = $(this).parent();
                    const $container = $(document.createElement('div'));
                    $container.html($option.html());
                    $option.html('').append($container);
                    addTooltip($container, $t('Changing the payment method is unavailable. Please cancel this order and create a new one.'));
                }));
        },

        /**
         * Handler for form submit.
         *
         * @param {Object} event
         * @param {String} method
         */
        _setPlaceOrderHandler: function (event, method) {
            this.options.paymentMethod = method

            this._prepare();
        },

        _prepare: function() {
            if (isLoading) {
                return;
            }

            if (this.options.code === this.options.paymentMethod) {
                $selector.off('submitOrder');
                $selector.off('beforeSubmitOrder.' + this.options.code);
                $selector.off('submitOrder.' + this.options.code);
                $selector.on('submitOrder.' +  this.options.code, this._placeOrderHandler.bind(this));

                if (!this.options.hostedFieldsInstance) {
                    startLoader();
                    initHostedFields(this)
                        .then((hf) => {
                            this.options.hostedFieldsInstance = hf;
                            this.renderOrDestroyHostedFields();
                        })
                        .catch((e) => {
                            console.error(e);
                            error(e.message);
                        })
                        .finally(() => stopLoader());
                } else {
                    this.renderOrDestroyHostedFields();
                }
            }
        },

        renderOrDestroyHostedFields: function () {
            const isAllowedMoto = this.options.hostedFieldsInstance.configuration?.paymentMethodsSettings?.bankCard?.allowMoto;
            if (isAllowedMoto) {
                $selector.find('#dna_payment_hosted-fields').show();
            } else {
                this.options.hostedFieldsInstance.destroy();
            }
        },

        _getElement: function (field) {
            return $('#' + this.options.code + '_' + field)[0]
        },

        /**
         * Handler for form submit to call gateway for credit card validation.
         *
         * @param {Event} event
         * @return {Boolean}
         * @private
         */
        _placeOrderHandler: function (event) {
            if ($selector.valid()) {
                this._orderSave();
            } else {
                stopLoader();
            }
            event.stopImmediatePropagation();

            return false;
        },

        _orderSave: async function () {
            isLoading = true;

            try {
                if (!orderId) {
                    orderId = await createOrder(this);
                }

                this._disable();
    
                if (!paymentResponse) {
                    paymentResponse = await fetchPaymentData(orderId);
                }
    
                const { paymentData, accessToken, adminOrderViewUrl } = paymentResponse;
                paymentData.entryMode = $selector.find(`[name="${this.options.code}_entry_mode"]:checked`).val();
    
                await this.options.hostedFieldsInstance.submit({ paymentData, token: accessToken });
                window.location.href = adminOrderViewUrl;    
            } catch (err) {
                console.error(err);
                error(err.message);
                if (!err.code === 'NOT_VALID_CARD_DATA') {
                    this.options.hostedFieldsInstance.clear();
                }
            }
            stopLoader();
        },

        _create: function() {
            this.options.paymentMethod = window.order.paymentMethod;
            $selector.on('changePaymentMethod', this._setPlaceOrderHandler.bind(this));
            this._prepare();
        }
   });

    const fetchDumbToken = () => {
        return new Promise((resolve, reject) => {
            storage.get('/rest/V1/dna-payment/get-dna-dumb-auth-data')
                .done((res) => {
                    const result = (function() {
                        if (Array.isArray(res)) {
                            const [a, t] = res
                            return { accessToken: a, isTest: t }
                        }
                        return res || {}
                    })()

                    resolve(result)
                })
                .fail((err) => {
                    console.error(err)
                    reject(err)
                })
        })
    }

    const fetchPaymentData = (orderId) => {
        return new Promise((resolve, reject) => {
                $.ajax({
                    url: '/rest/V1/dna-payment/get-dna-payment-data?orderId=' + orderId,
                    type: 'get',
                    success: function (res) {
                        const { paymentData, auth, adminOrderViewUrl } = (function() {
                            if (Array.isArray(res)) {
                                const [p, a, t, i, u] = res
                                return { paymentData: p, auth: a, isTestMode: t, integrationType: i, adminOrderViewUrl: u }
                            }
                            return res || {}
                        })()
                        resolve({ paymentData, accessToken: auth.access_token, adminOrderViewUrl });
                    },
                    error: function (err) {
                        console.error(err);
                        reject(err);
                    }
                })
        })
    }

    const cancelOrder = (orderId) => {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: '/rest/V1/dna-payment/cancel-order?orderId=' + orderId,
                type: 'get',
                success: function (res) {
                    resolve();
                },
                error: function (err) {
                    console.error(err);
                    reject(err);
                }
            });
        });
    }

    const createOrder = (self) => {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: $selector.attr('action'),
                type: 'post',
                context: self,
                data: $selector.serialize(),
                dataType: 'html',

                success: function (data) {
                    const orderId = data.match(/<div id="order_id_for_dna_payment" style="display:none">(.*)<\/div>/)[1]
                    resolve(orderId)
                },

                error: function (err) {
                    console.error(err);
                    reject(err);
                }
            })
        })
    }

    const initHostedFields = async (self) => {
        const { accessToken, isTest } = await fetchDumbToken();
        const config = {
            isTest,
            accessToken,
            styles: {
                input: {
                    'font-size': '14px',
                    'font-family': 'Roboto'
                },
                '.invalid': {
                    'color': 'red'
                }
            },
            fontNames: ['Roboto'],
            fields: {
                cardholderName: {
                    container: self._getElement('cc_name'),
                    placeholder: 'Cardholder name'
                },
                cardNumber: {
                    container: self._getElement('cc_number'),
                    placeholder: 'Card number'
                },
                expirationDate: {
                    container: self._getElement('cc_exp_date'),
                    placeholder: 'Expiry date'
                },
                cvv: {
                    container: self._getElement('cc_cid'),
                    placeholder: 'CSC/CVV'
                }
            }
        }

        return window.dnaPayments.hostedFields.create(config)
    }

    const addTooltip = ($elem, text) => {
        const $elemTooltip = $(document.createElement('div')).addClass('dna-tooltip');
        $elem.parent().append($elemTooltip);
        $elem.detach().appendTo($elemTooltip);
        $elemTooltip.append('<div class="tooltip-content">' + text + '</div>')
    }

    return $.mage.dnaPaymentForm;
});
