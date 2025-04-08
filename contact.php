<?php
/*
Plugin Name: Dynamic Contact Form Management 
Description: A customizable contact form plugin with dynamic column management 
Version: 7.1.2
Author: ari0329
*/

if (!defined('ABSPATH')) {
    exit;
}

class DCFM_Email_Handler {
    private $admin_email;
    private $site_name;
    private $headers;

    public function __construct() {
        $this->admin_email = get_option('dcfm_admin_email', get_option('admin_email'));
        $this->site_name = get_bloginfo('name');
        $this->headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->site_name . ' <' . $this->admin_email . '>',
            'Reply-To: ' . $this->admin_email
        );
        
        add_action('wp_mail_failed', array($this, 'log_email_error'));
    }

    public function log_email_error($wp_error) {
        $error_message = $wp_error->get_error_message();
        error_log('DCFM Email Error: ' . print_r($error_message, true));
    }

    public function send_notification_emails($submission_data) {
        $admin_sent = $this->send_admin_notification($submission_data);
        $user_sent = $this->send_user_confirmation($submission_data);
        
        return array(
            'success' => $admin_sent && $user_sent,
            'admin_sent' => $admin_sent,
            'user_sent' => $user_sent
        );
    }

    private function send_admin_notification($submission_data) {
        $subject = get_option('dcfm_admin_email_subject', 'New form submission received');
        $message = $this->generate_admin_email_content($submission_data);
        
        try {
            $headers = $this->headers;
            $headers[] = 'X-Mailer: WordPress/DCFM-' . get_bloginfo('version');
            
            $sent = wp_mail($this->admin_email, $subject, $message, $headers);
            
            if (!$sent) {
                $error = error_get_last();
                error_log('DCFM: Admin email failed. PHP Mail Error: ' . print_r($error, true));
                error_log('DCFM: Admin email data: ' . print_r($submission_data, true));
            }
            
            return $sent;
        } catch (Exception $e) {
            error_log('DCFM: Exception in admin email: ' . $e->getMessage());
            return false;
        }
    }

    private function send_user_confirmation($submission_data) {
        if (empty($submission_data['email']) || !is_email($submission_data['email'])) {
            error_log('DCFM: Invalid user email address');
            return false;
        }
        
        $user_email = sanitize_email($submission_data['email']);
        $subject = get_option('dcfm_user_email_subject', 'Thank you for contacting us!');
        $message = $this->generate_user_email_content($submission_data);
        
        try {
            $headers = $this->headers;
            $headers[] = 'X-Mailer: WordPress/DCFM-' . get_bloginfo('version');
            
            $sent = wp_mail($user_email, $subject, $message, $headers);
            
            if (!$sent) {
                $error = error_get_last();
                error_log('DCFM: User confirmation email failed. PHP Mail Error: ' . print_r($error, true));
            }
            
            return $sent;
        } catch (Exception $e) {
            error_log('DCFM: Exception in user email: ' . $e->getMessage());
            return false;
        }
    }

    public function send_test_email() {
        $test_email = get_option('admin_email');
        $subject = 'DCFM Test Email';
        $message = 'This is a test email from your Dynamic Contact Form Management plugin.';
        
        $sent = wp_mail($test_email, $subject, $message, $this->headers);
        
        return array(
            'success' => $sent,
            'message' => $sent ? 'Test email sent successfully!' : 'Failed to send test email.'
        );
    }

    private function generate_admin_email_content($submission_data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                table {border-collapse: collapse; width: 100%; max-width: 600px;}
                th, td {border: 1px solid #ddd; padding: 8px; text-align: left;}
                th {background-color: #f8f8f8;}
                .header {background-color: #2271b1; color: white; padding: 20px; text-align: center;}
            </style>
        </head>
        <body>
            <div class="header">
                <h2>New Form Submission - <?php echo esc_html($this->site_name); ?></h2>
            </div>
            <table>
                <?php foreach ($submission_data as $key => $value): ?>
                <tr>
                    <th><?php echo esc_html(ucfirst($key)); ?></th>
                    <td><?php echo esc_html($value); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p style="color: #666; font-size: 12px; margin-top: 20px;">
                This email was sent from your contact form at <?php echo esc_html($this->site_name); ?>
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function generate_user_email_content($submission_data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                table {border-collapse: collapse; width: 100%; max-width: 600px;}
                th, td {border: 1px solid #ddd; padding: 8px; text-align: left;}
                th {background-color: #f8f8f8;}
                .header {background-color: #2271b1; color: white; padding: 20px; text-align: center;}
                .thank-you {font-size: 16px; line-height: 1.6; margin: 20px 0;}
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Thank You for Contacting <?php echo esc_html($this->site_name); ?></h2>
            </div>
            <div class="thank-you">
                <p>Thank you for reaching out to us. We have received your submission and will get back to you soon.</p>
                <p>Here's a copy of the information you submitted:</p>
            </div>
            <table>
                <?php foreach ($submission_data as $key => $value): ?>
                <tr>
                    <th><?php echo esc_html(ucfirst($key)); ?></th>
                    <td><?php echo esc_html($value); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p style="color: #666; font-size: 12px; margin-top: 20px;">
                This is an automated response from <?php echo esc_html($this->site_name); ?>. Please do not reply to this email.
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

class DynamicContactFormManager {
    private $submission_errors = array();
    private $email_handler;
    
    public function __construct() {
        $this->email_handler = new DCFM_Email_Handler();
        
        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_action('init', [$this, 'register_shortcode']);
        add_action('init', [$this, 'check_and_create_tables']);
        add_action('wp_enqueue_scripts', [$this, 'register_scripts'], 999); // Higher priority
        add_action('wp_ajax_submit_contact_form', [$this, 'handle_ajax_submission']);
        add_action('wp_ajax_nopriv_submit_contact_form', [$this, 'handle_ajax_submission']);
        add_action('admin_init', [$this, 'handle_csv_export']);
        add_action('wp_ajax_dcfm_test_email', [$this, 'handle_test_email']);
        
        register_activation_hook(__FILE__, [$this, 'activate_plugin']);
    }

    public function handle_test_email() {
        check_ajax_referer('dcfm_ajax_nonce', 'nonce');
        
        $result = $this->email_handler->send_test_email();
        wp_send_json($result);
    }
        
    public function activate_plugin() {
        $this->check_and_create_tables();
    }

    public function create_admin_menu() {
        add_menu_page(
            'Contact Forms',
            'Contact Forms',
            'manage_options',
            'dcfm-forms',
            [$this, 'render_forms_page'],
            'dashicons-email',
            30
        );

        add_submenu_page(
            'dcfm-forms',
            'Created Contact Forms',
            'Created Contact Forms',
            'manage_options',
            'dcfm-forms',
            [$this, 'render_forms_page']
        );

        add_submenu_page(
            'dcfm-forms',
            'Add New Form',
            'Add New Form',
            'manage_options',
            'dcfm-add-form',
            [$this, 'render_add_form_page']
        );

        add_submenu_page(
            'dcfm-forms',
            'Custom Fields',
            'Custom Fields',
            'manage_options',
            'dcfm-fields',
            [$this, 'render_fields_page']
        );

        add_submenu_page(
            'dcfm-forms',
            'Submissions',
            'Submissions',
            'manage_options',
            'dcfm-submissions',
            [$this, 'render_submissions_page']
        );
        
        add_submenu_page(
            'dcfm-forms',
            'Email Settings',
            'Email Settings',
            'manage_options',
            'dcfm-email-settings',
            [$this, 'render_email_settings_page']
        );
        
        add_submenu_page(
            'dcfm-forms',
            'Style Settings',
            'Style Settings',
            'manage_options',
            'dcfm-style-settings',
            [$this, 'render_style_settings_page']
        );
    }

    public function check_and_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
    
        $fields_table = $wpdb->prefix . 'dcfm_fields';
        $forms_table = $wpdb->prefix . 'dcfm_forms';
        $submissions_table = $wpdb->prefix . 'dcfm_submissions';
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
        $sql_fields = "CREATE TABLE $fields_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            field_name varchar(100) NOT NULL,
            field_type varchar(50) NOT NULL,
            is_required tinyint(1) DEFAULT 0,
            options text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        dbDelta($sql_fields);
    
        $sql_forms = "CREATE TABLE $forms_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            fields text NOT NULL,
            shortcode varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        dbDelta($sql_forms);
    
        $sql_submissions = "CREATE TABLE $submissions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_id mediumint(9) NOT NULL,
            submission_data text NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        dbDelta($sql_submissions);
    
        if ($wpdb->get_var("SHOW TABLES LIKE '$fields_table'") != $fields_table) {
            error_log("DCFM: Failed to create fields table");
        }
        
        if (!$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $fields_table WHERE field_name = %s", 'email'))) {
            $result = $wpdb->insert($fields_table, [
                'field_name' => 'email',
                'field_type' => 'email',
                'is_required' => 0
            ], ['%s', '%s', '%d']);
            
            if ($result === false) {
                error_log("DCFM: Failed to insert default email field: " . $wpdb->last_error);
            }
        }
    }
    
    public function register_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'dcfm-form-script',
            plugins_url('js/form-handler.js', __FILE__),
            array('jquery'),
            '1.2.1',
            true
        );
        
        // Enqueue base form styles
        wp_enqueue_style(
            'dcfm-form-styles',
            plugins_url('css/styles.css', __FILE__),
            array(),
            '1.1.0'
        );

        // Enqueue Bootstrap CSS
        wp_enqueue_style(
            'bootstrap-css',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
            array(),
            '5.3.0'
        );

        // Enqueue custom CSS with highest priority
        $custom_css = get_option('dcfm_custom_css', '');
        if (!empty($custom_css)) {
            // Add !important to CSS rules that don't already have it
            $custom_css = $this->add_important_to_css($custom_css);
            
            wp_enqueue_style(
                'dcfm-custom-styles',
                false, // No external file, using inline
                array('dcfm-form-styles', 'bootstrap-css'), // Dependencies
                '1.1.0'
            );
            wp_add_inline_style('dcfm-custom-styles', $custom_css);
        }

        wp_localize_script('dcfm-form-script', 'dcfmAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dcfm_ajax_nonce')
        ));
    }

    private function add_important_to_css($css) {
        // Split CSS into individual rules
        $rules = explode('}', $css);
        $modified_css = '';
        
        foreach ($rules as $rule) {
            $rule = trim($rule);
            if (empty($rule)) {
                continue;
            }
            
            // Check if rule already has !important
            if (strpos($rule, '!important') === false) {
                // Add !important before the closing semicolon of each property
                $rule = preg_replace('/;(?=\s*$)/', ' !important;', $rule);
                $rule = preg_replace('/;(?=\s*[^\s;}]+)/', ' !important;', $rule);
            }
            
            $modified_css .= $rule . '}';
        }
        
        return $modified_css;
    }

    public function render_add_form_page() {
        global $wpdb;
        $optional_fields = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}dcfm_fields 
            ORDER BY created_at ASC"
        );
        
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $form_data = $edit_id ? $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dcfm_forms WHERE id = %d", 
            $edit_id
        )) : null;
        
        $selected_fields = $form_data ? json_decode($form_data->fields, true) : array();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dcfm_nonce'])) {
            $this->save_form();
        }
    
        wp_enqueue_script('jquery-ui-sortable');
        ?>
        <div class="wrap">
            <h1><?php echo $edit_id ? 'Edit Form' : 'Add New Form'; ?></h1>
            
            <style>
                .field-container { margin: 20px 0; }
                .field-item {
                    background: #fff;
                    border: 1px solid #ddd;
                    padding: 10px;
                    margin: 5px 0;
                    cursor: move;
                    border-radius: 4px;
                }
                .field-item:hover {
                    background: #f9f9f9;
                }
                .field-item.ui-sortable-helper {
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                }
                .field-placeholder {
                    border: 2px dashed #ccc;
                    height: 40px;
                    margin: 5px 0;
                }
                .field-controls {
                    float: right;
                }
                .required-label {
                    color: #d63638;
                    margin-left: 10px;
                }
                .field-type {
                    color: #666;
                    margin-left: 10px;
                    font-style: italic;
                }
                .field-item .dashicons {
                    color: #666;
                    cursor: pointer;
                    margin-left: 5px;
                }
                .field-item .dashicons:hover {
                    color: #135e96;
                }
            </style>
    
            <form method="post" id="create-form">
                <?php wp_nonce_field('dcfm_nonce', 'dcfm_nonce'); ?>
                <input type="hidden" name="form_id" value="<?php echo esc_attr($edit_id); ?>">
                <input type="hidden" name="field_order" id="field-order" value="">
                
                <table class="form-table">
                    <tr>
                        <th><label for="form_title">Form Title</label></th>
                        <td>
                            <input type="text" name="form_title" id="form_title" class="regular-text" 
                                value="<?php echo esc_attr($form_data->title ?? ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th>Form Fields</th>
                        <td>
                            <div class="field-container">
                                <div id="selected-fields" class="sortable-fields">
                                    <?php 
                                    foreach ($selected_fields as $field_id):
                                        $field = null;
                                        foreach ($optional_fields as $of) {
                                            if ($of->id == $field_id) {
                                                $field = $of;
                                                break;
                                            }
                                        }
                                        if ($field):
                                    ?>
                                        <div class="field-item" data-field-id="<?php echo esc_attr($field->id); ?>">
                                            <input type="hidden" name="fields[]" value="<?php echo esc_attr($field->id); ?>">
                                            <?php echo esc_html(ucfirst($field->field_name)); ?>
                                            <span class="field-type">(<?php echo esc_html($field->field_type); ?>)</span>
                                            <div class="field-controls">
                                                <span class="dashicons dashicons-move"></span>
                                                <span class="dashicons dashicons-no remove-field"></span>
                                            </div>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
    
                                <h4>Available Fields</h4>
                                <div id="available-fields">
                                    <?php 
                                    foreach ($optional_fields as $field):
                                        if (!in_array($field->id, $selected_fields)):
                                    ?>
                                        <div class="field-item" data-field-id="<?php echo esc_attr($field->id); ?>">
                                            <?php echo esc_html(ucfirst($field->field_name)); ?>
                                            <span class="field-type">(<?php echo esc_html($field->field_type); ?>)</span>
                                            <div class="field-controls">
                                                <span class="dashicons dashicons-plus-alt2 add-field"></span>
                                            </div>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button button-primary" 
                        value="<?php echo $edit_id ? 'Update Form' : 'Create Form'; ?>">
                </p>
            </form>
        </div>
    
        <script>
        jQuery(document).ready(function($) {
            $('#selected-fields').sortable({
                placeholder: 'field-placeholder',
                handle: '.dashicons-move',
                update: function(event, ui) {
                    updateFieldOrder();
                }
            });
    
            $(document).on('click', '.add-field', function() {
                var fieldItem = $(this).closest('.field-item');
                var fieldId = fieldItem.data('field-id');
                
                var newField = fieldItem.clone();
                newField.find('.field-controls').html(
                    '<span class="dashicons dashicons-move"></span>' +
                    '<span class="dashicons dashicons-no remove-field"></span>'
                );
                newField.append('<input type="hidden" name="fields[]" value="' + fieldId + '">');
                
                $('#selected-fields').append(newField);
                fieldItem.remove();
                updateFieldOrder();
            });
    
            $(document).on('click', '.remove-field', function() {
                var fieldItem = $(this).closest('.field-item');
                var fieldId = fieldItem.data('field-id');
                
                var availableField = fieldItem.clone();
                availableField.find('.field-controls').html(
                    '<span class="dashicons dashicons-plus-alt2 add-field"></span>'
                );
                availableField.find('input[type="hidden"]').remove();
                
                $('#available-fields').append(availableField);
                fieldItem.remove();
                updateFieldOrder();
            });
    
            function updateFieldOrder() {
                var order = $('#selected-fields .field-item').map(function() {
                    return $(this).data('field-id');
                }).get();
                $('#field-order').val(JSON.stringify(order));
            }
    
            updateFieldOrder();
        });
        </script>
        <?php
    }

    public function save_form() {
        if (!isset($_POST['dcfm_nonce']) || !wp_verify_nonce($_POST['dcfm_nonce'], 'dcfm_nonce')) {
            wp_die('Security check failed');
        }
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'dcfm_forms';
    
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $title = isset($_POST['form_title']) ? sanitize_text_field($_POST['form_title']) : '';
        $fields = isset($_POST['fields']) ? array_map('intval', $_POST['fields']) : [];
    
        if (empty($title) || empty($fields)) {
            echo '<div class="error"><p>Form title and fields are required.</p></div>';
            return;
        }
    
        $data = [
            'title' => $title,
            'fields' => json_encode($fields),
            'shortcode' => '[contact_form id="' . ($form_id > 0 ? $form_id : 'NEW') . '"]'
        ];
    
        if ($form_id > 0) {
            $result = $wpdb->update($table_name, $data, ['id' => $form_id]);
            if ($result === false) {
                echo '<div class="error"><p>Failed to update form: ' . $wpdb->last_error . '</p></div>';
            } else {
                echo '<div class="updated"><p>Form updated successfully!</p></div>';
            }
        } else {
            $result = $wpdb->insert($table_name, $data);
            if ($result === false) {
                echo '<div class="error"><p>Failed to create form: ' . $wpdb->last_error . '</p></div>';
            } else {
                $new_form_id = $wpdb->insert_id;
                $wpdb->update(
                    $table_name,
                    ['shortcode' => '[contact_form id="' . $new_form_id . '"]'],
                    ['id' => $new_form_id]
                );
                echo '<div class="updated"><p>Form created successfully!</p></div>';
            }
        }
    }

    public function render_forms_page() {
        global $wpdb;
        $forms_table = $wpdb->prefix . 'dcfm_forms';
    
        if (isset($_POST['delete_form']) && isset($_POST['form_id'])) {
            check_admin_referer('dcfm_delete_form_' . $_POST['form_id']);
    
            $form_id = intval($_POST['form_id']);
            $wpdb->delete($forms_table, ['id' => $form_id], ['%d']);
    
            echo '<div class="updated"><p>Form deleted successfully!</p></div>';
        }
    
        $forms = $wpdb->get_results("SELECT * FROM {$forms_table} ORDER BY created_at DESC");
        ?>
    
        <div class="wrap">
            <h1>Created Contact Forms</h1>
            <a href="<?php echo admin_url('admin.php?page=dcfm-add-form'); ?>" class="button button-primary">Add New Form</a>
            
            <?php if (empty($forms)): ?>
                <p>No forms found. Create a new form to get started.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Shortcode</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forms as $form): ?>
                            <tr id="form-<?php echo esc_attr($form->id); ?>">
                                <td><?php echo esc_html($form->title); ?></td>
                                <td><code>[contact_form id="<?php echo esc_html($form->id); ?>"]</code></td>
                                <td><?php echo esc_html($form->created_at); ?></td>
                                <td>
                                    <a href="<?php echo admin_url("admin.php?page=dcfm-add-form&edit={$form->id}"); ?>">Edit</a> |
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('dcfm_delete_form_' . $form->id); ?>
                                        <input type="hidden" name="form_id" value="<?php echo esc_attr($form->id); ?>">
                                        <button type="submit" name="delete_form" class="button delete-form"
                                                onclick="return confirm('Are you sure you want to delete this form?');">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function handle_csv_export() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
    
        if (isset($_POST['export_csv'])) {
            if (!isset($_POST['csv_export_nonce']) || !wp_verify_nonce($_POST['csv_export_nonce'], 'csv_export')) {
                wp_die('Security check failed');
            }
    
            global $wpdb;
            $submissions_table = $wpdb->prefix . 'dcfm_submissions';
            $forms_table = $wpdb->prefix . 'dcfm_forms';
    
            $submissions = $wpdb->get_results("
                SELECT s.*, f.title as form_title
                FROM $submissions_table s
                JOIN $forms_table f ON s.form_id = f.id
                ORDER BY s.created_at DESC
            ");
    
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="form-submissions-' . date('Y-m-d') . '.csv"');
    
            $output = fopen('php://output', 'w');
    
            fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
            fputcsv($output, ['Form', 'Submission Data', 'Date']);
    
            foreach ($submissions as $submission) {
                $data = json_decode($submission->submission_data, true);
                $submission_data = [];
    
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        $submission_data[] = "$key: $value";
                    }
                }
    
                fputcsv($output, [
                    $submission->form_title,
                    implode(' | ', $submission_data),
                    get_date_from_gmt($submission->created_at)
                ]);
            }
    
            fclose($output);
            exit;
        }
    }

    public function render_submissions_page() {
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'dcfm_submissions';
        $forms_table = $wpdb->prefix . 'dcfm_forms';
    
        $submissions = $wpdb->get_results("
            SELECT 
                s.*, 
                f.title as form_title,
                CONVERT_TZ(s.created_at, '+00:00', @@session.time_zone) as local_time
            FROM $submissions_table s
            JOIN $forms_table f ON s.form_id = f.id
            ORDER BY s.created_at DESC
        ");
    
        ?>
        <div class="wrap">
            <h1>Form Submissions</h1>
            <form method="post" action="">
                <?php wp_nonce_field('csv_export', 'csv_export_nonce'); ?>
                <input type="submit" name="export_csv" class="button button-primary" value="Export as CSV">
                <button type="button" id="export-pdf" class="button button-primary">Export as PDF</button>
            </form>
    
            <?php if (empty($submissions)): ?>
                <p>No submissions found.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped" id="submissions-table">
                    <thead>
                        <tr>
                            <th>Form</th>
                            <th>Submission Data</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td><?php echo esc_html($submission->form_title); ?></td>
                                <td>
                                    <?php
                                    $data = json_decode($submission->submission_data, true);
                                    if (is_array($data)) {
                                        foreach ($data as $key => $value) {
                                            echo esc_html($key) . ': ' . esc_html($value) . '<br>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html(get_date_from_gmt($submission->created_at)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
        <script>
            document.getElementById('export-pdf').addEventListener('click', function() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
    
                const table = document.getElementById('submissions-table');
                const rows = Array.from(table.querySelectorAll('tr'));
                
                const headers = Array.from(rows[0].querySelectorAll('th')).map(th => th.textContent.trim());
                const body = rows.slice(1).map(row => 
                    Array.from(row.querySelectorAll('td')).map(td => {
                        return td.innerHTML.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '');
                    })
                );
    
                doc.autoTable({
                    head: [headers],
                    body: body,
                    startY: 20,
                    margin: { top: 20 },
                    styles: { overflow: 'linebreak' },
                    columnStyles: {
                        0: { cellWidth: 40 },
                        1: { cellWidth: 'auto' },
                        2: { cellWidth: 40 }
                    },
                    didDrawPage: function(data) {
                        doc.setFontSize(16);
                        doc.text('Form Submissions', data.settings.margin.left, 15);
                    }
                });
    
                doc.save('form-submissions.pdf');
            });
        </script>
        <?php
    }

    public function render_fields_page() {
        global $wpdb;
        $fields_table = $wpdb->prefix . 'dcfm_fields';

        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['field_id'])) {
            $field_id = intval($_POST['field_id']);
            $wpdb->delete(
                $fields_table,
                ['id' => $field_id],
                ['%d']
            );
            echo '<div class="updated"><p>Field deleted successfully!</p></div>';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_field'])) {
            $field_name = sanitize_text_field($_POST['field_name']);
            $field_type = sanitize_text_field($_POST['field_type']);
            $options = ($field_type === 'select' && isset($_POST['select_options'])) ? 
                sanitize_text_field($_POST['select_options']) : '';
            $is_required = isset($_POST['is_required']) ? 1 : 0;

            $result = $wpdb->insert(
                $fields_table,
                [
                    'field_name' => $field_name,
                    'field_type' => $field_type,
                    'is_required' => $is_required,
                    'options' => $options
                ],
                ['%s', '%s', '%d', '%s']
            );
            
            if ($result === false) {
                echo '<div class="error"><p>Failed to add field: ' . $wpdb->last_error . '</p></div>';
                error_log('DCFM: Failed to add field: ' . $wpdb->last_error);
                error_log('DCFM: Attempted data: ' . print_r([
                    'field_name' => $field_name,
                    'field_type' => $field_type,
                    'is_required' => $is_required,
                    'options' => $options
                ], true));
            } else {
                echo '<div class="updated"><p>Field added successfully!</p></div>';
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_field'])) {
            $field_id = intval($_POST['field_id']);
            $field_name = sanitize_text_field($_POST['field_name']);
            $field_type = sanitize_text_field($_POST['field_type']);
            $options = ($field_type === 'select' && isset($_POST['select_options'])) ? 
                sanitize_text_field($_POST['select_options']) : '';
            $is_required = isset($_POST['is_required']) ? 1 : 0;

            $result = $wpdb->update(
                $fields_table,
                [
                    'field_name' => $field_name,
                    'field_type' => $field_type,
                    'is_required' => $is_required,
                    'options' => $options
                ],
                ['id' => $field_id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );
            
            if ($result === false) {
                echo '<div class="error"><p>Failed to update field: ' . $wpdb->last_error . '</p></div>';
                error_log('DCFM: Failed to update field: ' . $wpdb->last_error);
            } else {
                echo '<div class="updated"><p>Field updated successfully!</p></div>';
            }
        }

        $edit_mode = false;
        $edit_field = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['field_id'])) {
            $edit_mode = true;
            $field_id = intval($_GET['field_id']);
            $edit_field = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fields_table WHERE id = %d", $field_id));
        }

        $fields = $wpdb->get_results("SELECT * FROM $fields_table ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1>Custom Fields Management</h1>
            <form method="post" class="add-field-form">
                <h3><?php echo $edit_mode ? 'Edit Field' : 'Add New Field'; ?></h3>
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="field_id" value="<?php echo esc_attr($edit_field->id); ?>">
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="field_name">Field Name</label></th>
                        <td>
                            <input type="text" 
                                   name="field_name" 
                                   id="field_name" 
                                   class="regular-text" 
                                   required 
                                   value="<?php echo $edit_mode ? esc_attr($edit_field->field_name) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="field_type">Field Type</label></th>
                        <td>
                            <select name="field_type" id="field_type" onchange="toggleSelectOptions(this)">
                                <?php
                                $field_types = ['text', 'email', 'tel', 'textarea', 'date', 'number', 'checkbox', 'select'];
                                foreach ($field_types as $type):
                                    $selected = $edit_mode && $edit_field->field_type === $type ? 'selected' : '';
                                ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php echo $selected; ?>>
                                        <?php echo ucfirst($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="select_options_row" style="display: <?php echo ($edit_mode && $edit_field->field_type === 'select') ? 'table-row' : 'none'; ?>;">
                        <th><label for="select_options">Select Options</label></th>
                        <td>
                            <input type="text" 
                                   name="select_options" 
                                   id="select_options" 
                                   class="regular-text"
                                   value="<?php echo $edit_mode ? esc_attr($edit_field->options) : ''; ?>"
                                   placeholder="Enter options separated by commas (e.g., Option1, Option2, Option3)">
                            <p class="description">For select fields only: enter options separated by commas</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="is_required">Required Field?</label></th>
                        <td>
                            <input type="checkbox" 
                                   name="is_required" 
                                   id="is_required"
                                   <?php echo $edit_mode && $edit_field->is_required ? 'checked' : ''; ?>
                            >
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" 
                           name="<?php echo $edit_mode ? 'edit_field' : 'add_field'; ?>" 
                           class="button button-primary" 
                           value="<?php echo $edit_mode ? 'Update Field' : 'Add Field'; ?>">
                    <?php if ($edit_mode): ?>
                        <a href="?page=dcfm-fields" class="button">Cancel</a>
                    <?php endif; ?>
                </p>
            </form>

            <h3>Existing Fields</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Field Name</th>
                        <th>Field Type</th>
                        <th>Required</th>
                        <th>Options</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $field): ?>
                        <tr>
                            <td><?php echo esc_html($field->field_name); ?></td>
                            <td><?php echo esc_html($field->field_type); ?></td>
                            <td><?php echo $field->is_required ? 'Yes' : 'No'; ?></td>
                            <td><?php echo ($field->field_type === 'select' && $field->options) ? esc_html($field->options) : '-'; ?></td>
                            <td><?php echo esc_html($field->created_at); ?></td>
                            <td>
                                <a href="?page=dcfm-fields&action=edit&field_id=<?php echo $field->id; ?>" 
                                   class="button button-small">
                                    Edit
                                </a>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="field_id" value="<?php echo $field->id; ?>">
                                    <button type="submit" 
                                            class="button button-small" 
                                            onclick="return confirm('Are you sure you want to delete this field?');">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
            function toggleSelectOptions(select) {
                var optionsRow = document.getElementById('select_options_row');
                optionsRow.style.display = (select.value === 'select') ? 'table-row' : 'none';
            }
        </script>
        <?php
    }

    public function register_shortcode() {
        add_shortcode('contact_form', [$this, 'render_frontend_form']);
    }

    public function render_frontend_form($atts) {
        global $wpdb;
        $atts = shortcode_atts(['id' => 0], $atts);
        $form_id = intval($atts['id']);

        if ($form_id <= 0) {
            return '<p>Error: Form ID is missing.</p>';
        }

        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dcfm_forms WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            return '<p>Error: Form not found.</p>';
        }

        $field_ids = json_decode($form->fields, true);
        
        $fields_query = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}dcfm_fields WHERE id IN (" . 
            implode(',', array_map('intval', $field_ids)) . ")"
        );
        
        $field_data = array();
        foreach ($fields_query as $field) {
            $field_data[$field->id] = $field;
        }

        $ordered_fields = array();
        foreach ($field_ids as $id) {
            if (isset($field_data[$id])) {
                $ordered_fields[] = $field_data[$id];
            }
        }

        $captcha = '';
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < 4; $i++) {
            $captcha .= $characters[rand(0, strlen($characters) - 1)];
        }

        ob_start();
        ?>
        <div class="dcfm-form-wrapper">
            <div id="form-message-<?php echo esc_attr($form_id); ?>"></div>
            <form id="dcfm-form-<?php echo esc_attr($form_id); ?>" class="dcfm-form" method="post">
                <?php foreach ($ordered_fields as $field): ?>
                    <div class="form-field">
                        <label for="<?php echo esc_attr($field->field_name); ?>">
                            <?php echo esc_html(ucfirst($field->field_name)); ?>
                            <?php if ($field->is_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($field->field_type === 'textarea'): ?>
                            <textarea 
                                id="<?php echo esc_attr($field->field_name); ?>"
                                name="<?php echo esc_attr($field->field_name); ?>"
                                <?php echo $field->is_required ? 'required' : ''; ?>
                            ></textarea>
                        <?php elseif ($field->field_type === 'date'): ?>
                            <input 
                                type="date"
                                id="<?php echo esc_attr($field->field_name); ?>"
                                name="<?php echo esc_attr($field->field_name); ?>"
                                <?php echo $field->is_required ? 'required' : ''; ?>
                            >
                        <?php elseif ($field->field_type === 'email'): ?>
                            <input 
                                type="email"
                                id="<?php echo esc_attr($field->field_name); ?>"
                                name="<?php echo esc_attr($field->field_name); ?>"
                                pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                <?php echo $field->is_required ? 'required' : ''; ?>
                            >
                        <?php elseif ($field->field_type === 'tel'): ?>
                            <input 
                                type="tel"
                                id="<?php echo esc_attr($field->field_name); ?>"
                                name="<?php echo esc_attr($field->field_name); ?>"
                                pattern="[0-9-+\s()]+"
                                <?php echo $field->is_required ? 'required' : ''; ?>
                            >
                        <?php elseif ($field->field_type === 'number'): ?>
                            <input 
                                type="number"
                                id="<?php echo esc_attr($field->field_name); ?>"
                                name="<?php echo esc_attr($field->field_name); ?>"
                                <?php echo $field->is_required ? 'required' : ''; ?>
                            >
                        <?php elseif ($field->field_type === 'checkbox'): ?>
                            <input 
                                type="checkbox"
                                id="<?php echo esc_attr($field->field_name); ?>"
                                name="<?php echo esc_attr($field->field_name); ?>"
                                value="1"
                                <?php echo $field->is_required ? 'required' : ''; ?>
                            >
                        <?php elseif ($field->field_type === 'select'): ?>
                            <select 
                                id="<?php echo esc_attr($field->field_name); ?>"
                                name="<?php echo esc_attr($field->field_name); ?>"
                                <?php echo $field->is_required ? 'required' : ''; ?>
                            >
                                <option value="">Select an option</option>
                                <?php
                                if (!empty($field->options)) {
                                    $options = explode(',', $field->options);
                                    foreach ($options as $option) {
                                        echo '<option value="' . esc_attr(trim($option)) . '">' . esc_html(trim($option)) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        <?php else: ?>
                            <input 
                                type="<?php echo esc_attr($field->field_type); ?>"
                                id="<?php echo esc_attr($field->field_name); ?>"
                                name="<?php echo esc_attr($field->field_name); ?>"
                                <?php echo $field->is_required ? 'required' : ''; ?>
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="form-field captcha-field">
                    <label for="captcha_input">Captcha Check <span class="required">*</span></label>
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-2">
                            <div id="captcha-display-<?php echo esc_attr($form_id); ?>" class="captcha-display"><?php echo $captcha; ?></div>
                        </div>
                        <div class="col-12 col-md-1">
                            <button type="button" class="reset-captcha">↻</button>
                        </div>
                        <div class="col-12 col-md-9">
                            <input type="text" id="captcha_input" name="captcha_input" class="form-control" required placeholder="Enter code" required>
                            <input type="hidden" id="captcha_answer-<?php echo esc_attr($form_id); ?>" name="captcha_answer" value="<?php echo $captcha; ?>">
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
                <button type="submit" class="submit-button btn btn-primary">Submit</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_ajax_submission() {
        error_log('DCFM: AJAX Submission Received - POST Data: ' . print_r($_POST, true));

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dcfm_ajax_nonce')) {
            error_log('DCFM: Nonce verification failed');
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dcfm_submissions';
        
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if ($form_id <= 0) {
            error_log('DCFM: Invalid form ID: ' . $form_id);
            wp_send_json_error('Invalid form ID');
            return;
        }
        
        $user_answer = isset($_POST['captcha_input']) ? sanitize_text_field($_POST['captcha_input']) : '';
        $correct_answer = isset($_POST['captcha_answer']) ? sanitize_text_field($_POST['captcha_answer']) : '';
        
        if (empty($user_answer) || $user_answer !== $correct_answer) {
            error_log("DCFM: Captcha mismatch - User: '$user_answer', Correct: '$correct_answer'");
            wp_send_json_error('Incorrect captcha. Please try again.');
            return;
        }
        
        $submission_data = array();
        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['action', 'nonce', 'form_id', 'captcha_input', 'captcha_answer'])) {
                $submission_data[$key] = sanitize_text_field($value);
            }
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'form_id' => $form_id,
                'submission_data' => wp_json_encode($submission_data),
                'created_at' => current_time('mysql', true)
            ),
            array('%d', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('DCFM: Database insert failed: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save submission: ' . $wpdb->last_error);
            return;
        }
        
        $email_result = $this->email_handler->send_notification_emails($submission_data);

        if ($email_result['success']) {
            error_log('DCFM: Form submitted and emails sent successfully');
            wp_send_json_success('Form submitted successfully and all notifications sent!');
        } else {
            $message = 'Form submitted successfully but ';
            if (!$email_result['admin_sent'] && !$email_result['user_sent']) {
                $message .= 'there were issues sending notifications.';
            } else if (!$email_result['admin_sent']) {
                $message .= 'admin notification failed to send.';
            } else {
                $message .= 'user confirmation failed to send.';
            }
            error_log('DCFM: ' . $message);
            wp_send_json_success($message);
        }
    }

    public function handle_form_submission() {
        if (!isset($_POST['dcfm_nonce']) || !wp_verify_nonce($_POST['dcfm_nonce'], 'dcfm_submission_nonce')) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dcfm_submissions';

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if ($form_id <= 0) {
            $this->submission_errors[] = 'Invalid form submission.';
            return false;
        }

        $user_answer = isset($_POST['captcha_input']) ? sanitize_text_field($_POST['captcha_input']) : '';
        $correct_answer = isset($_POST['captcha_answer']) ? sanitize_text_field($_POST['captcha_answer']) : '';
        
        if ($user_answer !== $correct_answer) {
            $this->submission_errors[] = 'Incorrect captcha.';
            return false;
        }

        $submission_data = array();
        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['form_id', 'dcfm_nonce', 'dcfm_submission_nonce', 'captcha_input', 'captcha_answer'])) {
                $submission_data[$key] = sanitize_text_field($value);
            }
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'form_id' => $form_id,
                'submission_data' => wp_json_encode($submission_data),
                'created_at' => current_time('mysql', true)
            ),
            array('%d', '%s', '%s')
        );

        if (!$result) {
            $this->submission_errors[] = 'Database error: ' . $wpdb->last_error;
            return false;
        }

        return true;
    }

    public function render_email_settings_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dcfm_email_settings_nonce'])) {
            if (!wp_verify_nonce($_POST['dcfm_email_settings_nonce'], 'dcfm_email_settings')) {
                wp_die('Security check failed');
            }

            update_option('dcfm_admin_email', sanitize_email($_POST['admin_email']));
            update_option('dcfm_user_email_subject', sanitize_text_field($_POST['user_email_subject']));
            update_option('dcfm_admin_email_subject', sanitize_text_field($_POST['admin_email_subject']));

            echo '<div class="updated"><p>Email settings saved successfully!</p></div>';
        }

        $admin_email = get_option('dcfm_admin_email', get_option('admin_email'));
        $user_email_subject = get_option('dcfm_user_email_subject', 'Thank you for contacting us!');
        $admin_email_subject = get_option('dcfm_admin_email_subject', 'New form submission received');

        ?>
        <div class="wrap">
            <h1>Email Settings</h1>
            <form method="post">
                <?php wp_nonce_field('dcfm_email_settings', 'dcfm_email_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="admin_email">Admin Email</label></th>
                        <td>
                            <input type="email" name="admin_email" id="admin_email" class="regular-text" 
                                value="<?php echo esc_attr($admin_email); ?>" required>
                            <p class="description">This is where form submissions will be sent.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="user_email_subject">User Email Subject</label></th>
                        <td>
                            <input type="text" name="user_email_subject" id="user_email_subject" class="regular-text" 
                                value="<?php echo esc_attr($user_email_subject); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="admin_email_subject">Admin Email Subject</label></th>
                        <td>
                            <input type="text" name="admin_email_subject" id="admin_email_subject" class="regular-text" 
                                value="<?php echo esc_attr($admin_email_subject); ?>" required>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save Email Settings">
                </p>
            </form>
        </div>
        <?php
    }

    public function render_style_settings_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dcfm_style_settings_nonce'])) {
            if (!wp_verify_nonce($_POST['dcfm_style_settings_nonce'], 'dcfm_style_settings')) {
                wp_die('Security check failed');
            }

            update_option('dcfm_custom_css', wp_kses_post($_POST['custom_css']));
            echo '<div class="updated"><p>Style settings saved successfully!</p></div>';
        }

        $custom_css = get_option('dcfm_custom_css', '');

        ?>
        <div class="wrap">
            <h1>Style Settings</h1>
            <form method="post">
                <?php wp_nonce_field('dcfm_style_settings', 'dcfm_style_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="custom_css">Custom CSS</label></th>
                        <td>
                            <textarea name="custom_css" id="custom_css" rows="10" class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
                            <p class="description">Add custom CSS to style your forms. These styles will override all other CSS with !important flags.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save Style Settings">
                </p>
            </form>
        </div>
        <?php
    }
}

new DynamicContactFormManager();