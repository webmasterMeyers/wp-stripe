/**
 * WP Stripe Payment JavaScript
 * Handles Stripe payment processing and form interactions
 */

(function ($) {
    'use strict';

    // Global variables
    let stripe;
    let elements;
    let cardElement;
    let paymentForm;
    let currentPaymentIntent;

    // Initialize when DOM is ready
    $(document).ready(function () {
        initializeStripe();
        setupEventListeners();
    });

    /**
     * Initialize Stripe
     */
    function initializeStripe() {
        // Check if Stripe is available
        if (typeof Stripe === 'undefined') {
            console.error('Stripe is not loaded');
            return;
        }

        // Initialize Stripe
        stripe = Stripe(wpStripePayment.publishableKey);

        // Create elements
        elements = stripe.elements();

        // Create card element
        cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
                invalid: {
                    color: '#9e2146',
                },
            },
        });

        // Mount card element
        const cardContainer = document.getElementById('card-element');
        if (cardContainer) {
            cardElement.mount(cardContainer);
        }
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Payment form submission
        $(document).on('submit', '#stripe-payment-form', handlePaymentSubmit);

        // Card element changes
        if (cardElement) {
            cardElement.on('change', handleCardElementChange);
        }

        // Amount input changes
        $(document).on('input', '#amount-input', handleAmountChange);

        // Modal triggers
        $(document).on('click', '.stripe-modal-trigger', openModal);
        $(document).on('click', '.modal-close, .modal-overlay', closeModal);
    }

    /**
     * Handle payment form submission
     */
    async function handlePaymentSubmit(e) {
        e.preventDefault();

        const form = $(e.target);
        const submitButton = form.find('.submit-button');
        const buttonText = submitButton.find('.button-text');
        const buttonLoading = submitButton.find('.button-loading');

        // Show loading state
        buttonText.hide();
        buttonLoading.show();
        submitButton.prop('disabled', true);

        try {
            // Get form data
            const formData = getFormData(form);

            // Validate form
            if (!validateForm(formData)) {
                throw new Error('Please fill in all required fields');
            }

            // Get amount (from form or URL parameters)
            let amount = formData.amount;
            if (!amount || amount <= 0) {
                // Try to get amount from URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                const urlAmount = urlParams.get('stripe_price');
                if (urlAmount) {
                    amount = parseFloat(urlAmount) * 100; // Convert to cents
                }
            }

            if (!amount || amount <= 0) {
                throw new Error('Please enter a valid amount');
            }

            // Create payment intent
            const paymentIntent = await createPaymentIntent(amount, formData);

            if (!paymentIntent) {
                throw new Error('Failed to create payment intent');
            }

            // Store payment intent
            currentPaymentIntent = paymentIntent;

            // Confirm payment
            const result = await stripe.confirmCardPayment(paymentIntent.client_secret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: formData.name,
                        email: formData.email,
                    },
                },
            });

            if (result.error) {
                throw new Error(result.error.message);
            }

            if (result.paymentIntent.status === 'succeeded') {
                // Process successful payment
                await processPayment(result.paymentIntent.id, formData);
                showSuccessMessage('Payment successful!');

                // Redirect to success page if configured
                const successPage = wpStripePayment.successPage;
                if (successPage) {
                    window.location.href = successPage;
                }
            } else {
                throw new Error('Payment was not completed');
            }

        } catch (error) {
            console.error('Payment error:', error);
            showErrorMessage(error.message);
        } finally {
            // Reset button state
            buttonText.show();
            buttonLoading.hide();
            submitButton.prop('disabled', false);
        }
    }

    /**
     * Handle card element changes
     */
    function handleCardElementChange(event) {
        const displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
            displayError.style.display = 'block';
        } else {
            displayError.textContent = '';
            displayError.style.display = 'none';
        }
    }

    /**
     * Handle amount input changes
     */
    function handleAmountChange(e) {
        const amount = parseFloat(e.target.value);
        const currency = wpStripePayment.currency || 'usd';

        // Update amount display if it exists
        const amountDisplay = document.querySelector('.amount-display .amount');
        if (amountDisplay && !isNaN(amount)) {
            amountDisplay.textContent = amount.toFixed(2);
        }
    }

    /**
     * Get form data
     */
    function getFormData(form) {
        const formData = {};

        // Get basic form fields
        form.find('input, select, textarea').each(function () {
            const field = $(this);
            const name = field.attr('name');
            const value = field.val();

            if (name && value) {
                if (name === 'metadata[]') {
                    // Handle metadata arrays
                    if (!formData.metadata) {
                        formData.metadata = {};
                    }
                    const key = field.attr('data-key') || 'custom';
                    formData.metadata[key] = value;
                } else {
                    formData[name] = value;
                }
            }
        });

        // Get metadata from hidden fields
        form.find('input[name^="metadata["]').each(function () {
            const field = $(this);
            const name = field.attr('name');
            const value = field.val();

            if (name && value) {
                const key = name.match(/metadata\[(.*?)\]/);
                if (key && key[1]) {
                    if (!formData.metadata) {
                        formData.metadata = {};
                    }
                    formData.metadata[key[1]] = value;
                }
            }
        });

        return formData;
    }

    /**
     * Validate form data
     */
    function validateForm(formData) {
        const requiredFields = ['name', 'email'];

        for (const field of requiredFields) {
            if (!formData[field] || formData[field].trim() === '') {
                return false;
            }
        }

        // Validate email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(formData.email)) {
            return false;
        }

        return true;
    }

    /**
     * Create payment intent
     */
    async function createPaymentIntent(amount, formData) {
        try {
            const response = await $.ajax({
                url: wpStripePayment.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wp_stripe_create_payment_intent',
                    nonce: wpStripePayment.nonce,
                    amount: amount,
                    currency: wpStripePayment.currency || 'usd',
                    metadata: formData.metadata || {},
                },
            });

            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data || 'Failed to create payment intent');
            }
        } catch (error) {
            console.error('Error creating payment intent:', error);
            throw error;
        }
    }

    /**
     * Process payment
     */
    async function processPayment(paymentIntentId, formData) {
        try {
            const response = await $.ajax({
                url: wpStripePayment.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wp_stripe_process_payment',
                    nonce: wpStripePayment.nonce,
                    payment_intent_id: paymentIntentId,
                    metadata: formData.metadata || {},
                },
            });

            if (!response.success) {
                throw new Error(response.data || 'Failed to process payment');
            }

            return response.data;
        } catch (error) {
            console.error('Error processing payment:', error);
            throw error;
        }
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message) {
        const messageContainer = document.getElementById('payment-message');
        if (messageContainer) {
            messageContainer.textContent = message;
            messageContainer.className = 'payment-message success';
            messageContainer.style.display = 'block';
        }

        // Show success notification
        if (typeof wp !== 'undefined' && wp.notices) {
            wp.notices.create('success', message);
        }
    }

    /**
     * Show error message
     */
    function showErrorMessage(message) {
        const messageContainer = document.getElementById('payment-message');
        if (messageContainer) {
            messageContainer.textContent = message;
            messageContainer.className = 'payment-message error';
            messageContainer.style.display = 'block';
        }

        // Show error notification
        if (typeof wp !== 'undefined' && wp.notices) {
            wp.notices.create('error', message);
        }
    }

    /**
     * Open payment modal
     */
    function openModal() {
        const modal = document.getElementById('stripe-payment-modal');
        if (modal) {
            modal.style.display = 'block';

            // Load payment form in modal
            loadPaymentFormInModal();
        }
    }

    /**
     * Close payment modal
     */
    function closeModal() {
        const modal = document.getElementById('stripe-payment-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    /**
     * Load payment form in modal
     */
    async function loadPaymentFormInModal() {
        try {
            const response = await $.ajax({
                url: wpStripePayment.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wp_stripe_get_payment_form',
                    nonce: wpStripePayment.nonce,
                },
            });

            if (response.success) {
                const modalForm = document.getElementById('modal-payment-form');
                if (modalForm) {
                    modalForm.innerHTML = response.data.form_html;

                    // Reinitialize Stripe elements in modal
                    if (stripe && elements) {
                        const modalCardElement = elements.create('card');
                        const modalCardContainer = modalForm.querySelector('#card-element');
                        if (modalCardContainer) {
                            modalCardElement.mount(modalCardContainer);
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Error loading payment form:', error);
        }
    }

    /**
     * Show payment form
     */
    window.wpStripePayment = {
        showPaymentForm: function (amount, currency, description, metadata) {
            // This function can be called from shortcodes or other elements
            console.log('Show payment form:', { amount, currency, description, metadata });

            // You can implement custom logic here to show forms
            // For now, we'll just open the modal
            openModal();
        },

        openModal: openModal,
        closeModal: closeModal,

        testConnection: function () {
            // Test Stripe connection (admin only)
            if (typeof wp !== 'undefined' && wp.ajax) {
                wp.ajax.post('wp_stripe_test_connection', {
                    nonce: wpStripePayment.nonce,
                }).done(function (response) {
                    if (response.success) {
                        alert('Connection successful!');
                    } else {
                        alert('Connection failed: ' + response.data);
                    }
                }).fail(function () {
                    alert('Connection test failed');
                });
            }
        },

        retryPayment: function () {
            // Retry payment logic
            const form = document.getElementById('stripe-payment-form');
            if (form) {
                form.reset();

                // Clear error messages
                const messageContainer = document.getElementById('payment-message');
                if (messageContainer) {
                    messageContainer.style.display = 'none';
                }

                // Clear card errors
                const cardErrors = document.getElementById('card-errors');
                if (cardErrors) {
                    cardErrors.textContent = '';
                    cardErrors.style.display = 'none';
                }
            }
        }
    };

})(jQuery);
