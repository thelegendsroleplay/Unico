/**
 * Unico WooCommerce Checkout Scripts
 * Frontend JavaScript for checkout interactions
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Copy to clipboard functionality
         */
        $('.copy-btn, .copy-voucher-btn').on('click', function(e) {
            e.preventDefault();

            var button = $(this);
            var textToCopy = button.data('copy') || button.data('code');

            if (!textToCopy) {
                return;
            }

            // Create temporary input element
            var tempInput = $('<input>');
            $('body').append(tempInput);
            tempInput.val(textToCopy).select();

            try {
                // Copy to clipboard
                document.execCommand('copy');

                // Visual feedback
                var originalText = button.text();
                button.text('Copied!').addClass('copied');

                setTimeout(function() {
                    button.text(originalText).removeClass('copied');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
                alert('Failed to copy to clipboard');
            }

            // Remove temporary input
            tempInput.remove();
        });

        /**
         * Checkout form validation enhancements
         */
        var checkoutForm = $('form.checkout');

        if (checkoutForm.length) {
            checkoutForm.on('checkout_place_order', function() {
                var transactionId = $('#transaction_id').val();
                var paymentProof = $('#payment_proof').val();

                // Check if bank transfer is selected
                var selectedPayment = $('input[name="payment_method"]:checked').val();

                if (selectedPayment === 'unico_bank_transfer') {
                    // Validate transaction ID
                    if (!transactionId || transactionId.trim() === '') {
                        alert('Please enter your transaction ID.');
                        $('#transaction_id').focus();
                        return false;
                    }

                    // Validate payment proof
                    if (!paymentProof || paymentProof.trim() === '') {
                        alert('Please upload your payment proof screenshot.');
                        $('#payment_proof').focus();
                        return false;
                    }

                    // Validate file type
                    var fileInput = document.getElementById('payment_proof');
                    if (fileInput && fileInput.files.length > 0) {
                        var file = fileInput.files[0];
                        var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

                        if (!allowedTypes.includes(file.type)) {
                            alert('Payment proof must be an image (JPG, PNG, or WEBP).');
                            $('#payment_proof').focus();
                            return false;
                        }

                        // Check file size (5MB = 5 * 1024 * 1024 bytes)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('Payment proof file size must be less than 5MB.');
                            $('#payment_proof').focus();
                            return false;
                        }
                    }
                }

                return true;
            });

            // Show loading state on form submit
            checkoutForm.on('submit', function() {
                var selectedPayment = $('input[name="payment_method"]:checked').val();

                if (selectedPayment === 'unico_bank_transfer') {
                    $('.unico-bank-details').addClass('unico-loading');
                }
            });
        }

        /**
         * File input preview
         */
        $('#payment_proof').on('change', function(e) {
            var file = e.target.files[0];

            if (file) {
                var reader = new FileReader();

                reader.onload = function(e) {
                    // Remove existing preview if any
                    $('.payment-proof-preview').remove();

                    // Create preview
                    var preview = $('<div class="payment-proof-preview" style="margin-top: 10px;"></div>');
                    var img = $('<img>').attr('src', e.target.result).css({
                        'max-width': '200px',
                        'height': 'auto',
                        'border': '1px solid #ddd',
                        'border-radius': '4px'
                    });

                    preview.append('<p style="font-size: 13px; color: #666; margin-bottom: 5px;">Preview:</p>');
                    preview.append(img);

                    $('#payment_proof').after(preview);
                };

                reader.readAsDataURL(file);
            } else {
                $('.payment-proof-preview').remove();
            }
        });

        /**
         * Display file name when selected
         */
        $('#payment_proof').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            if (fileName) {
                $(this).next('small').text('Selected: ' + fileName);
            }
        });

    });

})(jQuery);
