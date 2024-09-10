/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Vault/js/view/payment/vault-enabler',
        'dnaPaymentsHostedFields',
        'mage/storage',
    ],
    function ($, Component, placeOrderAction, fullScreenLoader, VaultEnabler, hostedFields, storage) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Dna_Payment/payment/form',
                code: 'dna_payment',
                hostedFieldsInstance: null,
                orderId: null,
                paymentResponse: null,
                threeDModal: null,
            },
            initialize: function () {
                this._super();

                fullScreenLoader.startLoader();

                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());

                if (!this.hostedFieldsInstance) {
                    // Initialize hosted fields after the component is initialized
                    this.initHostedFields(this)
                        .then((hf) => {
                            this.hostedFieldsInstance = hf;

                            hf.on('dna-payments-three-d-secure-show', (data) => {
                                fullScreenLoader.stopLoader();
                                this.threeDModal.open();
                            });

                            hf.on('dna-payments-three-d-secure-hide', () => {
                                fullScreenLoader.startLoader();
                                this.threeDModal.close();
                            });

                            hf.on('change', () => {
                                const state = hf.getState();
                                if (state.cardInfo && state.cardInfo.type) {
                                    this.selectedCardType(state.cardInfo.type);
                                } else {
                                    this.selectedCardType(null);
                                }
                            });

                            $('#' + this.getCode() + '_hosted_fields_form').show();
                        })
                        .catch((e) => {
                            console.error('Hosted fields initialization failed', e);
                        })
                        .finally(() => {
                            fullScreenLoader.stopLoader();
                        });
                }

                return this;
            },
            getVaultCode: function () {
                return window.checkoutConfig.payment[this.getCode()].ccVaultCode;
            },
            placeOrder: async function (data, event) {
                let self = this;

                if (event) {
                    event.preventDefault();
                }

                if (await this.validate() && this.isPlaceOrderActionAllowed() === true) {
                    fullScreenLoader.startLoader();
                    this.isPlaceOrderActionAllowed(false);
                    this.getPlaceOrderDeferredObject().done(
                        function (orderId) {
                            self.orderId = orderId;
                            if (!self.paymentResponse) {
                                self.fetchPaymentData(orderId)
                                    .then(async function (response) {
                                        self.paymentResponse = response;
                                        const {paymentData, accessToken} = response;

                                        try {
                                            paymentData.merchantCustomData = JSON.stringify({
                                                storeCardOnFile: $('#' + self.getCode() + '_enable_vault').prop('checked')
                                            });
                                            await self.hostedFieldsInstance.submit({
                                                paymentData: paymentData,
                                                token: accessToken
                                            });
                                            window.location.href = paymentData.paymentSettings.returnUrl;
                                        } catch (error) {
                                            console.error('Failed to submit hosted fields data:', error);

                                            fullScreenLoader.stopLoader();
                                            self.isPlaceOrderActionAllowed(true);

                                            if (error.code === 'INVALID_CARD_DATA') {
                                                console.log('INVALID_CARD_DATA');
                                            } else {
                                                self.hostedFieldsInstance.clear();
                                            }
                                        }
                                    })
                                    .catch(function (error) {
                                        fullScreenLoader.stopLoader();
                                        self.isPlaceOrderActionAllowed(true);
                                    });
                            }
                        },
                    ).fail(
                        function () {
                            console.log('Failed to placed order.');

                            fullScreenLoader.stopLoader();
                            self.isPlaceOrderActionAllowed(true);
                        }
                    );

                    return true;
                } else {
                    console.log('Hosted fields validation failed.');

                    fullScreenLoader.stopLoader();
                    self.isPlaceOrderActionAllowed(true);
                }

                return false;
            },
            createThreeDSecureModal: function() {
                const modalId = 'dna-payment-three-d-modal';
                const modalClassName = 'dna-payment-modal-content';
                let modal = document.getElementById(modalId);
                let modalContent = document.querySelector('#' + modalId + ' .' + modalClassName);

                if (!modal) {
                    modal = document.createElement("div");
                    modal.id = modalId;
                    modal.className = "dna-payment-modal";

                    modalContent = document.createElement("div");
                    modalContent.className = "dna-payment-modal-content";

                    modal.appendChild(modalContent);

                    document.body.appendChild(modal);
                }

                this.threeDModal = {
                    content: modalContent,
                    open: function () {
                        modal.style.display = "block";
                    },
                    close: function () {
                        modal.style.display = "none";
                    }
                };
            },

            /**
             * Initialize the DNA Payments hosted fields asynchronously
             */
            initHostedFields: async function (self) {
                const {accessToken, isTest} = await this.fetchDumbToken();

                this.createThreeDSecureModal();

                const config = {
                    isTest,
                    accessToken,
                    styles: {
                        input: {
                            'font-size': '16px',
                            'font-family': 'Roboto'
                        },
                        '.invalid': {
                            'color': 'red'
                        }
                    },
                    fontNames: ['Roboto'],
                    threeDSecure: {
                        container: self.threeDModal.content
                    },
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
                            placeholder: '123'
                        }
                    }
                };

                return window.dnaPayments.hostedFields.create(config);
            },

            fetchPaymentData: function (orderId) {
                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: '/rest/V1/dna-payment/get-order-payment-data?orderId=' + orderId,
                        type: 'get',
                        success: function (res) {
                            const {paymentData, auth, adminOrderViewUrl} = (function () {
                                if (Array.isArray(res)) {
                                    const [p, a, t, i, u] = res
                                    return {
                                        paymentData: p,
                                        auth: a,
                                        isTestMode: t,
                                        integrationType: i,
                                        adminOrderViewUrl: u
                                    }
                                }
                                return res || {}
                            })()
                            resolve({paymentData, accessToken: auth.access_token, adminOrderViewUrl});
                        },
                        error: function (err) {
                            console.error('Failed to fetch payment data:', error);

                            reject(err);
                        }
                    })
                })
            },

            /**
             * Fetches the dumb token needed for initializing hosted fields
             */
            fetchDumbToken: async function () {
                return new Promise((resolve, reject) => {
                    storage.get('/rest/V1/dna-payment/get-dna-dumb-auth-data')
                        .done((res) => {
                            const result = (function () {
                                if (Array.isArray(res)) {
                                    const [a, t] = res
                                    return {accessToken: a, isTest: t}
                                }
                                return res || {}
                            })()

                            resolve(result)
                        })
                        .fail((err) => {
                            console.error('Fetch dumb token failed:', err)
                            reject(err)
                        })
                })
            },
            getCode: function () {
                return this.code;
            },
            _getElement: function (field) {
                return $(`#${this.getCode()}_${field}`)[0];
            },
            getPlaceOrderDeferredObject: function () {
                return $.when(placeOrderAction(this.getData()));
            },
            /**
             * @returns {Object}
             */
            getData: function () {
                var data = this._super();

                this.vaultEnabler.visitAdditionalData(data);

                return data;
            },
            validate: async function () {
                if (this.hostedFieldsInstance) {
                    const validateResponse = await this.hostedFieldsInstance.validate();

                    return validateResponse.isValid;
                }

                return true;
            },
            /**
             * @returns {Bool}
             */
            isVaultEnabled: function () {
                return this.vaultEnabler.isVaultEnabled();
            },
            getIcons: function (type) {
                return window.checkoutConfig.payment.dna_payment.icons.hasOwnProperty(type) ?
                    window.checkoutConfig.payment.dna_payment.icons[type]
                    : false;
            },
        });
    }
);