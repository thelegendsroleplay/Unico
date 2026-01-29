<?php
/**
 * Template Name: Management Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

if (!Unico_User_Roles::user_can('access_management_dashboard') && !current_user_can('administrator')) {
    wp_die(
        '<h1>Access Denied</h1><p>You do not have permission to access the Management Dashboard.</p><p><a href="' . home_url() . '">Return to Home</a></p>',
        'Access Denied',
        array('response' => 403)
    );
}

// Get overview data
global $wpdb;

$voucher_system = Unico_Voucher_System::get_instance();
$voucher_stats = $voucher_system->get_voucher_stats();

$application_form = Unico_Application_Form::get_instance();

$mgmt_notices = [];

if (isset($_POST['unico_add_exam_definition']) && isset($_POST['exam_definition_nonce']) && wp_verify_nonce($_POST['exam_definition_nonce'], 'unico_add_exam_definition')) {
    $exam_product_name = isset($_POST['exam_product_name']) ? sanitize_text_field($_POST['exam_product_name']) : '';
    $exam_family = isset($_POST['exam_family']) ? sanitize_text_field($_POST['exam_family']) : '';
    $exam_price = isset($_POST['exam_price']) && $_POST['exam_price'] !== '' ? floatval($_POST['exam_price']) : null;
    $exam_currency = isset($_POST['exam_currency']) ? sanitize_text_field($_POST['exam_currency']) : '';
    $exam_price_nature = isset($_POST['exam_price_nature']) ? sanitize_text_field($_POST['exam_price_nature']) : '';

    if (!$exam_product_name) {
        $mgmt_notices[] = [
            'type' => 'error',
            'message' => 'Exam product name is required.'
        ];
    } else {
        $slug = sanitize_title($exam_product_name);
        $product_post = get_page_by_path($slug, OBJECT, 'product');
        if ($product_post) {
            $product_id = $product_post->ID;
        } else {
            $product_id = wp_insert_post([
                'post_title' => $exam_product_name,
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'product',
                'post_author' => $user_id
            ]);
        }

        if (is_wp_error($product_id) || !$product_id) {
            $mgmt_notices[] = [
                'type' => 'error',
                'message' => 'Failed to create exam product.'
            ];
        } else {
            if ($exam_price !== null && $exam_price >= 0) {
                update_post_meta($product_id, '_regular_price', $exam_price);
                update_post_meta($product_id, '_price', $exam_price);
            }
            update_post_meta($product_id, '_virtual', 'yes');
            update_post_meta($product_id, '_downloadable', 'no');
            update_post_meta($product_id, '_manage_stock', 'no');
            update_post_meta($product_id, '_stock_status', 'instock');

            $term = get_term_by('slug', 'vouchers', 'product_cat');
            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($product_id, [(int) $term->term_id], 'product_cat', true);
            } else {
                $inserted = wp_insert_term('Vouchers', 'product_cat', ['slug' => 'vouchers']);
                if (!is_wp_error($inserted) && isset($inserted['term_id'])) {
                    wp_set_object_terms($product_id, [(int) $inserted['term_id']], 'product_cat', true);
                }
            }

            $exam_meta = $exam_family ? $exam_family : $exam_product_name;
            update_post_meta($product_id, 'exam_name', $exam_meta);
            if ($exam_currency) {
                update_post_meta($product_id, 'price_currency', $exam_currency);
            }
            if ($exam_price_nature) {
                update_post_meta($product_id, 'price_nature', $exam_price_nature);
            }
            update_post_meta($product_id, 'is_voucher', 'yes');

            $mgmt_notices[] = [
                'type' => 'success',
                'message' => 'Exam added to catalog.'
            ];
        }
    }
}

if (isset($_POST['unico_update_voucher_pricing']) && isset($_POST['voucher_pricing_nonce']) && wp_verify_nonce($_POST['voucher_pricing_nonce'], 'unico_update_voucher_pricing')) {
    $product_ids = [];

    $clicked_product_id = isset($_POST['unico_update_voucher_pricing']) ? intval($_POST['unico_update_voucher_pricing']) : 0;

    if ($clicked_product_id > 0) {
        $product_ids[] = $clicked_product_id;
    } elseif (isset($_POST['voucher_product_id']) && is_array($_POST['voucher_product_id'])) {
        foreach ($_POST['voucher_product_id'] as $product_id) {
            $product_id = intval($product_id);
            if ($product_id > 0) {
                $product_ids[] = $product_id;
            }
        }
    }

    if (!empty($product_ids)) {
        foreach ($product_ids as $product_id) {
            $official_key = 'official_price_' . $product_id;
            $agent_key = 'agent_price_' . $product_id;
            $official = isset($_POST[$official_key]) ? floatval($_POST[$official_key]) : null;
            $agent = isset($_POST[$agent_key]) ? floatval($_POST[$agent_key]) : null;
            if ($official !== null && $official >= 0) {
                update_post_meta($product_id, '_voucher_official_price', $official);
                update_post_meta($product_id, '_regular_price', $official);
                update_post_meta($product_id, '_price', $official);
            }
            if ($agent !== null && $agent >= 0) {
                update_post_meta($product_id, '_voucher_agent_price', $agent);
            }
        }
        $mgmt_notices[] = [
            'type' => 'success',
            'message' => 'Voucher pricing updated for selected voucher.'
        ];
    }
}

if (isset($_POST['unico_add_product_stock']) && isset($_POST['voucher_stock_nonce']) && wp_verify_nonce($_POST['voucher_stock_nonce'], 'unico_adjust_voucher_stock')) {
    $product_id = isset($_POST['unico_add_product_stock']) ? intval($_POST['unico_add_product_stock']) : 0;
    $quantity_key = $product_id > 0 ? 'stock_add_' . $product_id : '';
    $quantity = $quantity_key && isset($_POST[$quantity_key]) ? intval($_POST[$quantity_key]) : 0;
    if ($product_id <= 0 || $quantity <= 0) {
        $mgmt_notices[] = [
            'type' => 'error',
            'message' => 'Select a voucher and enter a valid stock quantity.'
        ];
    } else {
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'product') {
            $mgmt_notices[] = [
                'type' => 'error',
                'message' => 'Unable to load selected voucher product.'
            ];
        } else {
            $exam_name = get_post_meta($product_id, 'exam_name', true);
            if (!$exam_name) {
                $exam_name = $product->post_title;
            }
            if (!$exam_name) {
                $mgmt_notices[] = [
                    'type' => 'error',
                    'message' => 'Exam name is missing for this voucher product.'
                ];
            } else {
                $official_price = get_post_meta($product_id, '_voucher_official_price', true);
                if ($official_price === '') {
                    $official_price = get_post_meta($product_id, '_regular_price', true);
                }
                $purchase_price = 0;
                $selling_price = $official_price !== '' ? floatval($official_price) : 0;
                $expiry_date = null;
                $created = 0;
                $failed = 0;
                for ($i = 0; $i < $quantity; $i++) {
                    $random_code = strtoupper(substr(hash('sha256', $exam_name . '|' . microtime(true) . '|' . wp_rand()), 0, 16));
                    $result = $voucher_system->add_voucher([
                        'voucher_code' => $random_code,
                        'exam_name' => $exam_name,
                        'purchase_price' => $purchase_price,
                        'selling_price' => $selling_price,
                        'expiry_date' => $expiry_date
                    ]);
                    if (is_wp_error($result)) {
                        $failed++;
                    } else {
                        $created++;
                    }
                    usleep(5000);
                }
                if ($created > 0) {
                    $mgmt_notices[] = [
                        'type' => 'success',
                        'message' => $created . ' voucher code(s) added to inventory for this exam.'
                    ];
                }
                if ($failed > 0 && $created === 0) {
                    $mgmt_notices[] = [
                        'type' => 'error',
                        'message' => 'Failed to add voucher stock for this exam. Please try again.'
                    ];
                } elseif ($failed > 0 && $created > 0) {
                    $mgmt_notices[] = [
                        'type' => 'warning',
                        'message' => 'Some voucher codes could not be created. Added ' . $created . ' voucher(s); ' . $failed . ' failed.'
                    ];
                }
            }
        }
    }
}

if (isset($_POST['unico_remove_product_stock']) && isset($_POST['voucher_stock_nonce']) && wp_verify_nonce($_POST['voucher_stock_nonce'], 'unico_adjust_voucher_stock')) {
    $product_id = isset($_POST['unico_remove_product_stock']) ? intval($_POST['unico_remove_product_stock']) : 0;
    $quantity_key = $product_id > 0 ? 'stock_remove_' . $product_id : '';
    $quantity = $quantity_key && isset($_POST[$quantity_key]) ? intval($_POST[$quantity_key]) : 0;
    if ($product_id <= 0 || $quantity <= 0) {
        $mgmt_notices[] = [
            'type' => 'error',
            'message' => 'Select a voucher and enter a valid stock quantity to remove.'
        ];
    } else {
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'product') {
            $mgmt_notices[] = [
                'type' => 'error',
                'message' => 'Unable to load selected voucher product.'
            ];
        } else {
            $exam_name = get_post_meta($product_id, 'exam_name', true);
            if (!$exam_name) {
                $exam_name = $product->post_title;
            }
            if (!$exam_name) {
                $mgmt_notices[] = [
                    'type' => 'error',
                    'message' => 'Exam name is missing for this voucher product.'
                ];
            } else {
                global $wpdb;
                $table = $wpdb->prefix . 'unico_vouchers';
                $available_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM $table WHERE exam_name = %s AND voucher_status = 'available' ORDER BY created_at DESC LIMIT %d",
                    $exam_name,
                    $quantity
                ));
                if (empty($available_ids)) {
                    $mgmt_notices[] = [
                        'type' => 'error',
                        'message' => 'No available vouchers found to remove for this exam.'
                    ];
                } else {
                    $deleted_count = 0;
                    foreach ($available_ids as $vid) {
                        $deleted = $wpdb->delete($table, ['id' => intval($vid), 'voucher_status' => 'available'], ['%d', '%s']);
                        if ($deleted) {
                            $deleted_count++;
                        }
                    }
                    if ($deleted_count > 0) {
                        $mgmt_notices[] = [
                            'type' => 'success',
                            'message' => $deleted_count . ' available voucher(s) removed from inventory for this exam.'
                        ];
                    } else {
                        $mgmt_notices[] = [
                            'type' => 'error',
                            'message' => 'Unable to remove vouchers from inventory for this exam.'
                        ];
                    }
                }
            }
        }
    }
}

if (isset($_POST['unico_user_approval_action']) && isset($_POST['user_approval_nonce']) && wp_verify_nonce($_POST['user_approval_nonce'], 'unico_user_approval_action')) {
    $approval_id = isset($_POST['approval_id']) ? intval($_POST['approval_id']) : 0;
    $action = isset($_POST['unico_user_approval_action']) ? sanitize_text_field($_POST['unico_user_approval_action']) : '';
    $remarks = isset($_POST['approval_remarks']) ? sanitize_textarea_field($_POST['approval_remarks']) : '';
    if ($approval_id > 0 && $action) {
        $approvals_table = $wpdb->prefix . 'unico_user_approvals';
        $approval = $wpdb->get_row($wpdb->prepare("SELECT * FROM $approvals_table WHERE id = %d", $approval_id));
        if ($approval) {
            if ($action === 'approve') {
                $user_to_update = get_userdata($approval->user_id);
                if ($user_to_update) {
                    $roles = [];
                    if (class_exists('Unico_User_Roles') && method_exists('Unico_User_Roles', 'get_custom_roles')) {
                        $roles = Unico_User_Roles::get_custom_roles();
                    }
                    if (empty($roles) || isset($roles[$approval->requested_role])) {
                        $user_to_update->set_role($approval->requested_role);
                    }
                    if (!empty($user_to_update->user_email)) {
                        $new_password = wp_generate_password(12, true);
                        wp_set_password($new_password, $user_to_update->ID);
                        $login_url = home_url('/login');
                        $subject = 'Your account has been approved';
                        $message = "
                        <html>
                        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                            <h2 style='color:#194f68;'>Account Approved</h2>
                            <p>Your account request has been approved. You can now log in using the details below:</p>
                            <p><strong>Login URL:</strong> <a href='{$login_url}'>{$login_url}</a></p>
                            <p><strong>Username:</strong> {$user_to_update->user_email}</p>
                            <p><strong>Password:</strong> {$new_password}</p>
                            <p style='margin-top:24px;color:#666;'>For security, please change this password after your first login.</p>
                        </body>
                        </html>
                        ";
                        $headers = ['Content-Type: text/html; charset=UTF-8'];
                        wp_mail($user_to_update->user_email, $subject, $message, $headers);
                    }
                }
                if (class_exists('Unico_User_Roles') && method_exists('Unico_User_Roles', 'set_user_approval_status')) {
                    Unico_User_Roles::set_user_approval_status($approval_id, 'approved', $user_id, $remarks);
                }
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => 'User approval marked as approved.'
                ];
            } elseif ($action === 'reject') {
                if (class_exists('Unico_User_Roles') && method_exists('Unico_User_Roles', 'set_user_approval_status')) {
                    Unico_User_Roles::set_user_approval_status($approval_id, 'rejected', $user_id, $remarks);
                }
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => 'User approval marked as rejected.'
                ];
            }
        }
    }
}

if (isset($_POST['unico_add_voucher']) && isset($_POST['voucher_inventory_nonce']) && wp_verify_nonce($_POST['voucher_inventory_nonce'], 'unico_add_voucher')) {
    $exam_name = isset($_POST['voucher_exam_name']) ? sanitize_text_field($_POST['voucher_exam_name']) : '';
    $voucher_code = isset($_POST['voucher_code']) ? sanitize_text_field($_POST['voucher_code']) : '';
    $auto_generate_count = isset($_POST['auto_generate_count']) ? intval($_POST['auto_generate_count']) : 0;
    $purchase_price = isset($_POST['voucher_purchase_price']) ? floatval($_POST['voucher_purchase_price']) : 0;
    $selling_price = isset($_POST['voucher_selling_price']) ? floatval($_POST['voucher_selling_price']) : 0;
    $expiry_date = isset($_POST['voucher_expiry_date']) && $_POST['voucher_expiry_date'] !== '' ? sanitize_text_field($_POST['voucher_expiry_date']) : null;
    if ($exam_name) {
        if ($auto_generate_count > 0) {
            $created = 0;
            $failed = 0;
            for ($i = 0; $i < $auto_generate_count; $i++) {
                $random_code = strtoupper(substr(hash('sha256', $exam_name . '|' . microtime(true) . '|' . wp_rand()), 0, 16));
                $result = $voucher_system->add_voucher([
                    'voucher_code' => $random_code,
                    'exam_name' => $exam_name,
                    'purchase_price' => $purchase_price,
                    'selling_price' => $selling_price,
                    'expiry_date' => $expiry_date
                ]);
                if (is_wp_error($result)) {
                    $failed++;
                } else {
                    $created++;
                }
                usleep(5000);
            }
            if ($created > 0) {
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => $created . ' voucher code(s) auto-generated and added to inventory.'
                ];
            }
            if ($failed > 0 && $created === 0) {
                $mgmt_notices[] = [
                    'type' => 'error',
                    'message' => 'Failed to auto-generate vouchers. Please try again.'
                ];
            }
        } elseif ($voucher_code) {
            $result = $voucher_system->add_voucher([
                'voucher_code' => $voucher_code,
                'exam_name' => $exam_name,
                'purchase_price' => $purchase_price,
                'selling_price' => $selling_price,
                'expiry_date' => $expiry_date
            ]);
            if (is_wp_error($result)) {
                $mgmt_notices[] = [
                    'type' => 'error',
                    'message' => $result->get_error_message()
                ];
            } else {
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => 'Voucher added to inventory.'
                ];
            }
        } else {
            $mgmt_notices[] = [
                'type' => 'error',
                'message' => 'Either enter a voucher code or specify quantity to auto-generate.'
            ];
        }
    } else {
        $mgmt_notices[] = [
            'type' => 'error',
            'message' => 'Exam name is required.'
        ];
    }
}

if (isset($_POST['unico_delete_voucher']) && isset($_POST['voucher_delete_nonce']) && wp_verify_nonce($_POST['voucher_delete_nonce'], 'unico_delete_voucher')) {
    $voucher_id = isset($_POST['voucher_id']) ? intval($_POST['voucher_id']) : 0;
    if ($voucher_id > 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_vouchers';
        $deleted = $wpdb->delete($table, ['id' => $voucher_id, 'voucher_status' => 'available'], ['%d', '%s']);
        if ($deleted) {
            $mgmt_notices[] = [
                'type' => 'success',
                'message' => 'Voucher deleted from inventory.'
            ];
        } else {
            $mgmt_notices[] = [
                'type' => 'error',
                'message' => 'Unable to delete voucher. Only available vouchers can be removed.'
            ];
        }
    }
}

if (isset($_POST['unico_request_payment_proof']) && isset($_POST['order_management_nonce']) && wp_verify_nonce($_POST['order_management_nonce'], 'unico_update_order_status')) {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if ($order_id > 0) {
        $order = new Unico_Order($order_id);
        if ($order->get_id()) {
            $message = 'Please provide payment proof screenshot or transaction receipt for verification.';
            $order->add_note($message);
            $mgmt_notices[] = [
                'type' => 'success',
                'message' => 'Payment proof requested from customer for order #' . $order->get_order_number() . '.'
            ];
        }
    }
}

if (isset($_POST['unico_verify_and_deliver']) && isset($_POST['order_management_nonce']) && wp_verify_nonce($_POST['order_management_nonce'], 'unico_update_order_status')) {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if ($order_id > 0) {
        $order = new Unico_Order($order_id);
        if ($order->get_id()) {
            $order->update_meta('_voucher_verification_status', 'verified');
            $order->add_note('Management verified payment for voucher order. Triggering voucher delivery.');
            $voucher_system->auto_deliver_vouchers($order_id, $order);

            // Reload order to check status
            $order = new Unico_Order($order_id);
            if ($order && $order->get_meta('_vouchers_delivered')) {
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => 'Vouchers delivered for order #' . $order->get_order_number() . '.'
                ];
            } else {
                $mgmt_notices[] = [
                    'type' => 'error',
                    'message' => 'Voucher delivery could not be completed. Check stock and order notes.'
                ];
            }
        }
    }
}

// Payment Approval Handler (matches wp-admin verification)
if (isset($_POST['unico_approve_payment']) && isset($_POST['order_management_nonce']) && wp_verify_nonce($_POST['order_management_nonce'], 'unico_update_order_status')) {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if ($order_id > 0) {
        $wc_order = wc_get_order($order_id);
        if ($wc_order) {
            // Check if codes already exist
            $existing_codes = $wc_order->get_meta('_unico_voucher_codes', true);
            if (!empty($existing_codes) && is_array($existing_codes)) {
                $wc_order->add_order_note('Approval attempted but codes already exist. No new codes generated.');
                $mgmt_notices[] = [
                    'type' => 'warning',
                    'message' => 'Order #' . $wc_order->get_order_number() . ' has already been approved.'
                ];
            } else {
                // Calculate total quantity
                $total_qty = 0;
                foreach ($wc_order->get_items() as $item) {
                    $total_qty += (int) $item->get_quantity();
                }

                if ($total_qty > 0) {
                    // Generate voucher codes
                    if (class_exists('Unico_VC_Voucher_Generator')) {
                        $codes = Unico_VC_Voucher_Generator::generate_codes($total_qty);
                        $wc_order->update_meta_data('_unico_voucher_codes', $codes);
                        $wc_order->update_meta_data('_unico_approved_email_sent', current_time('mysql'));
                        $wc_order->update_meta_data('_voucher_verification_status', 'approved');
                        $wc_order->add_order_note('Payment approved by management. Voucher codes generated and email sent.');
                        $wc_order->set_status('completed');
                        $wc_order->save();

                        // Send approval email
                        if (class_exists('Unico_VC_Emails')) {
                            Unico_VC_Emails::instance()->send_approved($order_id);
                        }

                        $mgmt_notices[] = [
                            'type' => 'success',
                            'message' => 'Order #' . $wc_order->get_order_number() . ' approved. Voucher codes generated and customer notified via email.'
                        ];
                    } else {
                        $mgmt_notices[] = [
                            'type' => 'error',
                            'message' => 'Voucher generator class not found.'
                        ];
                    }
                } else {
                    $mgmt_notices[] = [
                        'type' => 'error',
                        'message' => 'No items to generate codes for in this order.'
                    ];
                }
            }
        }
    }
}

// Payment Rejection Handler (matches wp-admin verification)
if (isset($_POST['unico_reject_payment']) && isset($_POST['order_management_nonce']) && wp_verify_nonce($_POST['order_management_nonce'], 'unico_update_order_status')) {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $reject_reason = isset($_POST['reject_reason']) ? sanitize_textarea_field($_POST['reject_reason']) : '';

    if ($order_id > 0) {
        $wc_order = wc_get_order($order_id);
        if ($wc_order) {
            if ($wc_order->get_status() === 'cancelled') {
                $wc_order->add_order_note('Rejection attempted but order already cancelled.');
                $mgmt_notices[] = [
                    'type' => 'warning',
                    'message' => 'Order #' . $wc_order->get_order_number() . ' is already cancelled.'
                ];
            } else {
                $wc_order->update_meta_data('_unico_reject_reason', $reject_reason);
                $wc_order->update_meta_data('_unico_rejected_email_sent', current_time('mysql'));
                $wc_order->update_meta_data('_voucher_verification_status', 'rejected');
                $wc_order->add_order_note('Payment rejected by management. Reason: ' . ($reject_reason ?: 'No reason provided') . ' | Email sent to customer.');
                $wc_order->set_status('cancelled');
                $wc_order->save();

                // Send rejection email
                if (class_exists('Unico_VC_Emails')) {
                    Unico_VC_Emails::instance()->send_rejected($order_id);
                }

                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => 'Order #' . $wc_order->get_order_number() . ' rejected. Customer notified via email.'
                ];
            }
        }
    }
}

if (isset($_POST['unico_update_order_status']) && isset($_POST['order_management_nonce']) && wp_verify_nonce($_POST['order_management_nonce'], 'unico_update_order_status')) {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';
    $order_note = isset($_POST['order_note']) ? sanitize_textarea_field($_POST['order_note']) : '';
    if ($order_id > 0 && $new_status) {
        $order = new Unico_Order($order_id);
        if ($order->get_id()) {
            // Simple validation of status
            $valid_statuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded', 'failed', 'pending-verification'];
            if (in_array($new_status, $valid_statuses, true)) {
                if ($order_note !== '') {
                    $order->add_note($order_note);
                }
                $order->update_status($new_status);
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => 'Order #' . $order->get_order_number() . ' updated.'
                ];
            } else {
                $mgmt_notices[] = [
                    'type' => 'error',
                    'message' => 'Invalid order status.'
                ];
            }
        }
    }
}

if (isset($_POST['user_management_action']) && isset($_POST['user_management_nonce']) && wp_verify_nonce($_POST['user_management_nonce'], 'unico_user_management')) {
    $target_user_id = isset($_POST['target_user_id']) ? intval($_POST['target_user_id']) : 0;
    $action = isset($_POST['user_management_action']) ? sanitize_text_field($_POST['user_management_action']) : '';
    if ($action === 'add_user') {
        $email = isset($_POST['new_user_email']) ? sanitize_email($_POST['new_user_email']) : '';
        $full_name = isset($_POST['new_user_full_name']) ? sanitize_text_field($_POST['new_user_full_name']) : '';
        $role = isset($_POST['new_user_role']) ? sanitize_text_field($_POST['new_user_role']) : 'unico_customer';
        $phone = isset($_POST['new_user_phone']) ? sanitize_text_field($_POST['new_user_phone']) : '';
        if (!$email || !is_email($email)) {
            $mgmt_notices[] = [
                'type' => 'error',
                'message' => 'Enter a valid email address.'
            ];
        } elseif (email_exists($email)) {
            $mgmt_notices[] = [
                'type' => 'error',
                'message' => 'A user with this email already exists.'
            ];
        } else {
            $password = wp_generate_password(12, true);
            $created_id = wp_create_user($email, $password, $email);
            if (is_wp_error($created_id)) {
                $mgmt_notices[] = [
                    'type' => 'error',
                    'message' => 'Failed to create user.'
                ];
            } else {
                if ($full_name) {
                    $name_parts = explode(' ', $full_name, 2);
                    wp_update_user([
                        'ID' => $created_id,
                        'first_name' => $name_parts[0],
                        'last_name' => isset($name_parts[1]) ? $name_parts[1] : '',
                        'display_name' => $full_name
                    ]);
                }
                $created_user = new WP_User($created_id);
                $created_user->set_role($role);
                if ($phone) {
                    update_user_meta($created_id, 'billing_phone', $phone);
                }
                if (class_exists('Unico_User_Roles') && method_exists('Unico_User_Roles', 'create_user_approval')) {
                    Unico_User_Roles::create_user_approval($created_id, $role);
                }
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => 'User created.'
                ];
            }
        }
    } elseif ($target_user_id > 0 && $action) {
        if ($target_user_id === $user_id && in_array($action, ['delete', 'ban'], true)) {
            $mgmt_notices[] = [
                'type' => 'error',
                'message' => 'You cannot perform this action on your own account.'
            ];
        } else {
            if ($action === 'ban') {
                update_user_meta($target_user_id, 'unico_banned', 1);
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => 'User has been blocked.'
                ];
            } elseif ($action === 'unban') {
                delete_user_meta($target_user_id, 'unico_banned');
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => 'User has been unblocked.'
                ];
            } elseif ($action === 'delete') {
                $result = wp_delete_user($target_user_id);
                if ($result) {
                    $mgmt_notices[] = [
                        'type' => 'success',
                        'message' => 'User deleted.'
                    ];
                } else {
                    $mgmt_notices[] = [
                        'type' => 'error',
                        'message' => 'Failed to delete user.'
                    ];
                }
            } elseif ($action === 'reset_password') {
                $target_user = get_userdata($target_user_id);
                if ($target_user && function_exists('retrieve_password')) {
                    retrieve_password($target_user->user_login);
                    $mgmt_notices[] = [
                        'type' => 'success',
                        'message' => 'Password reset email sent.'
                    ];
                } else {
                    $mgmt_notices[] = [
                        'type' => 'error',
                        'message' => 'Unable to send password reset email.'
                    ];
                }
            }
        }
    }
}

if (isset($_POST['update_application_status']) && isset($_POST['application_id']) && isset($_POST['new_status']) && isset($_POST['application_status_nonce']) && wp_verify_nonce($_POST['application_status_nonce'], 'update_application_status')) {
    $application_id = intval($_POST['application_id']);
    $new_status = sanitize_text_field($_POST['new_status']);
    $notes = isset($_POST['status_notes']) ? sanitize_textarea_field($_POST['status_notes']) : '';

    if ($application_id > 0 && $new_status) {
        if ($new_status === 'approved') {
            // Use the approve_application method which creates user and sends credentials
            $result = $application_form->approve_application($application_id);
            if ($result['success']) {
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => $result['message']
                ];
            } else {
                $mgmt_notices[] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
            }
        } elseif ($new_status === 'rejected') {
            // Use the reject_application method which sends rejection email and deletes data
            $result = $application_form->reject_application($application_id, $notes);
            if ($result['success']) {
                $mgmt_notices[] = [
                    'type' => 'success',
                    'message' => $result['message']
                ];
            } else {
                $mgmt_notices[] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
            }
        } else {
            // For other statuses (submitted, in_review), just update the status
            $application_form->update_status($application_id, $new_status, $notes);
            $mgmt_notices[] = [
                'type' => 'success',
                'message' => 'Application status updated to ' . ucfirst(str_replace('_', ' ', $new_status)) . '.'
            ];
        }
    }
}

// Handle notification preferences
if (isset($_POST['update_notification_prefs']) && isset($_POST['notification_prefs_nonce']) && wp_verify_nonce($_POST['notification_prefs_nonce'], 'update_notification_prefs')) {
    $receive_notifications = isset($_POST['receive_new_application_notifications']) ? true : false;

    if ($receive_notifications) {
        // Add user to notification recipients
        if (!$application_form->is_notification_recipient($user_id, 'new_application')) {
            $application_form->add_notification_recipient($user_id, 'new_application');
            $mgmt_notices[] = [
                'type' => 'success',
                'message' => 'You will now receive email notifications for new applications.'
            ];
        }
    } else {
        // Remove user from notification recipients
        if ($application_form->is_notification_recipient($user_id, 'new_application')) {
            $application_form->remove_notification_recipient($user_id, 'new_application');
            $mgmt_notices[] = [
                'type' => 'success',
                'message' => 'You will no longer receive email notifications for new applications.'
            ];
        }
    }
}
 
$recent_applications = $application_form->get_submissions('all', 10);

// Get user counts
$customer_count = count(get_users(['role' => 'unico_customer']));
$agent_count = count(get_users(['role' => 'unico_agent']));
$reseller_count = count(get_users(['role' => 'unico_reseller']));

// Get order stats (last 30 days)
$orders_30days = Unico_Order::get_orders([
    'date_created' => date('Y-m-d', strtotime('-30 days')) . '...' . date('Y-m-d'),
    'limit' => -1
]);

$revenue_30days = array_sum(array_map(function($order) {
    return $order->get_total();
}, $orders_30days));

// Get ticket stats
$tickets_table = $wpdb->prefix . 'unico_support_tickets';
$open_tickets = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table WHERE status = 'open'");
$total_tickets = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table");

// Get security stats
$security_table = $wpdb->prefix . 'unico_security_checks';
$high_risk_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $security_table WHERE risk_score >= 70");

// Activity stats
$logs_table = $wpdb->prefix . 'unico_activity_logs';
$active_users_30days = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT user_id) FROM $logs_table WHERE created_at >= %s",
    date('Y-m-d H:i:s', strtotime('-30 days'))
));

// Global order stats
$orders_completed_all = Unico_Order::get_orders([
    'status' => ['processing', 'completed'],
    'limit' => -1
]);

$total_orders_all = count($orders_completed_all);
$total_revenue_all = array_sum(array_map(function($order) {
    return $order->get_total();
}, $orders_completed_all));

$orders_today = Unico_Order::get_orders([
    'date_created' => date('Y-m-d') . '...' . date('Y-m-d'),
    'status' => ['processing', 'completed'],
    'limit' => -1
]);

$today_orders_count = count($orders_today);
$today_revenue = array_sum(array_map(function($order) {
    return $order->get_total();
}, $orders_today));

// Orders per day for last 7 days
$orders_per_day = [];
foreach ($orders_30days as $order) {
    $created = $order->get_date_created();
    if (!$created) {
        continue;
    }
    $day_key = date('Y-m-d', strtotime($created));
    if (!isset($orders_per_day[$day_key])) {
        $orders_per_day[$day_key] = 0;
    }
    $orders_per_day[$day_key]++;
}

$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date_i18n('M j', strtotime($day));
    $chart_values[] = isset($orders_per_day[$day]) ? $orders_per_day[$day] : 0;
}

// Pending approvals
$approvals_table = $wpdb->prefix . 'unico_user_approvals';
$pending_approvals = $wpdb->get_results("SELECT * FROM $approvals_table WHERE status = 'pending' ORDER BY created_at DESC LIMIT 50");

// Recent vouchers
$vouchers_table = $wpdb->prefix . 'unico_vouchers';
$recent_vouchers = $wpdb->get_results("SELECT * FROM $vouchers_table ORDER BY created_at DESC LIMIT 25");

// Recent orders
$recent_orders = Unico_Order::get_orders([
    'limit' => 20,
    'orderby' => 'created_at',
    'order' => 'DESC'
]);

// Users
$managed_users = get_users([
    'number' => 20,
    'orderby' => 'registered',
    'order' => 'DESC'
]);

get_header();
?>

<div class="mgmt-dashboard-container">
    <div class="mgmt-dashboard-header">
        <div class="mgmt-header-main">
            <h1>Management Dashboard</h1>
            <p>System overview, approvals, vouchers, orders, and users</p>
        </div>
        <div class="mgmt-header-meta">
            <span><?php echo esc_html(date_i18n('M j, Y H:i')); ?></span>
            <span>Total orders: <?php echo intval($total_orders_all); ?></span>
            <span>Total revenue: <?php echo unico_format_price($total_revenue_all); ?></span>
        </div>
    </div>

    <?php if (!empty($mgmt_notices)): ?>
        <?php foreach ($mgmt_notices as $notice): ?>
            <div class="mgmt-notice <?php echo $notice['type'] === 'error' ? 'error' : 'success'; ?>">
                <?php echo esc_html($notice['message']); ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="mgmt-tabs">
        <button type="button" class="mgmt-tab default-active" data-tab="overview">Overview</button>
        <button type="button" class="mgmt-tab" data-tab="registrations">Registrations</button>
        <button type="button" class="mgmt-tab" data-tab="vouchers">Vouchers</button>
        <button type="button" class="mgmt-tab" data-tab="orders">Orders</button>
        <button type="button" class="mgmt-tab" data-tab="analytics">Analytics</button>
        <button type="button" class="mgmt-tab" data-tab="users">Users</button>
        <button type="button" class="mgmt-tab" data-tab="bank-accounts">Bank Accounts</button>
        <div class="mgmt-tabs-spacer"></div>
        <span class="mgmt-badge">Logged in as <?php echo esc_html($current_user->display_name); ?></span>
    </div>

    <div class="mgmt-tab-content active" data-tab="overview">
        <div class="mgmt-stats-grid">
            <div class="mgmt-stat-card">
                <div class="mgmt-stat-icon">📅</div>
                <div class="mgmt-stat-label">Today Orders</div>
                <div class="mgmt-stat-value"><?php echo intval($today_orders_count); ?></div>
                <div class="mgmt-stat-sub">Today revenue: <?php echo unico_format_price($today_revenue); ?></div>
            </div>
            <div class="mgmt-stat-card">
                <div class="mgmt-stat-icon">📦</div>
                <div class="mgmt-stat-label">Orders (30 Days)</div>
                <div class="mgmt-stat-value"><?php echo count($orders_30days); ?></div>
                <div class="mgmt-stat-sub">Last 30 days</div>
            </div>
            <div class="mgmt-stat-card">
                <div class="mgmt-stat-icon">💰</div>
                <div class="mgmt-stat-label">Revenue (30 Days)</div>
                <div class="mgmt-stat-value"><?php echo unico_format_price($revenue_30days); ?></div>
                <div class="mgmt-stat-sub">Processing + completed</div>
            </div>
            <div class="mgmt-stat-card">
                <div class="mgmt-stat-icon">👥</div>
                <div class="mgmt-stat-label">Active Users (30 Days)</div>
                <div class="mgmt-stat-value"><?php echo intval($active_users_30days); ?></div>
                <div class="mgmt-stat-sub">Based on activity logs</div>
            </div>
            <div class="mgmt-stat-card">
                <div class="mgmt-stat-icon">🎫</div>
                <div class="mgmt-stat-label">Open Tickets</div>
                <div class="mgmt-stat-value"><?php echo intval($open_tickets); ?></div>
                <div class="mgmt-stat-sub">Total tickets: <?php echo intval($total_tickets); ?></div>
            </div>
            <div class="mgmt-stat-card">
                <div class="mgmt-stat-icon">⚠️</div>
                <div class="mgmt-stat-label">High Risk Users</div>
                <div class="mgmt-stat-value"><?php echo intval($high_risk_users); ?></div>
                <div class="mgmt-stat-sub">Security risk score ≥ 70</div>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Voucher Inventory</span>
                <span>Total: <?php echo intval($voucher_stats['total']); ?> • Available: <?php echo intval($voucher_stats['available']); ?></span>
            </div>
            <div class="mgmt-section-body">
                <div class="mgmt-info-grid">
                    <div class="mgmt-info-item">
                        <h4>Total Vouchers</h4>
                        <div class="mgmt-kpi"><?php echo intval($voucher_stats['total']); ?></div>
                    </div>
                    <div class="mgmt-info-item">
                        <h4>Available</h4>
                        <div class="mgmt-kpi positive"><?php echo intval($voucher_stats['available']); ?></div>
                    </div>
                    <div class="mgmt-info-item">
                        <h4>Assigned</h4>
                        <div class="mgmt-kpi warning"><?php echo intval($voucher_stats['assigned']); ?></div>
                    </div>
                    <div class="mgmt-info-item">
                        <h4>Delivered</h4>
                        <div class="mgmt-kpi"><?php echo intval($voucher_stats['delivered']); ?></div>
                    </div>
                    <div class="mgmt-info-item">
                        <h4>Expired</h4>
                        <div class="mgmt-kpi danger"><?php echo intval($voucher_stats['expired']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>User Distribution</span>
                <span>Customers: <?php echo intval($customer_count); ?> • Agents: <?php echo intval($agent_count); ?> • Resellers: <?php echo intval($reseller_count); ?></span>
            </div>
            <div class="mgmt-section-body">
                <div class="mgmt-info-grid">
                    <div class="mgmt-info-item">
                        <h4>Customers</h4>
                        <div class="mgmt-kpi"><?php echo intval($customer_count); ?></div>
                    </div>
                    <div class="mgmt-info-item">
                        <h4>Agents</h4>
                        <div class="mgmt-kpi"><?php echo intval($agent_count); ?></div>
                    </div>
                    <div class="mgmt-info-item">
                        <h4>Resellers</h4>
                        <div class="mgmt-kpi"><?php echo intval($reseller_count); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mgmt-tab-content" data-tab="registrations">
        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Pending User Approvals</span>
                <span><?php echo $pending_approvals ? count($pending_approvals) : 0; ?> pending</span>
            </div>
            <div class="mgmt-section-body">
                <?php if (!empty($pending_approvals)): ?>
                    <div class="mgmt-scroll">
                        <table class="mgmt-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Requested Role</th>
                                    <th>Requested At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_approvals as $approval): ?>
                                    <?php
                                    $approval_user = get_userdata($approval->user_id);
                                    $requested_role_name = class_exists('Unico_User_Roles') && method_exists('Unico_User_Roles', 'get_role_display_name')
                                        ? Unico_User_Roles::get_role_display_name($approval->requested_role)
                                        : $approval->requested_role;
                                    ?>
                                    <tr>
                                        <td>#<?php echo intval($approval->id); ?></td>
                                        <td>
                                            <?php if ($approval_user): ?>
                                                <?php echo esc_html($approval_user->display_name); ?>
                                                <div class="mgmt-muted-text"><?php echo esc_html($approval_user->user_email); ?></div>
                                            <?php else: ?>
                                                <span class="mgmt-muted-text">User deleted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="mgmt-status-pill pending"><?php echo esc_html($requested_role_name); ?></span>
                                        </td>
                                        <td><?php echo esc_html(date_i18n('M j, Y H:i', strtotime($approval->created_at))); ?></td>
                                        <td>
                                            <?php if ($approval_user): ?>
                                                <form method="post" class="mgmt-form-row" style="align-items:center;gap:6px;">
                                                    <input type="hidden" name="approval_id" value="<?php echo intval($approval->id); ?>">
                                                    <input type="text" name="approval_remarks" placeholder="Remarks (optional)">
                                                    <?php wp_nonce_field('unico_user_approval_action', 'user_approval_nonce'); ?>
                                                    <button type="submit" name="unico_user_approval_action" value="approve" class="mgmt-btn mgmt-btn-primary">Approve</button>
                                                    <button type="submit" name="unico_user_approval_action" value="reject" class="mgmt-btn mgmt-btn-secondary">Reject</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="mgmt-muted-text">No action possible</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mgmt-muted-text">No pending user approvals.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Recent Applications</span>
                <span><?php echo $recent_applications ? count($recent_applications) : 0; ?> latest</span>
            </div>
            <div class="mgmt-section-body">
                <?php if (!empty($recent_applications)): ?>
                    <div class="mgmt-scroll">
                        <table class="mgmt-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Application</th>
                                    <th>Submitted</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_applications as $application): ?>
                                    <?php
                                    $data = json_decode($application->form_data, true) ?: [];
                                    $applicant_name = isset($data['full_name']) ? $data['full_name'] : '';
                                    $applicant_email = isset($data['email']) ? $data['email'] : '';
                                    $application_type = isset($data['application_type']) ? $data['application_type'] : 'student';
                                    $application_type_label = $application_type === 'agent' ? 'Agent' : 'Student';
                                    $status_class = str_replace('-', '_', $application->status);
                                    ?>
                                    <tr>
                                        <td>#<?php echo esc_html($application->submission_number); ?></td>
                                        <td>
                                            <?php echo esc_html($applicant_name ?: 'Unknown'); ?>
                                            <?php if ($applicant_email): ?>
                                                <div class="mgmt-muted-text"><?php echo esc_html($applicant_email); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(date_i18n('M j, Y H:i', strtotime($application->created_at))); ?></td>
                                        <td>
                                            <span class="mgmt-status-pill <?php echo $application_type === 'agent' ? 'agent' : 'student'; ?>">
                                                <?php echo esc_html($application_type_label); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="mgmt-status-pill <?php echo esc_attr($status_class); ?>">
                                                <?php echo esc_html(ucwords(str_replace('_', ' ', $application->status))); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($application->status === 'pending' || $application->status === 'submitted' || $application->status === 'in_review'): ?>
                                                <form method="post" class="mgmt-form-row" style="align-items:center;gap:6px; flex-wrap: wrap;">
                                                    <button type="button" class="mgmt-btn mgmt-btn-secondary view-application-btn"
                                                        data-application='<?php echo esc_attr($application->form_data); ?>'
                                                        data-id="<?php echo esc_attr($application->submission_number); ?>"
                                                        data-type="<?php echo esc_attr($application_type_label); ?>">
                                                        View
                                                    </button>

                                                    <input type="text" name="status_notes" placeholder="Notes (for Reject)" style="padding: 5px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; width: 140px;">

                                                    <input type="hidden" name="application_id" value="<?php echo intval($application->id); ?>">
                                                    <input type="hidden" name="update_application_status" value="1">
                                                    <?php wp_nonce_field('update_application_status', 'application_status_nonce'); ?>

                                                    <button type="submit" name="new_status" value="approved" class="mgmt-btn mgmt-btn-primary" style="background-color: #28a745; border-color: #28a745;" onclick="return confirm('Approve this application? This will create a user account and send login details.')">Approve</button>
                                                    <button type="submit" name="new_status" value="rejected" class="mgmt-btn mgmt-btn-danger" style="background-color: #dc3545; border-color: #dc3545; color: white;" onclick="if(!this.form.status_notes.value.trim()){alert('Please provide a reason for rejection in the notes field.'); return false;} return confirm('Reject this application? This will delete the application data.');">Reject</button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="mgmt-btn mgmt-btn-secondary view-application-btn" 
                                                    data-application='<?php echo esc_attr($application->form_data); ?>'
                                                    data-id="<?php echo esc_attr($application->submission_number); ?>"
                                                    data-type="<?php echo esc_attr($application_type_label); ?>">
                                                    View Details
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mgmt-muted-text">No applications found yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Email Notification Preferences</span>
                <span>Manage which notifications you receive</span>
            </div>
            <div class="mgmt-section-body">
                <form method="post" style="max-width: 600px;">
                    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                            <input
                                type="checkbox"
                                name="receive_new_application_notifications"
                                value="1"
                                <?php echo $application_form->is_notification_recipient($user_id, 'new_application') ? 'checked' : ''; ?>
                                style="width: 20px; height: 20px; cursor: pointer;"
                            >
                            <div>
                                <strong style="display: block; margin-bottom: 4px;">New Application Notifications</strong>
                                <span style="color: #666; font-size: 14px;">
                                    Receive an email when a new student or agent application is submitted
                                </span>
                            </div>
                        </label>
                    </div>

                    <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #194f68; border-radius: 4px; margin-bottom: 20px;">
                        <p style="margin: 0; font-size: 14px;">
                            <strong>Note:</strong> If no management users are subscribed to notifications,
                            all administrators and management users will automatically receive new application emails.
                        </p>
                    </div>

                    <?php
                    // Show list of all users receiving notifications
                    $recipients = $application_form->get_notification_recipients('new_application');
                    if (!empty($recipients)):
                    ?>
                        <div style="margin-top: 20px;">
                            <h4 style="margin-bottom: 10px; color: #194f68;">Currently Receiving Notifications:</h4>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach ($recipients as $recipient): ?>
                                    <?php $recipient_user = get_userdata($recipient->user_id); ?>
                                    <?php if ($recipient_user): ?>
                                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                                            <span style="font-weight: 500;"><?php echo esc_html($recipient_user->display_name); ?></span>
                                            <span style="color: #666; margin-left: 8px;">(<?php echo esc_html($recipient_user->user_email); ?>)</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="mgmt-form-actions">
                        <?php wp_nonce_field('update_notification_prefs', 'notification_prefs_nonce'); ?>
                        <button type="submit" name="update_notification_prefs" class="mgmt-btn mgmt-btn-primary">
                            Save Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="mgmt-tab-content" data-tab="vouchers">
        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Exam Catalog Management</span>
                <span>Add new exam types without opening WooCommerce</span>
            </div>
            <div class="mgmt-section-body">
                <form method="post">
                    <div class="mgmt-form-row">
                        <label>
                            Exam product name
                            <input type="text" name="exam_product_name" required placeholder="e.g. XYZ English Test">
                        </label>
                        <label>
                            Exam family (optional)
                            <input type="text" name="exam_family" placeholder="e.g. IELTS, PTE">
                        </label>
                    </div>
                    <div class="mgmt-form-row">
                        <label>
                            Price (optional)
                            <input type="number" name="exam_price" step="0.01" min="0">
                        </label>
                        <label>
                            Currency (optional)
                            <input type="text" name="exam_currency" placeholder="e.g. USD, GBP">
                        </label>
                        <label>
                            Price nature (optional)
                            <select name="exam_price_nature">
                                <option value="">Select</option>
                                <option value="Country Wise">Country Wise</option>
                                <option value="Global">Global</option>
                            </select>
                        </label>
                    </div>
                    <div class="mgmt-form-actions">
                        <?php wp_nonce_field('unico_add_exam_definition', 'exam_definition_nonce'); ?>
                        <button type="submit" name="unico_add_exam_definition" class="mgmt-btn mgmt-btn-primary">Add exam</button>
                    </div>
                    <p class="mgmt-small-note">New exams appear automatically in voucher pricing and inventory exam dropdowns.</p>
                </form>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Add Vouchers To Inventory</span>
                <span>Creates encrypted voucher records for delivery</span>
            </div>
            <div class="mgmt-section-body">
                <form method="post">
                    <div class="mgmt-form-row">
                        <label>
                            Exam name
                            <select name="voucher_exam_name">
                                <option value="">Select exam</option>
                                <?php
                                if (function_exists('unico_get_voucher_exam_options')) {
                                    $exam_options = unico_get_voucher_exam_options();
                                    foreach ($exam_options as $option) {
                                        $value = isset($option['value']) ? $option['value'] : '';
                                        $label = isset($option['label']) ? $option['label'] : $value;
                                        if (!$value) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                        </label>
                        <label>
                            Voucher code (leave blank to auto-generate)
                            <input type="text" name="voucher_code">
                        </label>
                    </div>
                    <div class="mgmt-form-row">
                        <label>
                            Purchase price
                            <input type="number" name="voucher_purchase_price" step="0.01" min="0">
                        </label>
                        <label>
                            Selling price
                            <input type="number" name="voucher_selling_price" step="0.01" min="0">
                        </label>
                        <label>
                            Expiry date
                            <input type="date" name="voucher_expiry_date">
                        </label>
                        <label>
                            Auto-generate quantity
                            <input type="number" name="auto_generate_count" min="0" step="1" placeholder="0">
                        </label>
                    </div>
                    <div class="mgmt-form-actions">
                        <?php wp_nonce_field('unico_add_voucher', 'voucher_inventory_nonce'); ?>
                        <button type="submit" name="unico_add_voucher" class="mgmt-btn mgmt-btn-primary">Add voucher</button>
                    </div>
                    <div class="mgmt-small-note">Voucher codes are stored encrypted and will be auto-assigned on order completion.</div>
                </form>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Voucher Pricing Management</span>
                <span>Updates WooCommerce voucher product prices</span>
            </div>
            <div class="mgmt-section-body">
                <?php
                $voucher_products_admin = new WP_Query([
                    'post_type'      => 'product',
                    'posts_per_page' => -1,
                    'tax_query'      => [
                        [
                            'taxonomy' => 'product_cat',
                            'field'    => 'slug',
                            'terms'    => 'vouchers',
                        ],
                    ],
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);
                ?>
                <?php if ($voucher_products_admin->have_posts()): ?>
                    <div class="mgmt-scroll">
                        <form method="post">
                            <table class="mgmt-table">
                                <thead>
                                    <tr>
                                        <th>Voucher</th>
                                        <th>Available Stock</th>
                                        <th>Official Price</th>
                                        <th>Agent Price</th>
                                        <th>Update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $voucher_system_local = Unico_Voucher_System::get_instance();
                                    while ($voucher_products_admin->have_posts()): $voucher_products_admin->the_post();
                                        $product_id = get_the_ID();
                                        $official_meta = get_post_meta($product_id, '_voucher_official_price', true);
                                        if ($official_meta === '') {
                                            $official_meta = get_post_meta($product_id, '_regular_price', true);
                                        }
                                        $agent_meta = get_post_meta($product_id, '_voucher_agent_price', true);
                                        
                                        $exam_label = get_post_meta($product_id, 'exam_name', true);
                                        if (!$exam_label) {
                                            $exam_label = get_the_title();
                                        }
                                        
                                        $available_stock = 0;
                                        if ($exam_label && method_exists($voucher_system_local, 'get_vouchers_by_exam')) {
                                            $available = $voucher_system_local->get_vouchers_by_exam($exam_label, 'available');
                                            $available_stock = is_array($available) ? count($available) : 0;
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <?php echo esc_html(get_the_title()); ?>
                                                <div class="mgmt-muted-text">ID: <?php echo intval($product_id); ?></div>
                                                <input type="hidden" name="voucher_product_id[]" value="<?php echo intval($product_id); ?>">
                                            </td>
                                            <td>
                                                <div><?php echo intval($available_stock); ?></div>
                                                <div style="margin-top:6px; display:flex; gap:6px; align-items:center;">
                                                    <input type="number" name="stock_add_<?php echo intval($product_id); ?>" min="0" step="1" placeholder="+ Qty" style="width:80px;">
                                                    <button type="submit" name="unico_add_product_stock" value="<?php echo intval($product_id); ?>" class="mgmt-btn mgmt-btn-secondary">Add</button>
                                                </div>
                                                <div style="margin-top:4px; display:flex; gap:6px; align-items:center;">
                                                    <input type="number" name="stock_remove_<?php echo intval($product_id); ?>" min="0" step="1" placeholder="- Qty" style="width:80px;">
                                                    <button type="submit" name="unico_remove_product_stock" value="<?php echo intval($product_id); ?>" class="mgmt-btn mgmt-btn-secondary">Remove</button>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" name="official_price_<?php echo intval($product_id); ?>" value="<?php echo esc_attr($official_meta); ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" name="agent_price_<?php echo intval($product_id); ?>" value="<?php echo esc_attr($agent_meta); ?>">
                                            </td>
                                            <td>
                                                <button type="submit" name="unico_update_voucher_pricing" value="<?php echo intval($product_id); ?>" class="mgmt-btn mgmt-btn-primary">Update</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; wp_reset_postdata(); ?>
                                </tbody>
                            </table>
                            <?php wp_nonce_field('unico_update_voucher_pricing', 'voucher_pricing_nonce'); ?>
                            <?php wp_nonce_field('unico_adjust_voucher_stock', 'voucher_stock_nonce'); ?>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="mgmt-muted-text">No voucher products found.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Recent Voucher Inventory</span>
                <span>Last <?php echo $recent_vouchers ? count($recent_vouchers) : 0; ?> records</span>
            </div>
            <div class="mgmt-section-body">
                <?php if (!empty($recent_vouchers)): ?>
                    <div class="mgmt-scroll">
                        <table class="mgmt-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Exam</th>
                                    <th>Status</th>
                                    <th>Purchase</th>
                                    <th>Selling</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_vouchers as $voucher): ?>
                                    <tr>
                                        <td>#<?php echo intval($voucher->id); ?></td>
                                        <td><?php echo esc_html($voucher->exam_name); ?></td>
                                        <td>
                                            <span class="mgmt-status-pill <?php echo $voucher->voucher_status === 'available' ? 'approved' : ($voucher->voucher_status === 'assigned' ? 'pending' : 'completed'); ?>">
                                                <?php echo esc_html(ucfirst($voucher->voucher_status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo unico_format_price($voucher->purchase_price); ?></td>
                                        <td><?php echo unico_format_price($voucher->selling_price); ?></td>
                                        <td><?php echo esc_html(date_i18n('M j, Y H:i', strtotime($voucher->created_at))); ?></td>
                                        <td>
                                            <?php if ($voucher->voucher_status === 'available'): ?>
                                                <form method="post" onsubmit="return confirm('Delete this voucher code?');">
                                                    <input type="hidden" name="voucher_id" value="<?php echo intval($voucher->id); ?>">
                                                    <?php wp_nonce_field('unico_delete_voucher', 'voucher_delete_nonce'); ?>
                                                    <button type="submit" name="unico_delete_voucher" class="mgmt-btn mgmt-btn-secondary">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="mgmt-muted-text">Locked</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mgmt-muted-text">No voucher inventory records found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mgmt-tab-content" data-tab="orders">
        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Recent Orders</span>
                <span>Last <?php echo $recent_orders ? count($recent_orders) : 0; ?> orders</span>
            </div>
            <div class="mgmt-section-body">
                <?php if (!empty($recent_orders)): ?>
                    <div class="mgmt-scroll">
                        <table class="mgmt-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Transaction ID</th>
                                    <th>Receipt</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <?php
                                    $order_id = $order->get_id();
                                    $wc_order = wc_get_order($order_id);
                                    $billing_name = $order->get_customer_name();
                                    $billing_email = $order->get_customer_email();
                                    $status_key = $order->get_status();
                                    $transaction_id = get_post_meta($order_id, '_unico_txn_id', true);
                                    if (!$transaction_id) {
                                        $transaction_id = $order->get_payment_reference();
                                    }
                                    $payment_method = $order->get_payment_method();
                                    $payment_method_title = $wc_order ? $wc_order->get_payment_method_title() : ucfirst($payment_method);
                                    if (!$payment_method_title) {
                                        $payment_method_title = ucfirst($payment_method);
                                    }
                                    $verification_status = get_post_meta($order_id, '_voucher_verification_status', true);
                                    $existing_codes = get_post_meta($order_id, '_unico_voucher_codes', true);
                                    $is_bank_transfer = ($payment_method === 'unico_bank_transfer_verify');
                                    $can_approve = !in_array($status_key, ['completed', 'cancelled', 'refunded', 'failed'], true) && empty($existing_codes);
                                    $receipt_id = (int) get_post_meta($order_id, '_unico_receipt_attachment_id', true);
                                    $receipt_url = get_post_meta($order_id, '_unico_receipt_url', true);
                                    if (!$receipt_url && $receipt_id) {
                                        $receipt_url = wp_get_attachment_url($receipt_id);
                                    }
                                    $bank_snapshot = get_post_meta($order_id, '_unico_selected_bank_snapshot', true);
                                    $bank_label = is_array($bank_snapshot) ? ($bank_snapshot['display_name'] ?? ($bank_snapshot['bank_name'] ?? '')) : '';
                                    $order_date = $wc_order && $wc_order->get_date_created() ? $wc_order->get_date_created()->date_i18n('M j, Y H:i') : ($order->get_date_created() ? date_i18n('M j, Y H:i', strtotime($order->get_date_created())) : '-');
                                    $formatted_total = $wc_order ? $wc_order->get_formatted_order_total() : unico_format_price($order->get_total());
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html($order->get_order_number()); ?></strong></td>
                                        <td>
                                            <span class="mgmt-status-pill <?php echo $status_key === 'completed' ? 'approved' : ($status_key === 'cancelled' || $status_key === 'failed' ? 'rejected' : 'pending'); ?>">
                                                <?php echo esc_html(function_exists('wc_get_order_status_name') ? wc_get_order_status_name($status_key) : ucfirst($status_key)); ?>
                                            </span>
                                            <?php if ($verification_status): ?>
                                                <div class="mgmt-small-note" style="margin-top:4px;">Verified: <?php echo esc_html(ucfirst($verification_status)); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html($billing_name ?: 'Guest'); ?>
                                            <?php if ($billing_email): ?>
                                                <div class="mgmt-muted-text"><?php echo esc_html($billing_email); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo wp_kses_post($formatted_total); ?></td>
                                        <td><?php echo esc_html($transaction_id ?: '-'); ?></td>
                                        <td>
                                            <?php if ($receipt_url): ?>
                                                <a href="<?php echo esc_url($receipt_url); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php if ($receipt_id): ?>
                                                        <?php
                                                        $mime = get_post_mime_type($receipt_id);
                                                        if ($mime && strpos($mime, 'image/') === 0): ?>
                                                            <?php echo wp_get_attachment_image($receipt_id, [48, 48], true, ['class' => 'mgmt-payment-thumb', 'style' => 'border-radius:4px;']); ?>
                                                        <?php else: ?>
                                                            <span class="mgmt-btn mgmt-btn-secondary" style="font-size:11px;padding:2px 6px;">View</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="mgmt-btn mgmt-btn-secondary" style="font-size:11px;padding:2px 6px;">View</span>
                                                    <?php endif; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="mgmt-muted-text">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html($payment_method_title); ?>
                                            <?php if ($bank_label): ?>
                                                <div class="mgmt-small-note"><?php echo esc_html($bank_label); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($order_date); ?></td>
                                        <td>
                                            <?php if ($can_approve): ?>
                                                <!-- Approve/Reject buttons matching wp-admin verification -->
                                                <div style="display:flex;gap:6px;margin-bottom:8px;">
                                                    <form method="post" onsubmit="return confirm('Approve this payment? This will generate voucher codes and send email to customer.');">
                                                        <input type="hidden" name="order_id" value="<?php echo intval($order_id); ?>">
                                                        <?php wp_nonce_field('unico_update_order_status', 'order_management_nonce'); ?>
                                                        <button type="submit" name="unico_approve_payment" class="mgmt-btn" style="background-color:#28a745;border-color:#28a745;color:#fff;">Approve</button>
                                                    </form>
                                                    <button type="button" class="mgmt-btn" style="background-color:#dc3545;border-color:#dc3545;color:#fff;" onclick="document.getElementById('reject-form-<?php echo intval($order_id); ?>').style.display='block';this.style.display='none';">Reject</button>
                                                </div>
                                                <form method="post" id="reject-form-<?php echo intval($order_id); ?>" style="display:none;margin-bottom:8px;">
                                                    <input type="hidden" name="order_id" value="<?php echo intval($order_id); ?>">
                                                    <?php wp_nonce_field('unico_update_order_status', 'order_management_nonce'); ?>
                                                    <textarea name="reject_reason" placeholder="Rejection reason (required)" rows="2" style="width:100%;margin-bottom:4px;font-size:12px;" required></textarea>
                                                    <button type="submit" name="unico_reject_payment" class="mgmt-btn" style="background-color:#dc3545;border-color:#dc3545;color:#fff;font-size:11px;">Confirm Reject</button>
                                                    <button type="button" class="mgmt-btn mgmt-btn-secondary" style="font-size:11px;" onclick="this.parentElement.style.display='none';this.parentElement.previousElementSibling.querySelector('button[type=button]').style.display='inline-block';">Cancel</button>
                                                </form>
                                            <?php else: ?>
                                                <?php if (!empty($existing_codes)): ?>
                                                    <span class="mgmt-small-note" style="color:#28a745;">Codes generated</span>
                                                <?php elseif ($status_key === 'completed'): ?>
                                                    <span class="mgmt-small-note" style="color:#28a745;">Completed</span>
                                                <?php elseif ($status_key === 'cancelled'): ?>
                                                    <span class="mgmt-small-note" style="color:#dc3545;">Cancelled</span>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- Status update form -->
                                            <details style="margin-top:8px;">
                                                <summary style="cursor:pointer;font-size:12px;color:#666;">More options</summary>
                                                <div style="padding-top:8px;">
                                                    <form method="post" class="mgmt-form-row" style="align-items:center;gap:6px;flex-wrap:wrap;">
                                                        <select name="new_status" style="font-size:12px;">
                                                            <?php
                                                            $statuses = [
                                                                'pending' => 'Pending',
                                                                'processing' => 'Processing',
                                                                'completed' => 'Completed',
                                                                'cancelled' => 'Cancelled',
                                                                'refunded' => 'Refunded',
                                                                'failed' => 'Failed',
                                                                'on-hold' => 'On Hold'
                                                            ];
                                                            foreach ($statuses as $status_slug => $status_label): ?>
                                                                <option value="<?php echo esc_attr($status_slug); ?>" <?php selected($status_slug, $status_key); ?>>
                                                                    <?php echo esc_html($status_label); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input type="hidden" name="order_id" value="<?php echo intval($order_id); ?>">
                                                        <input type="text" name="order_note" placeholder="Note" style="font-size:12px;width:100px;">
                                                        <?php wp_nonce_field('unico_update_order_status', 'order_management_nonce'); ?>
                                                        <button type="submit" name="unico_update_order_status" class="mgmt-btn mgmt-btn-secondary" style="font-size:11px;">Update</button>
                                                    </form>
                                                    <form method="post" style="margin-top:6px;">
                                                        <input type="hidden" name="order_id" value="<?php echo intval($order_id); ?>">
                                                        <?php wp_nonce_field('unico_update_order_status', 'order_management_nonce'); ?>
                                                        <button type="submit" name="unico_request_payment_proof" class="mgmt-btn mgmt-btn-secondary" style="font-size:11px;">Request receipt</button>
                                                    </form>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mgmt-small-note">Click "Approve" to verify payment, generate voucher codes and send email. Click "Reject" to cancel with reason.</p>
                <?php else: ?>
                    <p class="mgmt-muted-text">No recent orders found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mgmt-tab-content" data-tab="analytics">
        <div class="mgmt-two-column">
            <div>
                <div class="mgmt-section-card">
                    <div class="mgmt-section-header">
                        <span>Orders Last 7 Days</span>
                        <span>Daily order count</span>
                    </div>
                    <div class="mgmt-section-body">
                        <div class="mgmt-chart-wrapper">
                            <canvas id="mgmt-orders-chart" class="mgmt-chart-canvas" width="600" height="260"></canvas>
                        </div>
                        <p class="mgmt-small-note">Simple bar chart showing number of orders per day.</p>
                    </div>
                </div>
            </div>
            <div>
                <div class="mgmt-section-card">
                    <div class="mgmt-section-header">
                        <span>Key Totals</span>
                        <span>Lifetime metrics</span>
                    </div>
                    <div class="mgmt-section-body">
                        <div class="mgmt-info-grid">
                            <div class="mgmt-info-item">
                                <h4>Total Orders</h4>
                                <div class="mgmt-kpi"><?php echo intval($total_orders_all); ?></div>
                            </div>
                            <div class="mgmt-info-item">
                                <h4>Total Revenue</h4>
                                <div class="mgmt-kpi"><?php echo unico_format_price($total_revenue_all); ?></div>
                            </div>
                            <div class="mgmt-info-item">
                                <h4>30 Day Average Orders</h4>
                                <div class="mgmt-kpi muted">
                                    <?php
                                    $avg_orders = $orders_30days ? round(count($orders_30days) / 30, 2) : 0;
                                    echo esc_html($avg_orders);
                                    ?>
                                </div>
                            </div>
                            <div class="mgmt-info-item">
                                <h4>30 Day Average Revenue</h4>
                                <div class="mgmt-kpi muted">
                                    <?php
                                    $avg_revenue = $revenue_30days ? round($revenue_30days / 30, 2) : 0;
                                    echo unico_format_price($avg_revenue);
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mgmt-section-card">
                    <div class="mgmt-section-header">
                        <span>User Snapshot</span>
                        <span>Core user metrics</span>
                    </div>
                    <div class="mgmt-section-body">
                        <div class="mgmt-info-grid">
                            <div class="mgmt-info-item">
                                <h4>Total Customers</h4>
                                <div class="mgmt-kpi"><?php echo intval($customer_count); ?></div>
                            </div>
                            <div class="mgmt-info-item">
                                <h4>Total Agents</h4>
                                <div class="mgmt-kpi"><?php echo intval($agent_count); ?></div>
                            </div>
                            <div class="mgmt-info-item">
                                <h4>Total Resellers</h4>
                                <div class="mgmt-kpi"><?php echo intval($reseller_count); ?></div>
                            </div>
                            <div class="mgmt-info-item">
                                <h4>Active Users (30 Days)</h4>
                                <div class="mgmt-kpi"><?php echo intval($active_users_30days); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            window.unicoManagementChartData = <?php echo wp_json_encode([
                'labels' => $chart_labels,
                'values' => $chart_values,
            ]); ?>;
        </script>
    </div>

    <div class="mgmt-tab-content" data-tab="users">
        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Manage Users</span>
                <span>Create, block, delete, and reset passwords</span>
            </div>
            <div class="mgmt-section-body">
                <h3 style="font-size:15px;margin-bottom:10px;">Add New User</h3>
                <form method="post">
                    <div class="mgmt-form-row">
                        <label>
                            Full name
                            <input type="text" name="new_user_full_name" required>
                        </label>
                        <label>
                            Email
                            <input type="email" name="new_user_email" required>
                        </label>
                        <label>
                            Role
                            <select name="new_user_role">
                                <?php
                                $roles = class_exists('Unico_User_Roles') && method_exists('Unico_User_Roles', 'get_custom_roles')
                                    ? Unico_User_Roles::get_custom_roles()
                                    : [];
                                foreach ($roles as $role_key => $role_label) {
                                    ?>
                                    <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_label); ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </label>
                    </div>
                    <div class="mgmt-form-row">
                        <label>
                            Phone
                            <input type="text" name="new_user_phone">
                        </label>
                    </div>
                    <div class="mgmt-form-actions">
                        <?php wp_nonce_field('unico_user_management', 'user_management_nonce'); ?>
                        <button type="submit" name="user_management_action" value="add_user" class="mgmt-btn mgmt-btn-primary">Create user</button>
                    </div>
                    <div class="mgmt-small-note">New users receive a standard WordPress password reset email via the login page.</div>
                </form>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Recent Users</span>
                <span>Last <?php echo $managed_users ? count($managed_users) : 0; ?> registered</span>
            </div>
            <div class="mgmt-section-body">
                <?php if (!empty($managed_users)): ?>
                    <div class="mgmt-scroll">
                        <table class="mgmt-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($managed_users as $managed_user): ?>
                                    <?php
                                    $roles = $managed_user->roles;
                                    $primary_role = !empty($roles) ? $roles[0] : '';
                                    $role_label = class_exists('Unico_User_Roles') && method_exists('Unico_User_Roles', 'get_role_display_name')
                                        ? Unico_User_Roles::get_role_display_name($primary_role)
                                        : $primary_role;
                                    $is_banned = get_user_meta($managed_user->ID, 'unico_banned', true);
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($managed_user->display_name ?: $managed_user->user_login); ?>
                                            <div class="mgmt-muted-text"><?php echo esc_html($managed_user->user_email); ?></div>
                                        </td>
                                        <td>
                                            <span class="mgmt-user-role"><?php echo esc_html($role_label ?: 'N/A'); ?></span>
                                        </td>
                                        <td><?php echo esc_html(date_i18n('M j, Y H:i', strtotime($managed_user->user_registered))); ?></td>
                                        <td>
                                            <?php if ($is_banned): ?>
                                                <span class="mgmt-status-pill mgmt-banned-pill">Blocked</span>
                                            <?php else: ?>
                                                <span class="mgmt-status-pill approved">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="mgmt-user-actions">
                                                <form method="post">
                                                    <input type="hidden" name="target_user_id" value="<?php echo intval($managed_user->ID); ?>">
                                                    <?php wp_nonce_field('unico_user_management', 'user_management_nonce'); ?>
                                                    <?php if ($is_banned): ?>
                                                        <button type="submit" name="user_management_action" value="unban" class="mgmt-btn mgmt-btn-secondary">Unblock</button>
                                                    <?php else: ?>
                                                        <button type="submit" name="user_management_action" value="ban" class="mgmt-btn mgmt-btn-outline">Block</button>
                                                    <?php endif; ?>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                                    <input type="hidden" name="target_user_id" value="<?php echo intval($managed_user->ID); ?>">
                                                    <?php wp_nonce_field('unico_user_management', 'user_management_nonce'); ?>
                                                    <button type="submit" name="user_management_action" value="delete" class="mgmt-btn mgmt-btn-danger">Delete</button>
                                                </form>
                                                <form method="post">
                                                    <input type="hidden" name="target_user_id" value="<?php echo intval($managed_user->ID); ?>">
                                                    <?php wp_nonce_field('unico_user_management', 'user_management_nonce'); ?>
                                                    <button type="submit" name="user_management_action" value="reset_password" class="mgmt-btn mgmt-btn-secondary">Reset password</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mgmt-muted-text">No users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bank Accounts Tab -->
    <?php
    $bank_system = Unico_Bank_Accounts::get_instance();

    // Handle payment settings form
    if (isset($_POST['payment_settings_action']) && wp_verify_nonce($_POST['payment_settings_nonce'], 'unico_payment_settings')) {
        $enable_card = isset($_POST['enable_card_payment']) ? '1' : '0';
        update_option('unico_enable_card_payment', $enable_card);
        $mgmt_notices[] = [
            'type' => 'success',
            'message' => 'Payment settings updated successfully.'
        ];
    }

    // Handle form submissions
    if (isset($_POST['bank_action']) && wp_verify_nonce($_POST['bank_nonce'], 'unico_bank_management')) {
        $action = sanitize_text_field($_POST['bank_action']);

        if ($action === 'add_bank') {
            $result = $bank_system->add_bank($_POST);
            $mgmt_notices[] = [
                'type' => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            ];
        } elseif ($action === 'update_bank' && isset($_POST['bank_id'])) {
            $result = $bank_system->update_bank($_POST['bank_id'], $_POST);
            $mgmt_notices[] = [
                'type' => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            ];
        } elseif ($action === 'delete_bank' && isset($_POST['bank_id'])) {
            $result = $bank_system->delete_bank($_POST['bank_id']);
            $mgmt_notices[] = [
                'type' => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            ];
        } elseif ($action === 'toggle_active' && isset($_POST['bank_id'])) {
            $result = $bank_system->toggle_active($_POST['bank_id']);
            $mgmt_notices[] = [
                'type' => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            ];
        }
    }

    // Get current payment settings
    $card_payment_enabled = get_option('unico_enable_card_payment', '1'); // Default enabled

    $all_banks = $bank_system->get_all_banks();
    $bank_stats = $bank_system->get_bank_stats();
    ?>
    <div class="mgmt-tab-content" data-tab="bank-accounts">
        <!-- Payment Settings Section -->
        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Payment Method Settings</span>
                <span>Configure available payment options</span>
            </div>
            <div class="mgmt-section-body">
                <form method="POST" style="max-width: 600px;">
                    <?php wp_nonce_field('unico_payment_settings', 'payment_settings_nonce'); ?>
                    <input type="hidden" name="payment_settings_action" value="update">

                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 2px solid #e9ecef;">
                            <div>
                                <div style="font-weight: 600; color: #1a1a1a; margin-bottom: 5px;">
                                    💳 Card Payment
                                </div>
                                <div style="font-size: 13px; color: #6c757d;">
                                    Enable or disable card payment option at checkout
                                </div>
                            </div>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox"
                                       name="enable_card_payment"
                                       value="1"
                                       <?php checked($card_payment_enabled, '1'); ?>
                                       style="width: 20px; height: 20px; cursor: pointer;">
                                <span style="margin-left: 10px; font-weight: 600; color: #28a745;">
                                    <?php echo $card_payment_enabled === '1' ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </label>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 2px solid #e9ecef;">
                            <div>
                                <div style="font-weight: 600; color: #1a1a1a; margin-bottom: 5px;">
                                    🏦 Bank Transfer
                                </div>
                                <div style="font-size: 13px; color: #6c757d;">
                                    Bank transfer is always enabled (managed below)
                                </div>
                            </div>
                            <span style="padding: 6px 12px; background: #28a745; color: white; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                Always Active
                            </span>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="mgmt-button" style="background: #007bff;">
                            Save Payment Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Bank Accounts Overview</span>
                <span><?php echo $bank_stats['total']; ?> total accounts (<?php echo $bank_stats['active']; ?> active)</span>
            </div>
            <div class="mgmt-section-body">
                <div class="mgmt-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="mgmt-stat-card">
                        <div class="mgmt-stat-icon">🏦</div>
                        <div class="mgmt-stat-label">Total Banks</div>
                        <div class="mgmt-stat-value"><?php echo $bank_stats['total']; ?></div>
                    </div>
                    <div class="mgmt-stat-card">
                        <div class="mgmt-stat-icon">✅</div>
                        <div class="mgmt-stat-label">Active Banks</div>
                        <div class="mgmt-stat-value"><?php echo $bank_stats['active']; ?></div>
                    </div>
                    <div class="mgmt-stat-card">
                        <div class="mgmt-stat-icon">⏸️</div>
                        <div class="mgmt-stat-label">Inactive Banks</div>
                        <div class="mgmt-stat-value"><?php echo $bank_stats['inactive']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>Add New Bank Account</span>
                <span>Bank details will be randomly displayed during checkout</span>
            </div>
            <div class="mgmt-section-body">
                <form method="post">
                    <div class="mgmt-form-row">
                        <label>
                            Bank Name *
                            <input type="text" name="bank_name" required placeholder="e.g. State Bank of India">
                        </label>
                        <label>
                            Account Holder Name *
                            <input type="text" name="account_holder" required placeholder="e.g. UNICOU EDUCATION PVT LTD">
                        </label>
                    </div>
                    <div class="mgmt-form-row">
                        <label>
                            Account Number *
                            <input type="text" name="account_number" required placeholder="e.g. 1234567890">
                        </label>
                        <label>
                            IFSC Code
                            <input type="text" name="ifsc_code" placeholder="e.g. SBIN0001234">
                        </label>
                    </div>
                    <div class="mgmt-form-row">
                        <label>
                            SWIFT Code (for international)
                            <input type="text" name="swift_code" placeholder="e.g. SBININBB123">
                        </label>
                        <label>
                            Branch Name
                            <input type="text" name="branch_name" placeholder="e.g. Mumbai Main Branch">
                        </label>
                    </div>
                    <div class="mgmt-form-row">
                        <label>
                            Country
                            <input type="text" name="country" value="India" placeholder="e.g. India">
                        </label>
                        <label>
                            Currency
                            <input type="text" name="currency" value="INR" placeholder="e.g. INR, USD">
                        </label>
                    </div>
                    <div class="mgmt-form-row">
                        <label>
                            Bank Logo URL (optional)
                            <input type="url" name="bank_logo_url" placeholder="https://example.com/logo.png">
                        </label>
                        <label>
                            Display Order (lower = higher priority)
                            <input type="number" name="display_order" value="0" min="0">
                        </label>
                    </div>
                    <div class="mgmt-form-row">
                        <label style="grid-column: 1 / -1;">
                            Internal Notes (not shown to customers)
                            <textarea name="notes" rows="2" placeholder="e.g. Primary account for high-value transactions"></textarea>
                        </label>
                    </div>
                    <div class="mgmt-form-row">
                        <label>
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active (show in checkout)</span>
                        </label>
                    </div>
                    <div class="mgmt-form-actions">
                        <?php wp_nonce_field('unico_bank_management', 'bank_nonce'); ?>
                        <button type="submit" name="bank_action" value="add_bank" class="mgmt-btn mgmt-btn-primary">Add Bank Account</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mgmt-section-card">
            <div class="mgmt-section-header">
                <span>All Bank Accounts</span>
                <span><?php echo count($all_banks); ?> accounts configured</span>
            </div>
            <div class="mgmt-section-body">
                <?php if (!empty($all_banks)): ?>
                    <div class="mgmt-scroll">
                        <table class="mgmt-table">
                            <thead>
                                <tr>
                                    <th>Bank Name</th>
                                    <th>Account Holder</th>
                                    <th>Account Number</th>
                                    <th>IFSC/SWIFT</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_banks as $bank): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($bank->bank_name); ?></strong></td>
                                        <td><?php echo esc_html($bank->account_holder); ?></td>
                                        <td><code><?php echo esc_html($bank->account_number); ?></code></td>
                                        <td>
                                            <?php if (!empty($bank->ifsc_code)): ?>
                                                <div style="font-size: 12px;">IFSC: <?php echo esc_html($bank->ifsc_code); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($bank->swift_code)): ?>
                                                <div style="font-size: 12px;">SWIFT: <?php echo esc_html($bank->swift_code); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($bank->branch_name ?: '-'); ?></td>
                                        <td>
                                            <span class="mgmt-status-pill <?php echo $bank->is_active ? 'approved' : 'rejected'; ?>">
                                                <?php echo $bank->is_active ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                                <form method="post" style="display: inline;">
                                                    <?php wp_nonce_field('unico_bank_management', 'bank_nonce'); ?>
                                                    <input type="hidden" name="bank_id" value="<?php echo $bank->id; ?>">
                                                    <button type="submit" name="bank_action" value="toggle_active" class="mgmt-btn mgmt-btn-secondary" style="font-size: 12px; padding: 4px 10px;">
                                                        <?php echo $bank->is_active ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                <button type="button" class="mgmt-btn mgmt-btn-secondary edit-bank-btn"
                                                    data-bank='<?php echo esc_attr(json_encode($bank)); ?>'
                                                    style="font-size: 12px; padding: 4px 10px;">
                                                    Edit
                                                </button>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this bank account? This action cannot be undone.');">
                                                    <?php wp_nonce_field('unico_bank_management', 'bank_nonce'); ?>
                                                    <input type="hidden" name="bank_id" value="<?php echo $bank->id; ?>">
                                                    <button type="submit" name="bank_action" value="delete_bank" class="mgmt-btn mgmt-btn-danger" style="font-size: 12px; padding: 4px 10px;">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mgmt-muted-text">No bank accounts configured. Add your first bank account above.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Application View Modal -->
<div id="application-modal" class="mgmt-modal">
    <div class="mgmt-modal-content">
        <span class="mgmt-modal-close">&times;</span>
        <h2>Application Details</h2>
        <div id="modal-application-content"></div>
    </div>
</div>

<style>
/* Modal Styles */
.mgmt-modal {
    display: none; 
    position: fixed; 
    z-index: 1000; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(0,0,0,0.4); 
}
.mgmt-modal-content {
    background-color: #fefefe;
    margin: 10% auto; 
    padding: 24px;
    border: 1px solid #888;
    width: 90%; 
    max-width: 600px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    position: relative;
}
.mgmt-modal-close {
    color: #aaa;
    position: absolute;
    right: 20px;
    top: 15px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}
