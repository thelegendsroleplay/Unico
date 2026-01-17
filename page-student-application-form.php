<?php
/**
 * Template Name: Student Application Form
 */

$application_form = Unico_Application_Form::get_instance();
$sections = $application_form->get_fields_by_section();

get_header();
?>

<main class="application-page">
    <section class="application-hero">
        <div class="application-hero-inner">
            <div class="application-badge">
                STUDENT PROTOCOL • GLOBAL ADMISSIONS PIPELINE
            </div>
            <h1 class="application-title">
                Student Application
            </h1>
            <p class="application-subtitle">
                Submit a single, structured profile for global universities. Our counsellors triage,
                verify and route your case to the right admissions lane.
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
                            counsellor review. Expect an update within 24–48 hours.
                        </p>
                    </div>
                </div>
            <?php else: ?>

                <?php if (isset($_GET['submission_error'])): ?>
                    <div class="application-alert application-alert-error">
                        <div class="application-alert-icon">!</div>
                        <div class="application-alert-body">
                            <h2>Submission failed</h2>
                            <p>There was a problem submitting your application. Please try again.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="application-note">
                    <strong>Verification first.</strong>
                    Fields marked with <span>*</span> are mandatory. Use official spellings that match your
                    passport and academic records.
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

                    <?php wp_nonce_field('submit_application', 'application_nonce'); ?>

                    <div class="application-actions">
                        <button type="submit" name="submit_application" class="application-submit">
                            Submit application
                        </button>
                        <p class="application-actions-note">
                            By submitting, you authorise UNICOU to process your data for admissions,
                            visa and compliance checks.
                        </p>
                    </div>
                </form>
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
