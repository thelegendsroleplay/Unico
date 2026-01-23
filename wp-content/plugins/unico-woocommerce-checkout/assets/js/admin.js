/**
 * Unico WooCommerce Admin Scripts
 * Admin JavaScript for order management
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Quick approve/reject buttons in meta box
         */
        $('.unico-quick-approve').on('click', function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to approve this payment?')) {
                return;
            }

            var button = $(this);
            var orderId = button.data('order-id');

            button.prop('disabled', true).text('Approving...');

            $.ajax({
                url: unicoAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'unico_quick_approve',
                    order_id: orderId,
                    nonce: unicoAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        button.prop('disabled', false).text('✓ Approve Payment');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    button.prop('disabled', false).text('✓ Approve Payment');
                }
            });
        });

        $('.unico-quick-reject').on('click', function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to reject this payment?')) {
                return;
            }

            var button = $(this);
            var orderId = button.data('order-id');

            button.prop('disabled', true).text('Rejecting...');

            $.ajax({
                url: unicoAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'unico_quick_reject',
                    order_id: orderId,
                    nonce: unicoAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        button.prop('disabled', false).text('✗ Reject Payment');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    button.prop('disabled', false).text('✗ Reject Payment');
                }
            });
        });

        /**
         * Bank account delete confirmation
         * (Already handled inline in class-bank-accounts.php)
         */

    });

})(jQuery);