.mgmt-modal-close:hover,
.mgmt-modal-close:focus {
    color: #333;
    text-decoration: none;
    cursor: pointer;
}
.mgmt-detail-row {
    margin-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 8px;
}
.mgmt-detail-row:last-child {
    border-bottom: none;
}
.mgmt-detail-label {
    font-weight: 600;
    color: #555;
    display: block;
    margin-bottom: 4px;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.mgmt-detail-value {
    color: #111;
    font-size: 1.05em;
    word-break: break-word;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tabs Logic
    var tabs = document.querySelectorAll('.mgmt-tab');
    var contents = document.querySelectorAll('.mgmt-tab-content');

    function setActiveTab(target) {
        tabs.forEach(function(tab) {
            tab.classList.toggle('active', tab.getAttribute('data-tab') === target);
        });
        contents.forEach(function(block) {
            block.classList.toggle('active', block.getAttribute('data-tab') === target);
        });
    }

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            setActiveTab(tab.getAttribute('data-tab'));
        });
    });

    // Default active tab
    var defaultTab = document.querySelector('.mgmt-tab.default-active') || tabs[0];
    if (defaultTab) {
        setActiveTab(defaultTab.getAttribute('data-tab'));
    }

    // Modal Logic
    var modal = document.getElementById('application-modal');
    var span = document.getElementsByClassName("mgmt-modal-close")[0];
    var content = document.getElementById('modal-application-content');

    document.querySelectorAll('.view-application-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var dataStr = this.getAttribute('data-application');
            var type = this.getAttribute('data-type');
            var id = this.getAttribute('data-id');
            
            try {
                var data = JSON.parse(dataStr);
                
                var html = '<div class="mgmt-detail-row"><span class="mgmt-detail-label">Application ID</span><span class="mgmt-detail-value">' + id + '</span></div>';
                html += '<div class="mgmt-detail-row"><span class="mgmt-detail-label">Type</span><span class="mgmt-detail-value">' + type + '</span></div>';
                
                for (var key in data) {
                    if (data.hasOwnProperty(key) && key !== 'application_type') {
                        var label = key.replace(/_/g, ' ').replace(/\b\w/g, function(l){ return l.toUpperCase() });
                        var value = data[key];
                        // Handle objects/arrays if any
                        if (typeof value === 'object' && value !== null) {
                            value = JSON.stringify(value);
                        }
                        html += '<div class="mgmt-detail-row"><span class="mgmt-detail-label">' + label + '</span><span class="mgmt-detail-value">' + value + '</span></div>';
                    }
                }
                
                content.innerHTML = html;
                modal.style.display = "block";
            } catch (e) {
                console.error('Error parsing application data', e);
                alert('Error viewing application details.');
            }
        });
    });

    if (span) {
        span.onclick = function() {
            modal.style.display = "none";
        }
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
});
</script>

<?php get_footer(); ?>
