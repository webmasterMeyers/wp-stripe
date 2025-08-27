/**
 * WP Stripe Payment Admin JavaScript
 * Handles admin interface interactions
 */

(function ($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function () {
        initializeAdmin();
    });

    /**
     * Initialize admin functionality
     */
    function initializeAdmin() {
        setupEventListeners();
        setupTabs();
        setupFormValidation();
        setupTestConnection();
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Test connection button
        $(document).on('click', '.test-connection-btn', handleTestConnection);

        // Settings form submission
        $(document).on('submit', '#stripe-settings-form', handleSettingsSubmit);

        // API key visibility toggle
        $(document).on('click', '.toggle-api-key', toggleApiKeyVisibility);

        // Currency change handler
        $(document).on('change', '#wp_stripe_currency', handleCurrencyChange);

        // Test mode toggle
        $(document).on('change', '#wp_stripe_test_mode', handleTestModeToggle);

        // Bulk actions
        $(document).on('change', '#bulk-action-selector-top', handleBulkActionChange);
        $(document).on('click', '#doaction, #doaction2', handleBulkActionSubmit);

        // Search functionality
        $(document).on('input', '.search-input', handleSearch);

        // Export functionality
        $(document).on('click', '.export-btn', handleExport);

        // Import functionality
        $(document).on('change', '#import-file', handleImport);
    }

    /**
     * Setup tabs functionality
     */
    function setupTabs() {
        const tabLinks = $('.nav-tab');
        const tabContents = $('.tab-content');

        tabLinks.on('click', function (e) {
            e.preventDefault();

            const target = $(this).data('tab');

            // Update active tab
            tabLinks.removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Show target content
            tabContents.hide();
            $('#' + target).show();

            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, '#' + target);
            }
        });

        // Handle initial tab based on URL hash
        const hash = window.location.hash.substring(1);
        if (hash && $('#' + hash).length) {
            tabLinks.filter('[data-tab="' + hash + '"]').click();
        }
    }

    /**
     * Setup form validation
     */
    function setupFormValidation() {
        $('#stripe-settings-form').on('submit', function (e) {
            const requiredFields = ['wp_stripe_secret_key', 'wp_stripe_publishable_key'];
            let isValid = true;

            requiredFields.forEach(function (fieldName) {
                const field = $('#' + fieldName);
                const value = field.val().trim();

                if (!value) {
                    showFieldError(field, 'This field is required');
                    isValid = false;
                } else {
                    clearFieldError(field);
                }
            });

            if (!isValid) {
                e.preventDefault();
                showMessage('Please fill in all required fields', 'error');
            }
        });
    }

    /**
     * Setup test connection functionality
     */
    function setupTestConnection() {
        // Auto-test connection when API keys change
        let testTimeout;

        $('input[name="wp_stripe_secret_key"], input[name="wp_stripe_publishable_key"]').on('input', function () {
            clearTimeout(testTimeout);
            testTimeout = setTimeout(function () {
                if (canTestConnection()) {
                    testConnection();
                }
            }, 2000);
        });
    }

    /**
     * Handle test connection
     */
    function handleTestConnection(e) {
        e.preventDefault();
        testConnection();
    }

    /**
     * Test Stripe connection
     */
    function testConnection() {
        if (!canTestConnection()) {
            showMessage('Please enter both API keys before testing', 'warning');
            return;
        }

        const button = $('.test-connection-btn');
        const originalText = button.text();

        // Show loading state
        button.prop('disabled', true).text('Testing...').addClass('loading');

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wp_stripe_test_connection',
                nonce: wpStripePayment.nonce
            },
            success: function (response) {
                if (response.success) {
                    showMessage('Connection successful! Stripe is properly configured.', 'success');
                    updateConnectionStatus('connected');
                } else {
                    showMessage('Connection failed: ' + response.data, 'error');
                    updateConnectionStatus('failed');
                }
            },
            error: function () {
                showMessage('Connection test failed. Please check your API keys.', 'error');
                updateConnectionStatus('failed');
            },
            complete: function () {
                // Reset button state
                button.prop('disabled', false).text(originalText).removeClass('loading');
            }
        });
    }

    /**
     * Check if connection can be tested
     */
    function canTestConnection() {
        const secretKey = $('#wp_stripe_secret_key').val().trim();
        const publishableKey = $('#wp_stripe_publishable_key').val().trim();

        return secretKey && publishableKey;
    }

    /**
     * Update connection status display
     */
    function updateConnectionStatus(status) {
        const statusElement = $('.connection-status');

        statusElement.removeClass('status-connected status-failed status-unknown');

        switch (status) {
            case 'connected':
                statusElement.addClass('status-connected').text('Connected');
                break;
            case 'failed':
                statusElement.addClass('status-failed').text('Failed');
                break;
            default:
                statusElement.addClass('status-unknown').text('Unknown');
        }
    }

    /**
     * Handle settings form submission
     */
    function handleSettingsSubmit(e) {
        // Show loading state
        const submitButton = $(this).find('input[type="submit"]');
        const originalText = submitButton.val();

        submitButton.prop('disabled', true).val('Saving...');

        // Form will submit normally, but we can add custom validation here
    }

    /**
     * Toggle API key visibility
     */
    function toggleApiKeyVisibility(e) {
        e.preventDefault();

        const button = $(this);
        const input = button.siblings('input');
        const icon = button.find('.dashicons');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            button.attr('title', 'Hide');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            button.attr('title', 'Show');
        }
    }

    /**
     * Handle currency change
     */
    function handleCurrencyChange() {
        const currency = $(this).val();
        const symbol = getCurrencySymbol(currency);

        // Update currency symbol displays
        $('.currency-symbol').text(symbol);

        // Update amount examples
        $('.amount-example').each(function () {
            const amount = $(this).data('amount');
            $(this).text(symbol + amount);
        });
    }

    /**
     * Get currency symbol
     */
    function getCurrencySymbol(currency) {
        const symbols = {
            'usd': '$',
            'eur': '€',
            'gbp': '£',
            'cad': 'C$',
            'aud': 'A$',
            'jpy': '¥'
        };

        return symbols[currency] || currency.toUpperCase();
    }

    /**
     * Handle test mode toggle
     */
    function handleTestModeToggle() {
        const isTestMode = $(this).val() === 'yes';
        const testFields = $('.test-mode-field');
        const liveFields = $('.live-mode-field');

        if (isTestMode) {
            testFields.show();
            liveFields.hide();
            showMessage('Test mode enabled. Use test API keys for development.', 'info');
        } else {
            testFields.hide();
            liveFields.show();
            showMessage('Live mode enabled. Use live API keys for production.', 'warning');
        }
    }

    /**
     * Handle bulk action change
     */
    function handleBulkActionChange() {
        const action = $(this).val();
        const submitButton = $('#doaction, #doaction2');

        if (action && action !== '-1') {
            submitButton.prop('disabled', false);
        } else {
            submitButton.prop('disabled', true);
        }
    }

    /**
     * Handle bulk action submit
     */
    function handleBulkActionSubmit(e) {
        const action = $('#bulk-action-selector-top').val();
        const selectedItems = $('input[name="item[]"]:checked');

        if (action === '-1' || selectedItems.length === 0) {
            e.preventDefault();
            showMessage('Please select an action and at least one item.', 'warning');
            return false;
        }

        // Confirm destructive actions
        if (action === 'delete' || action === 'refund') {
            if (!confirm('Are you sure you want to perform this action? This cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        }
    }

    /**
     * Handle search
     */
    function handleSearch() {
        const query = $(this).val().toLowerCase();
        const table = $(this).closest('.wp-list-table');
        const rows = table.find('tbody tr');

        rows.each(function () {
            const text = $(this).text().toLowerCase();
            if (text.includes(query)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        // Update row count
        const visibleRows = rows.filter(':visible').length;
        $('.row-count').text(visibleRows + ' items');
    }

    /**
     * Handle export
     */
    function handleExport(e) {
        e.preventDefault();

        const format = $(this).data('format');
        const type = $(this).data('type');

        // Create export URL
        const exportUrl = ajaxurl + '?action=wp_stripe_export&format=' + format + '&type=' + type + '&nonce=' + wpStripePayment.nonce;

        // Trigger download
        window.location.href = exportUrl;
    }

    /**
     * Handle import
     */
    function handleImport(e) {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('action', 'wp_stripe_import');
        formData.append('import_file', file);
        formData.append('nonce', wpStripePayment.nonce);

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    showMessage('Import completed successfully!', 'success');
                    location.reload();
                } else {
                    showMessage('Import failed: ' + response.data, 'error');
                }
            },
            error: function () {
                showMessage('Import failed. Please try again.', 'error');
            }
        });
    }

    /**
     * Show field error
     */
    function showFieldError(field, message) {
        clearFieldError(field);

        const errorDiv = $('<div class="field-error">' + message + '</div>');
        field.after(errorDiv);
        field.addClass('error');
    }

    /**
     * Clear field error
     */
    function clearFieldError(field) {
        field.siblings('.field-error').remove();
        field.removeClass('error');
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        const messageDiv = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

        // Remove existing messages
        $('.notice').remove();

        // Add new message
        $('.wrap h1').after(messageDiv);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            messageDiv.fadeOut();
        }, 5000);
    }

    /**
     * Format currency
     */
    function formatCurrency(amount, currency) {
        const symbol = getCurrencySymbol(currency);
        const formatted = (amount / 100).toFixed(2);
        return symbol + formatted;
    }

    /**
     * Format date
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    /**
     * Confirm action
     */
    function confirmAction(message) {
        return confirm(message || 'Are you sure you want to perform this action?');
    }

    /**
     * Show loading overlay
     */
    function showLoading() {
        if ($('#loading-overlay').length === 0) {
            $('body').append('<div id="loading-overlay"><div class="spinner"></div></div>');
        }
        $('#loading-overlay').show();
    }

    /**
     * Hide loading overlay
     */
    function hideLoading() {
        $('#loading-overlay').hide();
    }

    /**
     * Refresh data
     */
    function refreshData() {
        location.reload();
    }

    // Expose functions globally for use in other scripts
    window.wpStripeAdmin = {
        testConnection: testConnection,
        showMessage: showMessage,
        formatCurrency: formatCurrency,
        formatDate: formatDate,
        confirmAction: confirmAction,
        showLoading: showLoading,
        hideLoading: hideLoading,
        refreshData: refreshData
    };

})(jQuery);
