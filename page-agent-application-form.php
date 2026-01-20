<?php
/**
 * Template Name: Agent Application Form
 */

$application_form = Unico_Application_Form::get_instance();
$sections = $application_form->get_fields_by_section('agent');
$is_agent_form = true;

get_header();
?>

<main class="application-page">
    <section class="application-hero">
        <div class="application-hero-inner">
            <div class="application-badge">
                AGENT PROTOCOL • PARTNER ONBOARDING
            </div>
            <h1 class="application-title">
                Agent Application
            </h1>
            <p class="application-subtitle">
                Share your agency details so UNICOU can onboard you as a verified partner and enable agent dashboards, pricing and workflows.
            </p>
        </div>
    </section>

    <section class="application-layout">
        <div class="application-main">
            <?php if (isset($_GET['submission_success']) && isset($_GET['submission_number'])): ?>
                <div class="application-alert application-alert-success">
                    <div class="application-alert-icon">✓</div>
                    <div class="application-alert-body">
                        <h2>Application submitted</h2>
                        <p>
                            Application number
                            <span class="application-number">
                                <?php echo esc_html($_GET['submission_number']); ?>
                            </span>
                        </p>
                        <p>
                            A confirmation email has been sent. Your profile is now queued for
                            review. Expect an update within 24–48 hours.
                        </p>
                    </div>
                </div>
            <?php else: ?>

                <?php if (isset($_GET['email_verified'])): ?>
                    <div class="application-alert application-alert-success">
                        <div class="application-alert-icon">✓</div>
                        <div class="application-alert-body">
                            <h2>Email Verified!</h2>
                            <p>Your email address has been verified. You can now submit your application.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['verification_sent'])): ?>
                    <div class="application-alert application-alert-success">
                        <div class="application-alert-icon">📧</div>
                        <div class="application-alert-body">
                            <h2>Verification Email Sent</h2>
                            <p>
                                Please check your email at <strong><?php echo esc_html(urldecode($_GET['email'])); ?></strong>
                                and click the verification link to proceed with your application.
                            </p>
                            <p style="font-size: 14px; margin-top: 10px; color: #666;">
                                The verification link will expire in 24 hours. If you don't receive the email, check your spam folder.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['submission_error'])): ?>
                    <div class="application-alert application-alert-error">
                        <div class="application-alert-icon">!</div>
                        <div class="application-alert-body">
                            <h2>Submission failed</h2>
                            <p>
                                <?php
                                if (isset($_GET['error_message'])) {
                                    echo esc_html(urldecode($_GET['error_message']));
                                } else {
                                    echo 'There was a problem submitting your application. Please try again.';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="application-note">
                    <strong>Partner screening.</strong>
                    Provide accurate business details. Management will review and approve or reject your agent profile.
                </div>

                <form method="post" action="" class="application-form">
                    <?php foreach ($sections as $section_name => $fields): ?>
                        <div class="application-section">
                            <div class="application-section-header">
                                <h2><?php echo esc_html($section_name); ?></h2>
                            </div>

                            <div class="application-grid">
                                <?php foreach ($fields as $field): ?>
                                    <div class="application-field <?php echo $field->field_type === 'textarea' ? 'application-field-full' : ''; ?>">
                                        <label class="application-label" for="<?php echo esc_attr($field->field_name); ?>">
                                            <?php echo esc_html($field->field_label); ?>
                                            <?php if ($field->field_required): ?>
                                                <span class="application-required">*</span>
                                            <?php endif; ?>
                                        </label>

                                        <?php if ($field->field_type === 'select'):
                                            $options = json_decode($field->field_options, true) ?: [];
                                        ?>
                                            <select
                                                name="<?php echo esc_attr($field->field_name); ?>"
                                                id="<?php echo esc_attr($field->field_name); ?>"
                                                class="application-control"
                                                <?php echo $field->field_required ? 'required' : ''; ?>
                                            >
                                                <option value="">Select an option</option>
                                                <?php foreach ($options as $option): ?>
                                                    <option value="<?php echo esc_attr($option); ?>">
                                                        <?php echo esc_html($option); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($field->field_type === 'textarea'): ?>
                                            <textarea
                                                name="<?php echo esc_attr($field->field_name); ?>"
                                                id="<?php echo esc_attr($field->field_name); ?>"
                                                class="application-control application-control-textarea"
                                                placeholder="<?php echo esc_attr($field->field_placeholder); ?>"
                                                <?php echo $field->field_required ? 'required' : ''; ?>
                                            ></textarea>
                                        <?php else: ?>
                                            <input
                                                type="<?php echo esc_attr($field->field_type); ?>"
                                                name="<?php echo esc_attr($field->field_name); ?>"
                                                id="<?php echo esc_attr($field->field_name); ?>"
                                                class="application-control"
                                                placeholder="<?php echo esc_attr($field->field_placeholder); ?>"
                                                <?php echo $field->field_required ? 'required' : ''; ?>
                                            >
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <input type="hidden" name="application_type" value="agent">

                    <?php wp_nonce_field('submit_application', 'application_nonce'); ?>

                    <!-- Email Verification Section -->
                    <div class="application-section" style="background: #f0f8ff; border: 2px solid #194f68; border-radius: 8px; padding: 20px; margin: 20px 0;">
                        <h3 style="color: #194f68; margin-top: 0;">📧 Email Verification Required</h3>
                        <p style="margin-bottom: 15px;">Before submitting your application, you must verify your email address.</p>

                        <div id="email-verification-form" style="display: block;">
                            <label for="verify-email" style="display: block; margin-bottom: 8px; font-weight: 600;">Enter your email to verify:</label>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <input
                                    type="email"
                                    id="verify-email"
                                    class="application-control"
                                    placeholder="your@email.com"
                                    value="<?php echo isset($_GET['email']) ? esc_attr(urldecode($_GET['email'])) : ''; ?>"
                                    required
                                    style="flex: 1; min-width: 250px;"
                                >
                                <button
                                    type="button"
                                    id="send-verification-btn"
                                    class="application-submit"
                                    style="background: #194f68; flex-shrink: 0;"
                                    onclick="sendVerificationEmail()"
                                >
                                    Send Verification Email
                                </button>
                            </div>
                            <p style="font-size: 14px; color: #666; margin-top: 10px;">
                                <strong>Important:</strong> Make sure the email address you enter here matches the email in your application form above.
                            </p>
                        </div>

                        <div id="verification-pending" style="display: none; color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                            <strong>⏳ Verification Pending</strong>
                            <p style="margin: 5px 0 0 0;">Check your email and click the verification link to proceed.</p>
                        </div>

                        <div id="verification-success" style="display: none; color: #155724; background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;">
                            <strong>✅ Email Verified!</strong>
                            <p style="margin: 5px 0 0 0;">You can now submit your application.</p>
                        </div>
                    </div>

                    <div class="application-actions">
                        <button
                            type="submit"
                            name="submit_application"
                            class="application-submit"
                            id="submit-application-btn"
                            <?php if (!isset($_GET['email_verified'])): ?>disabled<?php endif; ?>
                            style="<?php if (!isset($_GET['email_verified'])): ?>opacity: 0.5; cursor: not-allowed;<?php endif; ?>"
                        >
                            Submit Application
                        </button>
                        <p class="application-actions-note">
                            By submitting, you authorise UNICOU to process your business data for
                            partnership verification.
                        </p>
                    </div>
                </form>

                <form method="post" id="verify-email-form" style="display: none;">
                    <input type="hidden" name="email" id="verify-email-hidden">
                    <input type="hidden" name="application_type" value="agent">
                    <?php wp_nonce_field('verify_application_email', 'verify_email_nonce'); ?>
                    <button type="submit" name="verify_application_email" id="verify-email-submit"></button>
                </form>

                <script>
                function sendVerificationEmail() {
                    const emailInput = document.getElementById('verify-email');
                    const email = emailInput.value.trim();

                    if (!email || !email.includes('@')) {
                        alert('Please enter a valid email address');
                        return;
                    }

                    // Update hidden form
                    document.getElementById('verify-email-hidden').value = email;

                    // Show pending state
                    document.getElementById('email-verification-form').style.display = 'none';
                    document.getElementById('verification-pending').style.display = 'block';

                    // Submit the verification form
                    document.getElementById('verify-email-submit').click();
                }

                // Check if email was just verified
                if (window.location.search.includes('email_verified=1')) {
                    document.getElementById('email-verification-form').style.display = 'none';
                    document.getElementById('verification-success').style.display = 'block';
                    document.getElementById('submit-application-btn').disabled = false;
                    document.getElementById('submit-application-btn').style.opacity = '1';
                    document.getElementById('submit-application-btn').style.cursor = 'pointer';

                    // Auto-fill email in form if provided
                    const urlParams = new URLSearchParams(window.location.search);
                    const verifiedEmail = urlParams.get('email');
                    if (verifiedEmail) {
                        const emailField = document.getElementById('email');
                        if (emailField) {
                            emailField.value = decodeURIComponent(verifiedEmail);
                        }
                    }
                }

                // Check if verification was just sent
                if (window.location.search.includes('verification_sent=1')) {
                    document.getElementById('email-verification-form').style.display = 'none';
                    document.getElementById('verification-pending').style.display = 'block';
                }
                </script>
            <?php endif; ?>
        </div>

        <aside class="application-sidebar">
            <div class="application-sidebar-card">
                <h3>Agent Partnership Benefits</h3>
                <ul>
                    <li>Access to exclusive pricing and commissions.</li>
                    <li>Dedicated agent dashboard for student management.</li>
                    <li>Priority support and training materials.</li>
                </ul>
            </div>

            <div class="application-sidebar-card">
                <h3>Approval Process</h3>
                <ul>
                    <li>Submit your agency details and registration documents.</li>
                    <li>Our partnership team will verify your credentials.</li>
                    <li>Upon approval, you will receive login credentials via email.</li>
                </ul>
            </div>
        </aside>
    </section>
</main>

<?php get_footer(); ?>
