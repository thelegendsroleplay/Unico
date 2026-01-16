<?php
/**
 * Template Name: Student Application Form
 */

$application_form = Unico_Application_Form::get_instance();
$sections = $application_form->get_fields_by_section();

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Application Form - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; color: #333; }
        .form-container { max-width: 900px; margin: 40px auto; padding: 20px; }
        .form-header { background: linear-gradient(135deg, #e84e33 0%, #c43d2a 100%); color: white; padding: 40px; border-radius: 12px 12px 0 0; text-align: center; }
        .form-header h1 { font-size: 32px; margin-bottom: 10px; }
        .form-header p { opacity: 0.9; font-size: 16px; }
        .form-body { background: white; padding: 40px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); }
        .success-message { background: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin-bottom: 30px; border-radius: 8px; }
        .success-message h3 { color: #155724; margin-bottom: 10px; }
        .success-message p { color: #155724; }
        .error-message { background: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; margin-bottom: 30px; border-radius: 8px; }
        .form-section { margin-bottom: 40px; }
        .section-title { font-size: 24px; font-weight: 700; color: #e84e33; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #e84e33; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
        .form-label .required { color: #dc3545; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; font-family: inherit; transition: border-color 0.2s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #e84e33; }
        .form-textarea { min-height: 120px; resize: vertical; }
        .btn-submit { background: #e84e33; color: white; padding: 15px 40px; border: none; border-radius: 8px; font-size: 18px; font-weight: 700; cursor: pointer; transition: background 0.2s; width: 100%; }
        .btn-submit:hover { background: #d43f2a; }
        .form-note { background: #e7f3ff; border-left: 4px solid #0066cc; padding: 15px; margin-bottom: 30px; border-radius: 8px; font-size: 14px; }
    </style>
</head>
<body>

<div class="form-container">
    <div class="form-header">
        <h1>Student Application Form</h1>
        <p>Start your study abroad journey with us. Fill out this form and our counselors will contact you within 24-48 hours.</p>
    </div>

    <div class="form-body">

        <?php if (isset($_GET['submission_success']) && isset($_GET['submission_number'])): ?>
        <div class="success-message">
            <h3>✓ Application Submitted Successfully!</h3>
            <p><strong>Your Application Number:</strong> <span style="font-size: 18px;"><?php echo esc_html($_GET['submission_number']); ?></span></p>
            <p>Thank you for applying. We've sent a confirmation email with your application number. Our counselling team will review your application and contact you soon.</p>
        </div>
        <?php return; // Stop rendering form after success
        endif; ?>

        <?php if (isset($_GET['submission_error'])): ?>
        <div class="error-message">
            <strong>Error:</strong> There was a problem submitting your application. Please try again.
        </div>
        <?php endif; ?>

        <div class="form-note">
            <strong>Note:</strong> All fields marked with <span style="color: #dc3545;">*</span> are required. Please ensure all information is accurate.
        </div>

        <form method="post" action="">
            <?php foreach ($sections as $section_name => $fields): ?>
            <div class="form-section">
                <h2 class="section-title"><?php echo esc_html($section_name); ?></h2>

                <div class="form-grid">
                    <?php foreach ($fields as $field): ?>
                    <div class="form-group <?php echo $field->field_type === 'textarea' ? 'full-width' : ''; ?>">
                        <label class="form-label" for="<?php echo esc_attr($field->field_name); ?>">
                            <?php echo esc_html($field->field_label); ?>
                            <?php if ($field->field_required): ?>
                            <span class="required">*</span>
                            <?php endif; ?>
                        </label>

                        <?php if ($field->field_type === 'select'): 
                            $options = json_decode($field->field_options, true) ?: [];
                        ?>
                        <select 
                            name="<?php echo esc_attr($field->field_name); ?>" 
                            id="<?php echo esc_attr($field->field_name); ?>"
                            class="form-select"
                            <?php echo $field->field_required ? 'required' : ''; ?>
                        >
                            <option value="">-- Select --</option>
                            <?php foreach ($options as $option): ?>
                            <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <?php elseif ($field->field_type === 'textarea'): ?>
                        <textarea
                            name="<?php echo esc_attr($field->field_name); ?>"
                            id="<?php echo esc_attr($field->field_name); ?>"
                            class="form-textarea"
                            placeholder="<?php echo esc_attr($field->field_placeholder); ?>"
                            <?php echo $field->field_required ? 'required' : ''; ?>
                        ></textarea>

                        <?php else: ?>
                        <input
                            type="<?php echo esc_attr($field->field_type); ?>"
                            name="<?php echo esc_attr($field->field_name); ?>"
                            id="<?php echo esc_attr($field->field_name); ?>"
                            class="form-input"
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

            <button type="submit" name="submit_application" class="btn-submit">
                Submit Application
            </button>
        </form>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
