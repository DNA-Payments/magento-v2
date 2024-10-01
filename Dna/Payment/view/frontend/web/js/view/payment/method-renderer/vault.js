/*browser:true*/
/*global define*/

define([
    'jquery',
    'Magento_Vault/js/view/payment/method-renderer/vault',
    'Magento_Checkout/js/action/place-order',
    'dnaPaymentsHostedFields',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/storage',
], function (
    $,
    VaultComponent,
    placeOrderAction,
    hostedFields,
    fullScreenLoader,
    storage,
) {
    'use strict';

    return VaultComponent.extend({
        defaults: {
            template: 'Dna_Payment/payment/cc/vault',
            imports: {
                onActiveChange: 'active'
            },
            hostedFieldsInstance: null,
            threeDModal: null,
            paymentResponse: null,
        },

        initialize: function () {
            this._super();

            console.log('initilize saved cards');

            return this;
        },

        /**
         * Get last 4 digits of card
         * @returns {String}
         */
        getMaskedCard: function () {
            return this.details.maskedCC;
        },

        /**
         * Get expiration date
         * @returns {String}
         */
        getExpirationDate: function () {
            return this.details.expirationDate;
        },

        /**
         * Get card type
         * @returns {String}
         */
        getCardType: function () {
            return this.details.type;
        },

        /**
         * @returns {String}
         */
        getToken: function () {
            return this.publicHash;
        },

        getCode: function () {
            return 'dna_payment';
        },

        /**
         * Fired whenever a payment option is changed.
         * @param isActive
         */
        onActiveChange: function (isActive) {
            if (!isActive) {
                return;
            }

            fullScreenLoader.startLoader();
            this.isPlaceOrderActionAllowed(false);

            if (this.hostedFieldsInstance) {
                this.hostedFieldsInstance.destroy();
                this.hostedFieldsInstance = null;
            }

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

                    const card = {
                        merchantTokenId: this.merchantTokenId,
                        panStar: this.details.panStar,
                        cardSchemeId: this.details.cardSchemeId,
                        cardSchemeName: this.details.cardSchemeName,
                        cardName: this.details.cardholderName,
                        expiryDate: this.details.expirationDate
                    };

                    this.hostedFieldsInstance.selectCard(card);
                    const cvvState = this.hostedFieldsInstance.getTokenizedCardCvvState(card)
                    if (cvvState === 'required') {
                        $('#' + this.getId() + '_hosted_fields_form').show();
                    } else {
                        $('#' + this.getId() + '_hosted_fields_form').hide();
                    }
                })
                .catch((e) => {
                    console.error('Hosted fields initialization failed', e);

                })
                .finally(() => {
                    fullScreenLoader.stopLoader();
                    this.isPlaceOrderActionAllowed(true);
                });
        },

        isActive: function () {
            var active = this.getId() === this.isChecked();
            this.active(active);
            return active;
        },
        isVaultEnabled: function () {
            return window.checkoutConfig.payment.dna_payment.integrationType === '2';
        },
        /**
         * @returns {exports}
         */
        initObservable: function () {
            this._super().observe(['active']);
            return this;
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
                    },
                    tokenizedCardCvv: {
                        container: self._getElement('cc_cid_token'),
                        placeholder: '123'
                    }
                }
            };

            return window.dnaPayments.hostedFields.create(config);
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
                        if (!self.paymentResponse) {
                            self.fetchPaymentData(orderId)
                                .then(async function (response) {
                                    self.paymentResponse = response;
                                    const {paymentData, accessToken} = response;

                                    try {
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
                                    console.error('Failed to fetch payment data:', error);

                                    fullScreenLoader.stopLoader();
                                    self.isPlaceOrderActionAllowed(true);
                                });
                        }
                    },
                ).fail(
                    function (e) {
                        console.error('Failed to placed order.', e);

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
        getPlaceOrderDeferredObject: function () {
            return $.when(placeOrderAction(this.getData()));
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
                        reject(err);
                    }
                })
            })
        },
        _getElement: function (field) {
            return $(`#${this.getId()}_${field}`)[0];
        },
        createThreeDSecureModal: function(){
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
        validate: async function () {
            if (this.hostedFieldsInstance) {
                const validateResponse = await this.hostedFieldsInstance.validate();

                return validateResponse.isValid;
            }

            return true;
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
    });
});