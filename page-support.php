<?php
/**
 * Template Name: Support
 */

// Handle ticket submission
if (isset($_POST['submit_ticket']) && wp_verify_nonce($_POST['ticket_nonce'], 'submit_ticket')) {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'unico_support_tickets';

    $user_id = is_user_logged_in() ? get_current_user_id() : null;
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $subject = sanitize_text_field($_POST['subject']);
    $message = sanitize_textarea_field($_POST['message']);
    $category = sanitize_text_field($_POST['category']);
    $priority = sanitize_text_field($_POST['priority']);
    $order_id = !empty($_POST['order_id']) ? intval($_POST['order_id']) : null;

    // Generate ticket number
    $ticket_number = 'TKT-' . date('Ymd') . '-' . strtoupper(wp_generate_password(6, false));

    // Insert ticket
    $inserted = $wpdb->insert($tickets_table, [
        'ticket_number' => $ticket_number,
        'user_id' => $user_id,
        'subject' => $subject,
        'message' => $message,
        'category' => $category,
        'priority' => $priority,
        'order_id' => $order_id,
        'status' => 'open',
        'created_at' => current_time('mysql')
    ]);

    if ($inserted) {
        // Send confirmation email
        $email_subject = 'Support Ticket Created - ' . $ticket_number;
        $email_message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2 style='color: #194f68;'>Support Ticket Received</h2>
            <p>Dear {$name},</p>
            <p>Your support ticket has been created successfully.</p>
            <p><strong>Ticket Number:</strong> <span style='font-size: 18px; color: #e95134;'>{$ticket_number}</span></p>
            <p><strong>Subject:</strong> {$subject}</p>
            <p>Our support team will review your request and respond within 24 hours.</p>
            <p style='margin-top: 30px;'>Best regards,<br>" . get_bloginfo('name') . " Support Team</p>
        </body>
        </html>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $email_subject, $email_message, $headers);

        // Redirect with success
        wp_redirect(add_query_arg([
            'ticket_created' => '1',
            'ticket_number' => $ticket_number
        ], home_url('/support')));
        exit;
    }
}

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; color: #4a4a4a; }
        .support-container { max-width: 900px; margin: 40px auto; padding: 20px; }
        .support-header { background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%); color: white; padding: 40px; border-radius: 12px 12px 0 0; text-align: center; }
        .support-header h1 { font-size: 32px; margin-bottom: 10px; }
        .support-header p { opacity: 0.9; font-size: 16px; }
        .support-body { background: white; padding: 40px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); }
        .success-message { background: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin-bottom: 30px; border-radius: 8px; }
        .success-message h3 { color: #155724; margin-bottom: 10px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: #4a4a4a; }
        .form-label .required { color: #dc3545; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; font-family: inherit; transition: border-color 0.2s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #6f42c1; }
        .form-textarea { min-height: 150px; resize: vertical; }
        .btn-submit { background: #6f42c1; color: white; padding: 15px 40px; border: none; border-radius: 8px; font-size: 18px; font-weight: 700; cursor: pointer; transition: background 0.2s; width: 100%; }
        .btn-submit:hover { background: #563d7c; }
        .info-box { background: #e7f3ff; border-left: 4px solid #0066cc; padding: 15px; margin-bottom: 30px; border-radius: 8px; font-size: 14px; }
        .faq-section { margin-top: 40px; padding: 30px; background: #f8f9fa; border-radius: 12px; }
        .faq-section h3 { margin-bottom: 20px; color: #6f42c1; }
        .faq-item { margin-bottom: 20px; }
        .faq-item h4 { color: #103e54; margin-bottom: 8px; }
        .faq-item p { color: #6c757d; line-height: 1.6; }
    </style>
</head>
<body>

<div class="support-container">
    <div class="support-header">
        <h1>🎧 Customer Support</h1>
        <p>Need help? Our support team is here to assist you 24/7.</p>
    </div>

    <div class="support-body">

        <?php if (isset($_GET['ticket_created']) && isset($_GET['ticket_number'])): ?>
        <div class="success-message">
            <h3>✓ Support Ticket Created!</h3>
            <p><strong>Your Ticket Number:</strong> <span style="font-size: 18px;"><?php echo esc_html($_GET['ticket_number']); ?></span></p>
            <p>We've sent a confirmation email. Our support team will respond within 24 hours.</p>
        </div>
        <?php else: ?>

        <div class="info-box">
            <strong>Before submitting a ticket:</strong> Check our <a href="#faq">FAQ section</a> below. Your question might already be answered!
        </div>

        <form method="post" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="name">
                        Full Name <span class="required">*</span>
                    </label>
                    <input type="text" name="name" id="name" class="form-input"
                           value="<?php echo is_user_logged_in() ? wp_get_current_user()->display_name : ''; ?>"
                           placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">
                        Email Address <span class="required">*</span>
                    </label>
                    <input type="email" name="email" id="email" class="form-input"
                           value="<?php echo is_user_logged_in() ? wp_get_current_user()->user_email : ''; ?>"
                           placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="category">
                        Category <span class="required">*</span>
                    </label>
                    <select name="category" id="category" class="form-select" required>
                        <option value="">-- Select Category --</option>
                        <option value="order_issue">Order Issue</option>
                        <option value="voucher_problem">Voucher Problem</option>
                        <option value="payment_issue">Payment Issue</option>
                        <option value="account_access">Account Access</option>
                        <option value="refund_request">Refund Request</option>
                        <option value="technical_issue">Technical Issue</option>
                        <option value="general_inquiry">General Inquiry</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="priority">
                        Priority <span class="required">*</span>
                    </label>
                    <select name="priority" id="priority" class="form-select" required>
                        <option value="low">Low - General question</option>
                        <option value="medium" selected>Medium - Need help</option>
                        <option value="high">High - Urgent issue</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="subject">
                        Subject <span class="required">*</span>
                    </label>
                    <input type="text" name="subject" id="subject" class="form-input"
                           placeholder="Brief description of your issue" required>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="order_id">
                        Order ID (if applicable)
                    </label>
                    <input type="number" name="order_id" id="order_id" class="form-input"
                           placeholder="12345">
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="message">
                        Message <span class="required">*</span>
                    </label>
                    <textarea name="message" id="message" class="form-textarea"
                              placeholder="Please provide detailed information about your issue..." required></textarea>
                </div>
            </div>

            <?php wp_nonce_field('submit_ticket', 'ticket_nonce'); ?>

            <button type="submit" name="submit_ticket" class="btn-submit">
                Submit Ticket
            </button>
        </form>

        <?php endif; ?>

        <div class="faq-section" id="faq">
            <h3>📚 Frequently Asked Questions</h3>

            <div class="faq-item">
                <h4>How long does voucher delivery take?</h4>
                <p>Vouchers are delivered instantly to your email and dashboard once payment is confirmed. This typically takes less than 5 minutes.</p>
            </div>

            <div class="faq-item">
                <h4>Can I get a refund if I haven't used my voucher?</h4>
                <p>Refund policies vary by voucher type. Unused vouchers can typically be refunded within 24 hours of purchase. Please contact support for assistance.</p>
            </div>

            <div class="faq-item">
                <h4>How do I verify my email address?</h4>
                <p>Check your inbox for a verification email sent during registration. Click the verification link. If you didn't receive it, contact support.</p>
            </div>

            <div class="faq-item">
                <h4>What payment methods do you accept?</h4>
                <p>We accept credit/debit cards, UPI, net banking, and digital wallets through our secure payment gateways.</p>
            </div>

            <div class="faq-item">
                <h4>How do I track my support ticket?</h4>
                <p>You'll receive a ticket number via email. Use this number to track your ticket status by contacting support or logging into your dashboard.</p>
            </div>
        </div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
