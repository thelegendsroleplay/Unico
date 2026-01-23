jQuery(function ($) {
    $(document).on('click', '.button', function () {
        if ($(this).text().toLowerCase().includes('reject')) {
            return confirm('Are you sure you want to reject this payment?');
        }
        if ($(this).text().toLowerCase().includes('approve')) {
            return confirm('Approve this payment and deliver vouchers?');
        }
        return true;
    });
});
