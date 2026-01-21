<?php
/**
 * Template Name: Student Application Form
 */

$application_form = Unico_Application_Form::get_instance();
$sections = $application_form->get_fields_by_section('student');
$is_agent_form = false;

get_header();
?>

<main class="application-page">
    <section class="application-hero">
        <div class="application-hero-inner">
            <div class="application-badge">
                <?php echo $is_agent_form ? 'AGENT PROTOCOL • PARTNER ONBOARDING' : 'STUDENT PROTOCOL • GLOBAL ADMISSIONS PIPELINE'; ?>
            </div>
            <h1 class="application-title">
                <?php echo $is_agent_form ? 'Agent Application' : 'Student Application'; ?>
            </h1>
            <p class="application-subtitle">
                <?php if ($is_agent_form): ?>
                    Share your agency details so UNICOU can onboard you as a verified partner and enable agent dashboards, pricing and workflows.
                <?php else: ?>
                    Submit a single, structured profile for global universities. Our counsellors triage,
                    verify and route your case to the right admissions lane.
                <?php endif; ?>
            </p>
        </div>
    </section>

    <section class="application-layout">
        <div class="application-main">
            <?php if (isset($_GET['submission_success']) && isset($_GET['submission_number'])): ?>
                <div class="application-alert application-alert-success">
                    <div class="application-alert-icon">✓</div>
                    <div class="application-alert-body">
                        <h2>Application submitted successfully</h2>
                        <p>
                            Application number: 
                            <span class="application-number">
                                <?php echo esc_html($_GET['submission_number']); ?>
                            </span>
                        </p>
                        <p>
                            Your application is submitted successfully and is under review. 
                            You will shortly receive an update on mail about your application.
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
                    <?php if ($is_agent_form): ?>
                        <strong>Partner screening.</strong>
                        Provide accurate business details. Management will review and approve or reject your agent profile.
                    <?php else: ?>
                        <strong>Email Verification Required.</strong>
                        After filling in your email address, you must verify it via OTP before submitting. Fields marked with <span>*</span> are mandatory. Use official spellings that match your passport and academic records.
                    <?php endif; ?>
                </div>

                <?php if (isset($_GET['submission_success']) || isset($_GET['submission_error']) || isset($_GET['email_verified'])): ?>
                <script>
                    // Auto-scroll to alert on page load
                    document.addEventListener('DOMContentLoaded', function() {
                        const alert = document.querySelector('.application-alert');
                        if (alert) {
                            alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            // Add pulse animation
                            alert.style.animation = 'pulse 0.5s ease-in-out';
                        }
                    });

                    // Add pulse animation CSS
                    const style = document.createElement('style');
                    style.textContent = `
                        @keyframes pulse {
                            0%, 100% { transform: scale(1); }
                            50% { transform: scale(1.02); }
                        }
                    `;
                    document.head.appendChild(style);
                </script>
                <?php endif; ?>

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

                                        <?php if ($field->field_name === 'email'): ?>
                                            <div style="margin-top: 8px;">
                                                <div id="email-status" style="display: none; padding: 8px 12px; border-radius: 5px; font-size: 13px; font-weight: 600; margin-bottom: 8px;">
                                                    <span id="email-status-text"></span>
                                                </div>
                                                <a href="#" id="verify-email-link" style="display: none; color: #194f68; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #e8f4f8; border-radius: 5px;" onclick="openVerificationPopup(event)">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                    </svg>
                                                    Click here to verify your email
                                                </a>
                                                <span id="email-verified-badge" style="display: none; color: #28a745; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #d4edda; border-radius: 5px;">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <path d="M9 12l2 2 4-4"></path>
                                                    </svg>
                                                    Email Verified
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <input type="hidden" name="application_type" value="<?php echo $is_agent_form ? 'agent' : 'student'; ?>">

                    <?php wp_nonce_field('submit_application', 'application_nonce'); ?>

                    <div class="application-actions" id="submit-section" style="display: none;">
                        <button
                            type="submit"
                            name="submit_application"
                            class="application-submit"
                            id="submit-application-btn"
                        >
                            Submit application
                        </button>
                        <p class="application-actions-note">
                            By submitting, you authorise UNICOU to process your data for admissions,
                            visa and compliance checks.
                        </p>
                    </div>
                </form>

                <!-- Verification Popup Modal -->
                <div id="verification-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; align-items: center; justify-content: center;">
                    <div style="background: #fff; border-radius: 10px; max-width: 500px; width: 90%; padding: 30px; position: relative;">
                        <h3 style="color: #194f68; margin-top: 0;">Email Verification</h3>

                        <div id="verification-step-1">
                            <p style="margin-bottom: 20px;">Click "Send Code" to receive a 6-digit verification code at your email address.</p>
                            <div id="verification-error" style="display: none; background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #f5c6cb;"></div>
                            <button type="button" id="send-otp-btn" onclick="sendVerificationOTP()" style="background: #194f68; color: #fff; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%;">
                                Send Verification Code
                            </button>
                        </div>

                        <div id="verification-step-2" style="display: none;">
                            <p style="margin-bottom: 20px;">A 6-digit verification code has been sent to your email. Please enter it below:</p>
                            <div id="otp-error" style="display: none; background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #f5c6cb;"></div>
                            <input type="text" id="otp-input" maxlength="6" placeholder="Enter 6-digit code" style="width: 100%; padding: 12px; border: 2px solid #194f68; border-radius: 5px; font-size: 18px; text-align: center; letter-spacing: 5px; margin-bottom: 15px;" />
                            <button type="button" id="verify-otp-btn" onclick="verifyOTP()" style="background: #e95134; color: #fff; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%;">
                                Verify Code
                            </button>
                            <button type="button" onclick="backToSendOTP()" style="background: #6c757d; color: #fff; padding: 8px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; width: 100%; margin-top: 10px;">
                                Resend Code
                            </button>
                        </div>

                        <button type="button" onclick="closeVerificationPopup()" style="position: absolute; top: 10px; right: 10px; background: transparent; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
                    </div>
                </div>

                <script>
                let isEmailVerified = false;
                let emailCheckTimeout = null;
                const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const verifyNonce = '<?php echo wp_create_nonce('verify_application_email'); ?>';

                // Real-time email checking
                document.addEventListener('DOMContentLoaded', function() {
                    const emailField = document.getElementById('email');
                    const phoneField = document.getElementById('phone');
                    const emailStatus = document.getElementById('email-status');
                    const emailStatusText = document.getElementById('email-status-text');
                    const verifyLink = document.getElementById('verify-email-link');

                    if (emailField) {
                        emailField.addEventListener('input', function() {
                            const email = this.value.trim();

                            // Clear previous timeout
                            clearTimeout(emailCheckTimeout);

                            // Hide verify link and verified badge while typing
                            verifyLink.style.display = 'none';
                            document.getElementById('email-verified-badge').style.display = 'none';

                            if (!email || !email.includes('@')) {
                                emailStatus.style.display = 'none';
                                return;
                            }

                            // Show checking status
                            emailStatus.style.display = 'block';
                            emailStatus.style.background = '#e8f4f8';
                            emailStatus.style.color = '#194f68';
                            emailStatus.style.border = '1px solid #b8d4e0';
                            emailStatusText.innerHTML = `
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite; display: inline-block; margin-right: 6px;">
                                    <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                                </svg>
                                Checking email availability...
                            `;

                            // Add CSS animation for spinner
                            if (!document.getElementById('spin-animation')) {
                                const style = document.createElement('style');
                                style.id = 'spin-animation';
                                style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                                document.head.appendChild(style);
                            }

                            // Debounce the email check
                            emailCheckTimeout = setTimeout(() => {
                                checkEmailExists(email, phoneField.value);
                            }, 800);
                        });

                        // Also check when phone changes (after email is entered)
                        phoneField.addEventListener('blur', function() {
                            const email = emailField.value.trim();
                            if (email && email.includes('@') && !isEmailVerified) {
                                clearTimeout(emailCheckTimeout);
                                checkEmailExists(email, this.value);
                            }
                        });
                    }
                });

                function checkEmailExists(email, phone) {
                    const emailStatus = document.getElementById('email-status');
                    const emailStatusText = document.getElementById('email-status-text');
                    const verifyLink = document.getElementById('verify-email-link');

                    const formData = new FormData();
                    formData.append('action', 'check_email_exists');
                    formData.append('nonce', verifyNonce);
                    formData.append('email', email);
                    formData.append('phone', phone);

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Email is available
                            emailStatus.style.display = 'block';
                            emailStatus.style.background = '#d1ecf1';
                            emailStatus.style.color = '#0c5460';
                            emailStatus.style.border = '1px solid #bee5eb';
                            emailStatusText.innerHTML = `
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: inline-block; margin-right: 6px;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M9 12l2 2 4-4"></path>
                                </svg>
                                Email is available
                            `;

                            // Show verify link after short delay
                            setTimeout(() => {
                                emailStatus.style.display = 'none';
                                verifyLink.style.display = 'inline-flex';
                            }, 1500);
                        } else {
                            // Email already exists
                            emailStatus.style.display = 'block';
                            emailStatus.style.background = '#f8d7da';
                            emailStatus.style.color = '#721c24';
                            emailStatus.style.border = '1px solid #f5c6cb';
                            emailStatusText.innerHTML = `
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: inline-block; margin-right: 6px;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                </svg>
                                ${data.data.message}
                            `;
                            verifyLink.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Email check error:', error);
                        emailStatus.style.display = 'none';
                        verifyLink.style.display = 'inline-flex';
                    });
                }

                function openVerificationPopup(event) {
                    event.preventDefault();
                    const emailField = document.getElementById('email');
                    const phoneField = document.getElementById('phone');

                    if (!emailField.value || !emailField.value.includes('@')) {
                        alert('Please enter a valid email address first');
                        emailField.focus();
                        return;
                    }

                    if (!phoneField.value) {
                        alert('Please enter your phone number first');
                        phoneField.focus();
                        return;
                    }

                    // Reset modal to step 1
                    document.getElementById('verification-step-1').style.display = 'block';
                    document.getElementById('verification-step-2').style.display = 'none';
                    document.getElementById('verification-error').style.display = 'none';
                    document.getElementById('otp-error').style.display = 'none';
                    document.getElementById('otp-input').value = '';

                    // Show modal
                    document.getElementById('verification-modal').style.display = 'flex';
                }

                function closeVerificationPopup() {
                    document.getElementById('verification-modal').style.display = 'none';
                }

                function sendVerificationOTP() {
                    const emailField = document.getElementById('email');
                    const phoneField = document.getElementById('phone');
                    const email = emailField.value.trim();
                    const phone = phoneField.value.trim();
                    const errorDiv = document.getElementById('verification-error');
                    const sendBtn = document.getElementById('send-otp-btn');

                    if (!email || !email.includes('@')) {
                        errorDiv.textContent = 'Please enter a valid email address';
                        errorDiv.style.display = 'block';
                        return;
                    }

                    // Disable button
                    sendBtn.disabled = true;
                    sendBtn.textContent = 'Sending...';
                    errorDiv.style.display = 'none';

                    // Send AJAX request to send OTP
                    const formData = new FormData();
                    formData.append('action', 'send_verification_otp');
                    formData.append('nonce', verifyNonce);
                    formData.append('email', email);
                    formData.append('phone', phone);

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        sendBtn.disabled = false;
                        sendBtn.textContent = 'Send Verification Code';

                        if (data.success) {
                            // Move to step 2
                            document.getElementById('verification-step-1').style.display = 'none';
                            document.getElementById('verification-step-2').style.display = 'block';
                            document.getElementById('verification-error').style.display = 'none';
                        } else {
                            errorDiv.textContent = data.data.message || 'Failed to send verification code';
                            errorDiv.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        sendBtn.disabled = false;
                        sendBtn.textContent = 'Send Verification Code';
                        errorDiv.textContent = 'Network error. Please try again.';
                        errorDiv.style.display = 'block';
                    });
                }

                function backToSendOTP() {
                    document.getElementById('verification-step-2').style.display = 'none';
                    document.getElementById('verification-step-1').style.display = 'block';
                    document.getElementById('otp-input').value = '';
                    document.getElementById('otp-error').style.display = 'none';
                }

                function verifyOTP() {
                    const emailField = document.getElementById('email');
                    const email = emailField.value.trim();
                    const otp = document.getElementById('otp-input').value.trim();
                    const errorDiv = document.getElementById('otp-error');
                    const verifyBtn = document.getElementById('verify-otp-btn');

                    if (!otp || otp.length !== 6) {
                        errorDiv.textContent = 'Please enter the 6-digit verification code';
                        errorDiv.style.display = 'block';
                        return;
                    }

                    // Disable button
                    verifyBtn.disabled = true;
                    verifyBtn.textContent = 'Verifying...';
                    errorDiv.style.display = 'none';

                    // Send AJAX request to verify OTP
                    const formData = new FormData();
                    formData.append('action', 'verify_otp');
                    formData.append('nonce', verifyNonce);
                    formData.append('email', email);
                    formData.append('otp', otp);

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = 'Verify Code';

                        if (data.success) {
                            // Email verified successfully
                            isEmailVerified = true;
                            closeVerificationPopup();

                            // Update UI
                            document.getElementById('verify-email-link').style.display = 'none';
                            document.getElementById('email-verified-badge').style.display = 'inline';

                            // Show submit button
                            document.getElementById('submit-section').style.display = 'block';

                            // Disable email field
                            emailField.setAttribute('readonly', 'readonly');
                            emailField.style.backgroundColor = '#f5f5f5';
                        } else {
                            errorDiv.textContent = data.data.message || 'Invalid verification code';
                            errorDiv.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = 'Verify Code';
                        errorDiv.textContent = 'Network error. Please try again.';
                        errorDiv.style.display = 'block';
                    });
                }
                </script>
            <?php endif; ?>
        </div>

        <aside class="application-sidebar">
            <div class="application-sidebar-card">
                <h3>How this workflow runs</h3>
                <ul>
                    <li>Application is stored securely inside the UNICO admissions table.</li>
                    <li>Status starts as <span>submitted</span> and is visible to management.</li>
                    <li>Management or administrators can mark it as in review, approved or rejected.</li>
                </ul>
            </div>

            <div class="application-sidebar-card">
                <h3>Who can approve</h3>
                <ul>
                    <li>Users with the Management role access the Management Dashboard.</li>
                    <li>From there, they can update application status and attach internal notes.</li>
                    <li>Every change is tied to their WordPress user account for auditability.</li>
                </ul>
            </div>

            <div class="application-sidebar-card">
                <h3>Typical response SLA</h3>
                <ul>
                    <li>Initial screening within 24–48 working hours.</li>
                    <li>Program shortlisting and documentation review follow immediately after.</li>
                    <li>Escalation to partner institutions for final offer issuance.</li>
                </ul>
            </div>
        </aside>
    </section>
</main>

<?php get_footer(); ?>
