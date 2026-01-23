<?php
/**
 * Custom Bank Transfer Payment Gateway
 * Shows random bank account at checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_unico_bank_transfer_gateway');

function init_unico_bank_transfer_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class Unico_Bank_Transfer_Gateway extends WC_Payment_Gateway {

        /**
         * Constructor
         */
        public function __construct() {
            $this->id = 'unico_bank_transfer';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __('Bank Transfer (Unico)', 'unico-wc');
            $this->method_description = __('Manual bank transfer with random bank account selection and payment proof upload', 'unico-wc');

            // Load settings
            $this->init_form_fields();
            $this->init_settings();

            // Get settings
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        }

        /**
         * Initialize gateway settings form fields
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __('Enable/Disable', 'unico-wc'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable Bank Transfer', 'unico-wc'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __('Title', 'unico-wc'),
                    'type'        => 'text',
                    'description' => __('Payment method title that customers see during checkout.', 'unico-wc'),
                    'default'     => __('Bank Transfer', 'unico-wc'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', 'unico-wc'),
                    'type'        => 'textarea',
                    'description' => __('Payment method description that customers see during checkout.', 'unico-wc'),
                    'default'     => __('Make payment directly into our bank account.', 'unico-wc'),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __('Instructions', 'unico-wc'),
                    'type'        => 'textarea',
                    'description' => __('Instructions shown on the thank you page.', 'unico-wc'),
                    'default'     => __('Your order is under review. We will verify your payment and process your order soon.', 'unico-wc'),
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * Payment fields (shown at checkout)
         */
        public function payment_fields() {
            // Get or select random bank account
            $bank_account = $this->get_checkout_bank_account();

            if (!$bank_account) {
                echo '<div class="woocommerce-error">' . __('No active bank accounts available. Please contact support.', 'unico-wc') . '</div>';
                return;
            }

            // Display description
            if ($this->description) {
                echo wpautop(wptautop($this->description));
            }

            ?>
            <div class="unico-bank-details">
                <h3><?php _e('Bank Account Details', 'unico-wc'); ?></h3>
                <p class="bank-notice"><?php _e('Please transfer the order total to the following bank account:', 'unico-wc'); ?></p>

                <div class="bank-info-box">
                    <table class="bank-details-table">
                        <tr>
                            <th><?php _e('Bank Name:', 'unico-wc'); ?></th>
                            <td><strong><?php echo esc_html($bank_account['bank_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php _e('Account Holder:', 'unico-wc'); ?></th>
                            <td><?php echo esc_html($bank_account['account_holder']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Account Number:', 'unico-wc'); ?></th>
                            <td>
                                <code class="copyable"><?php echo esc_html($bank_account['account_number']); ?></code>
                                <button type="button" class="copy-btn" data-copy="<?php echo esc_attr($bank_account['account_number']); ?>">
                                    <?php _e('Copy', 'unico-wc'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php if (!empty($bank_account['ifsc_code'])): ?>
                        <tr>
                            <th><?php _e('IFSC Code:', 'unico-wc'); ?></th>
                            <td>
                                <code class="copyable"><?php echo esc_html($bank_account['ifsc_code']); ?></code>
                                <button type="button" class="copy-btn" data-copy="<?php echo esc_attr($bank_account['ifsc_code']); ?>">
                                    <?php _e('Copy', 'unico-wc'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($bank_account['swift_code'])): ?>
                        <tr>
                            <th><?php _e('SWIFT Code:', 'unico-wc'); ?></th>
                            <td>
                                <code class="copyable"><?php echo esc_html($bank_account['swift_code']); ?></code>
                                <button type="button" class="copy-btn" data-copy="<?php echo esc_attr($bank_account['swift_code']); ?>">
                                    <?php _e('Copy', 'unico-wc'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($bank_account['branch'])): ?>
                        <tr>
                            <th><?php _e('Branch:', 'unico-wc'); ?></th>
                            <td><?php echo esc_html($bank_account['branch']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <input type="hidden" name="selected_bank_id" value="<?php echo esc_attr($bank_account['id']); ?>">

                <p class="form-row form-row-wide">
                    <label><?php _e('Transaction ID / Reference Number', 'unico-wc'); ?> <span class="required">*</span></label>
                    <input type="text" name="transaction_id" id="transaction_id" class="input-text" required>
                    <small><?php _e('Enter the transaction/reference number from your bank transfer', 'unico-wc'); ?></small>
                </p>

                <p class="form-row form-row-wide">
                    <label><?php _e('Payment Proof (Screenshot)', 'unico-wc'); ?> <span class="required">*</span></label>
                    <input type="file" name="payment_proof" id="payment_proof" accept="image/*" required>
                    <small><?php _e('Upload payment receipt/screenshot (JPG, PNG, WEBP - Max 5MB)', 'unico-wc'); ?></small>
                </p>
            </div>
            <?php
        }

        /**
         * Get or set bank account for checkout
         */
        private function get_checkout_bank_account() {
            $bank_system = Unico_Bank_Accounts::instance();

            // Check if bank account is already stored in session
            if (WC()->session) {
                $stored_bank_id = WC()->session->get('unico_selected_bank_id');

                if ($stored_bank_id) {
                    $bank_account = $bank_system->get_account_by_id($stored_bank_id);
                    if ($bank_account && !empty($bank_account['active'])) {
                        return $bank_account;
                    }
                }
            }

            // Get random bank account
            $bank_account = $bank_system->get_random_active_account();

            // Store in session
            if ($bank_account && WC()->session) {
                WC()->session->set('unico_selected_bank_id', $bank_account['id']);
            }

            return $bank_account;
        }

        /**
         * Validate payment fields
         */
        public function validate_fields() {
            $errors = array();

            // Validate transaction ID
            if (empty($_POST['transaction_id'])) {
                $errors[] = __('Transaction ID is required', 'unico-wc');
            }

            // Validate file upload
            if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = __('Payment proof screenshot is required', 'unico-wc');
            } else {
                $file = $_FILES['payment_proof'];

                // Check file size (5MB max)
                if ($file['size'] > 5 * 1024 * 1024) {
                    $errors[] = __('Payment proof file size must be less than 5MB', 'unico-wc');
                }

                // Check file type
                $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/webp');
                $file_type = wp_check_filetype($file['name']);

                if (!in_array($file['type'], $allowed_types)) {
                    $errors[] = __('Payment proof must be an image (JPG, PNG, or WEBP)', 'unico-wc');
                }
            }

            // Validate bank account
            if (empty($_POST['selected_bank_id'])) {
                $errors[] = __('Bank account selection error. Please refresh and try again', 'unico-wc');
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    wc_add_notice($error, 'error');
                }
                return false;
            }

            return true;
        }

        /**
         * Process payment
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            // Upload payment proof
            $attachment_id = $this->handle_payment_proof_upload($order_id);

            if (is_wp_error($attachment_id)) {
                wc_add_notice($attachment_id->get_error_message(), 'error');
                return array('result' => 'failure');
            }

            // Get bank account details
            $bank_id = sanitize_text_field($_POST['selected_bank_id']);
            $bank_system = Unico_Bank_Accounts::instance();
            $bank_account = $bank_system->get_account_by_id($bank_id);

            // Save order meta
            $order->update_meta_data('_transaction_id', sanitize_text_field($_POST['transaction_id']));
            $order->update_meta_data('_payment_proof_id', $attachment_id);
            $order->update_meta_data('_payment_proof_url', wp_get_attachment_url($attachment_id));
            $order->update_meta_data('_selected_bank_id', $bank_id);
            $order->update_meta_data('_bank_details', wp_json_encode($bank_account));
            $order->update_meta_data('_payment_verified', 'no');

            // Add order note
            $order->add_order_note(
                sprintf(
                    __('Payment proof uploaded. Transaction ID: %s. Awaiting admin verification.', 'unico-wc'),
                    sanitize_text_field($_POST['transaction_id'])
                )
            );

            // Set order status to under-review
            $order->update_status('under-review', __('Payment proof received, awaiting verification.', 'unico-wc'));

            $order->save();

            // Clear cart
            WC()->cart->empty_cart();

            // Clear session bank ID
            if (WC()->session) {
                WC()->session->set('unico_selected_bank_id', null);
            }

            // Return success and redirect to order received page
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        /**
         * Handle payment proof upload
         */
        private function handle_payment_proof_upload($order_id) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            $file = $_FILES['payment_proof'];

            // Set up upload handling
            $upload_overrides = array(
                'test_form' => false,
                'mimes' => array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ),
            );

            $uploaded_file = wp_handle_upload($file, $upload_overrides);

            if (isset($uploaded_file['error'])) {
                return new WP_Error('upload_error', $uploaded_file['error']);
            }

            // Insert attachment
            $attachment = array(
                'post_mime_type' => $uploaded_file['type'],
                'post_title' => 'Payment Proof - Order #' . $order_id,
                'post_content' => '',
                'post_status' => 'inherit',
            );

            $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);

            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }

            // Generate attachment metadata
            if (!function_exists('wp_generate_attachment_metadata')) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }

            $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_data);

            return $attachment_id;
        }

        /**
         * Output for the order received page
         */
        public function thankyou_page($order_id) {
            if ($this->instructions) {
                echo '<div class="unico-thankyou-instructions">';
                echo wpautop(wptautop(wp_kses_post($this->instructions)));
                echo '</div>';
            }

            $order = wc_get_order($order_id);
            $transaction_id = $order->get_meta('_transaction_id');
            $proof_url = $order->get_meta('_payment_proof_url');

            if ($transaction_id) {
                echo '<h2>' . __('Payment Details', 'unico-wc') . '</h2>';
                echo '<p><strong>' . __('Transaction ID:', 'unico-wc') . '</strong> ' . esc_html($transaction_id) . '</p>';

                if ($proof_url) {
                    echo '<p><strong>' . __('Payment Proof:', 'unico-wc') . '</strong> <a href="' . esc_url($proof_url) . '" target="_blank">' . __('View uploaded proof', 'unico-wc') . '</a></p>';
                }
            }
        }
    }
}
