<?php
if (!defined('ABSPATH')) {
    exit;
}

$bank = isset($bank) ? $bank : [];
$context = isset($context) ? $context : 'checkout';
?>
<div class="unico-bank-details unico-bank-details--<?php echo esc_attr($context); ?>">
    <h3>Bank Transfer Account</h3>
    <ul>
        <li><strong>Bank:</strong> <?php echo esc_html($bank['unico_bank_name'] ?? $bank['title'] ?? ''); ?></li>
        <?php if (!empty($bank['unico_account_name'])) : ?>
            <li><strong>Account Name:</strong> <?php echo esc_html($bank['unico_account_name']); ?></li>
        <?php endif; ?>
        <?php if (!empty($bank['unico_account_number'])) : ?>
            <li><strong>Account Number:</strong> <?php echo esc_html($bank['unico_account_number']); ?></li>
        <?php endif; ?>
        <?php if (!empty($bank['unico_iban'])) : ?>
            <li><strong>IBAN:</strong> <?php echo esc_html($bank['unico_iban']); ?></li>
        <?php endif; ?>
        <?php if (!empty($bank['unico_swift'])) : ?>
            <li><strong>SWIFT:</strong> <?php echo esc_html($bank['unico_swift']); ?></li>
        <?php endif; ?>
        <?php if (!empty($bank['unico_branch'])) : ?>
            <li><strong>Branch:</strong> <?php echo esc_html($bank['unico_branch']); ?></li>
        <?php endif; ?>
        <?php if (!empty($bank['unico_currency'])) : ?>
            <li><strong>Currency:</strong> <?php echo esc_html($bank['unico_currency']); ?></li>
        <?php endif; ?>
        <?php if (!empty($bank['unico_notes'])) : ?>
            <li><strong>Notes:</strong> <?php echo esc_html($bank['unico_notes']); ?></li>
        <?php endif; ?>
    </ul>
</div>
