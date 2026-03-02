/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'dnaPaymentsHostedFields',
        'mage/storage',
        'mage/translate',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'Magento_Vault/js/view/payment/vault-enabler',
        'Magento_Checkout/js/model/quote',
        'Dna_Payment/js/api',
        'Magento_Payment/js/view/payment/cc-form'
    ],
    function ($, hostedFields, storage, $t, placeOrderAction, fullScreenLoader, globalMessageList, VaultEnabler, quote, api, Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Dna_Payment/payment/form-hosted',
                code: 'dna_payment',
                hostedFieldsInstance: null,
                paymentResponse: null,
                threeDModal: null,
            },
            initialize: function () {
                this._super();

                fullScreenLoader.startLoader();

                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());

                if (this.hostedFieldsInstance) {
                    this.hostedFieldsInstance.clear();
                    this.hostedFieldsInstance.destroy();
                    this.hostedFieldsInstance = null;
                }

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
            showError: function (errorMessage) {
                globalMessageList.addErrorMessage({
                    message: errorMessage
                });
                window.scrollTo({top: 0, behavior: 'smooth'});
            },
            placeOrder: async function (data, event) {
                let self = this;

                if (event) {
                    event.preventDefault();
                }

                if (await this.validate() && this.isPlaceOrderActionAllowed() === true) {
                    fullScreenLoader.startLoader();
                    self.isPlaceOrderActionAllowed(false);

                    try {
                        var quoteId = quote.getQuoteId();
                        var response = await api.fetchQuotePaymentData(quoteId);
                        var paymentData = response.paymentData;
                        var accessToken = response.auth.access_token;

                        if (self.isVaultEnabled()) {
                            var customData = JSON.parse(paymentData.merchantCustomData || '{}');
                            customData.storeCardOnFile = $('#' + self.getCode() + '_enable_vault').prop('checked');
                            paymentData.merchantCustomData = JSON.stringify(customData);
                        }

                        await self.hostedFieldsInstance.submit({
                            paymentData: paymentData,
                            token: accessToken
                        });

                        await self.getPlaceOrderDeferredObject();

                        window.location.href = paymentData.paymentSettings.returnUrl;
                    } catch (error) {
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);

                        if (error.code !== 'INVALID_CARD_DATA' && self.hostedFieldsInstance) {
                            self.hostedFieldsInstance.clear();
                        }
                        self.showError((error && error.message) || $t('Payment failed. Please try again.'));
                    }
                } else {

                    fullScreenLoader.stopLoader();
                    self.isPlaceOrderActionAllowed(true);
                    self.showError($t('Please check your card details.'));
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
                return this.vaultEnabler.isVaultEnabled() && window.checkoutConfig.payment.dna_payment.isVaultEnabled;
            },
            getIcons: function (type) {
                return window.checkoutConfig.payment.dna_payment.icons.hasOwnProperty(type) ?
                    window.checkoutConfig.payment.dna_payment.icons[type]
                    : false;
            },
        });
    }
);