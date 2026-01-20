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

                    <div class="application-actions">
                        <button type="submit" name="submit_application" class="application-submit">
                            Submit Application
                        </button>
                        <p class="application-actions-note">
                            By submitting, you authorise UNICOU to process your business data for
                            partnership verification.
                        </p>
                    </div>
                </form>
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
