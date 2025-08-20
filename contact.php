<?php
/*
Plugin Name: Dynamic Contact Form Management 
Description: A customizable contact form plugin with dynamic column management and template support
Version: 7.9.1.9
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
        $header_color = get_option('dcfm_email_header_color', '#2271b4');
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                table {border-collapse: collapse; width: 100%; max-width: 600px;}
                th, td {border: 1px solid #ddd; padding: 8px; text-align: left;}
                th {background-color: #f8f8f8;}
                .header {background-color: <?php echo esc_attr($header_color); ?>; color: white; padding: 20px; text-align: center;}
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
        $header_color = get_option('dcfm_email_header_color', '#2271b1');
        $user_email_header = get_option('dcfm_user_email_header', '<p>Thank you for reaching out to us. We have received your submission and will get back to you soon.</p><p>Here is a copy of the information you submitted:</p>');
        $user_email_footer = get_option('dcfm_user_email_footer', '<p style="color: #666; font-size: 12px; margin-top: 20px;">This is an automated response from ' . esc_html($this->site_name) . '. Please do not reply to this email.</p>');
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                table { border-collapse: collapse; width: 100%; max-width: 600px; }
                textarea { width: 100px; height: auto; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f8f8; }
                .header { background-color: <?php echo esc_attr($header_color); ?>; color: white; padding: 20px; text-align: center; }
                .thank-you { font-size: 16px; line-height: 1.6; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Thank You for Contacting <?php echo esc_html($this->site_name); ?></h2>
            </div>
            <div class="thank-you">
                <?php echo wp_kses_post($user_email_header); ?>
            </div>
            <table>
                <?php foreach ($submission_data as $key => $value): ?>
                    <tr>
                        <th><?php echo esc_html(ucfirst($key)); ?></th>
                        <td><?php echo esc_html($value); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php echo wp_kses_post($user_email_footer); ?>
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
        add_action('wp_enqueue_scripts', [$this, 'register_scripts'], 999);
        add_action('wp_ajax_submit_contact_form', [$this, 'handle_ajax_submission']);
        add_action('wp_ajax_nopriv_submit_contact_form', [$this, 'handle_ajax_submission']);
        add_action('admin_init', [$this, 'handle_csv_export']);
        add_action('wp_ajax_dcfm_test_email', [$this, 'handle_test_email']);
        add_action('wp_ajax_dcfm_quick_edit_template', [$this, 'handle_quick_edit_template']);
        
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
        
        add_submenu_page(
            'dcfm-forms',
            'Templates',
            'Templates',
            'manage_options',
            'dcfm-templates',
            [$this, 'render_templates_page']
        );
        add_submenu_page(
        'dcfm_templates',
        'Edit Template',
        'Edit Template',
        'manage_options',
        'dcfm_edit_template',
        [$this, 'render_edit_template_page']
    );
    }


private function add_important_to_css($css) {
        $lines = explode("\n", trim($css));
        $modified_css = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '{') !== false || strpos($line, '}') !== false) {
                $modified_css .= $line . "\n";
                continue;
            }
            
            if (strpos($line, '!important') === false && strpos($line, ':') !== false && substr($line, -1) === ';') {
                $line = str_replace(';', ' !important;', $line);
            }
            
            $modified_css .= $line . "\n";
        }
        
        $modified_css = trim($modified_css);
        error_log('DCFM: Modified CSS after adding !important: ' . $modified_css);
        return $modified_css;
    }



private function get_field_icon($field_type) {
        switch ($field_type) {
            case 'text':
                return 'fa-text';
            case 'email':
                return 'fa-envelope';
            case 'textarea':
                return 'fa-paragraph';
            case 'select':
                return 'fa-caret-down';
            case 'checkbox':
                return 'fa-check-square';
            case 'radio':
                return 'fa-dot-circle';
            default:
                return 'fa-text';
        }
    }


    

// start the new code 1

public function render_edit_template_page() {
    global $wpdb;
    $templates_table = $wpdb->prefix . 'dcfm_templates';
    $fields_table = $wpdb->prefix . 'dcfm_fields';
    $error_message = '';
    $success_message = '';

    // Verify nonce and get template ID
    if (isset($_GET['template_id']) && isset($_GET['dcfm_edit_nonce']) && wp_verify_nonce($_GET['dcfm_edit_nonce'], 'edit_template')) {
        $template_id = intval($_GET['template_id']);
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $templates_table WHERE id = %d", $template_id));

        if (!$template) {
            $error_message = 'Template not found.';
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template']) && wp_verify_nonce($_POST['dcfm_template_nonce'], 'template_actions')) {
                $title = isset($_POST['template_title']) ? sanitize_text_field($_POST['template_title']) : $template->title;
                $template_color = isset($_POST['template_color']) ? sanitize_hex_color($_POST['template_color']) : $template->template_color;
                $text_color = isset($_POST['text_color']) ? sanitize_hex_color($_POST['text_color']) : $template->text_color;
                $input_color = isset($_POST['input_color']) ? sanitize_hex_color($_POST['input_color']) : $template->input_color;
                $text_font = isset($_POST['text_font']) ? sanitize_text_field($_POST['text_font']) : $template->text_font;
                $hover_effect = isset($_POST['hover_effect']) ? sanitize_text_field($_POST['hover_effect']) : $template->hover_effect;
                $hover_color = isset($_POST['hover_color']) ? sanitize_hex_color($_POST['hover_color']) : $template->hover_color;
                $animation = isset($_POST['animation']) ? sanitize_text_field($_POST['animation']) : $template->animation;
                $submit_button_width = isset($_POST['submit_button_width']) ? sanitize_text_field($_POST['submit_button_width']) : $template->submit_button_width;
                $submit_button_position = ($submit_button_width === 'max-width' && isset($_POST['submit_button_position'])) ? sanitize_text_field($_POST['submit_button_position']) : $template->submit_button_position;
                $border_enabled = isset($_POST['border_enabled']) ? 1 : $template->border_enabled;
                $border_type = isset($_POST['border_type']) ? sanitize_text_field($_POST['border_type']) : $template->border_type;
                $border_radius = isset($_POST['border_radius']) ? sanitize_text_field($_POST['border_radius']) : $template->border_radius;
                $border_position = isset($_POST['border_position']) && is_array($_POST['border_position']) ? implode(',', array_map('sanitize_text_field', $_POST['border_position'])) : $template->border_position;
                $placeholder_icon_color = isset($_POST['placeholder_icon_color']) ? sanitize_hex_color($_POST['placeholder_icon_color']) : $template->placeholder_icon_color;
                $hover_placeholder_icon_color = isset($_POST['hover_placeholder_icon_color']) ? sanitize_hex_color($_POST['hover_placeholder_icon_color']) : $template->hover_placeholder_icon_color;
                $multi_field_row_enabled = isset($_POST['multi_field_row_enabled']) ? 1 : $template->multi_field_row_enabled;
                $multi_field_row_fields = $multi_field_row_enabled && isset($_POST['multi_field_row_fields']) ? json_encode(array_map('intval', $_POST['multi_field_row_fields'])) : $template->multi_field_row_fields;
                $display_border = isset($_POST['display_border']) ? 1 : $template->display_border;

                if (empty($title)) {
                    $error_message = 'Template title is required.';
                } elseif (!$template_color || !$text_color || !$input_color || !$hover_color || !$placeholder_icon_color || !$hover_placeholder_icon_color) {
                    $error_message = 'All color fields must be valid hex colors.';
                } elseif ($multi_field_row_enabled && empty($multi_field_row_fields)) {
                    $error_message = 'At least one field must be selected for multi-field row.';
                } else {
                    $css = $this->generate_template_css($template_color, $text_color, $input_color, $text_font, $hover_effect, $hover_color, $animation, $submit_button_width, $submit_button_position, $border_enabled, $border_radius, $border_position, $placeholder_icon_color, $hover_placeholder_icon_color, $border_type, $display_border);

                    $result = $wpdb->update(
                        $templates_table,
                        [
                            'title' => $title,
                            'css' => $css['css'],
                            'template_color' => $template_color,
                            'text_color' => $text_color,
                            'input_color' => $input_color,
                            'text_font' => $text_font,
                            'hover_effect' => $hover_effect,
                            'hover_color' => $hover_color,
                            'animation' => $animation,
                            'submit_button_width' => $submit_button_width,
                            'submit_button_position' => $submit_button_position,
                            'border_enabled' => $border_enabled,
                            'border_type' => $border_type,
                            'border_radius' => $border_radius,
                            'border_position' => $border_position,
                            'placeholder_icon_color' => $placeholder_icon_color,
                            'hover_placeholder_icon_color' => $hover_placeholder_icon_color,
                            'multi_field_row_enabled' => $multi_field_row_enabled,
                            'multi_field_row_fields' => $multi_field_row_fields,
                            'display_border' => $display_border
                        ],
                        ['id' => $template_id],
                        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d'],
                        ['%d']
                    );

                    if ($result !== false) {
                        $success_message = 'Template updated successfully!';
                    } else {
                        $error_message = 'Failed to update template: ' . $wpdb->last_error;
                        error_log('DCFM: Database error during template update: ' . $wpdb->last_error);
                    }
                }
            }
        }
    } else {
        $error_message = 'Invalid request or security check failed.';
    }

    if ($error_message) {
        echo '<div class="error"><p>' . esc_html($error_message) . '</p></div>';
    }
    if ($success_message) {
        echo '<div class="updated"><p>' . esc_html($success_message) . '</p></div>';
    }

    $template = isset($template_id) && $template ? $template : null;
    $fields = $wpdb->get_results("SELECT * FROM $fields_table ORDER BY created_at ASC");
    ?>
    <div class="wrap">
        <h1>Edit Template</h1>
        <?php if ($template): ?>
            <form method="post" id="edit-template-form">
                <?php wp_nonce_field('template_actions', 'dcfm_template_nonce'); ?>
                <input type="hidden" name="template_id" value="<?php echo esc_attr($template->id); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="template_title">Template Title</label></th>
                        <td>
                            <input type="text" name="template_title" id="template_title" class="regular-text" value="<?php echo esc_attr($template->title); ?>" required>
                            <p class="description">Unique name for the template.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_color">Template Color</label></th>
                        <td>
                            <input type="color" name="template_color" id="template_color" value="<?php echo esc_attr($template->template_color); ?>">
                            <p class="description">Main color for buttons, borders, and CAPTCHA background.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="text_color">Text Color</label></th>
                        <td>
                            <input type="color" name="text_color" id="text_color" value="<?php echo esc_attr($template->text_color); ?>">
                            <p class="description">Color for form text and inputs.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="input_color">Input Field Background</label></th>
                        <td>
                            <input type="color" name="input_color" id="input_color" value="<?php echo esc_attr($template->input_color); ?>">
                            <p class="description">Background color for input fields.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="placeholder_icon_color">Placeholder & Icon Color</label></th>
                        <td>
                            <input type="color" name="placeholder_icon_color" id="placeholder_icon_color" value="<?php echo esc_attr($template->placeholder_icon_color); ?>">
                            <p class="description">Color for placeholder text and input icons.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hover_placeholder_icon_color">Hover Placeholder & Icon Color</label></th>
                        <td>
                            <input type="color" name="hover_placeholder_icon_color" id="hover_placeholder_icon_color" value="<?php echo esc_attr($template->hover_placeholder_icon_color); ?>">
                            <p class="description">Color for placeholder text and input icons on hover.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="text_font">Font</label></th>
                        <td>
                            <select name="text_font" id="text_font">
                                <option value="Arial" <?php selected($template->text_font, 'Arial'); ?>>Arial</option>
                                <option value="Helvetica" <?php selected($template->text_font, 'Helvetica'); ?>>Helvetica</option>
                                <option value="Times New Roman" <?php selected($template->text_font, 'Times New Roman'); ?>>Times New Roman</option>
                                <option value="Georgia" <?php selected($template->text_font, 'Georgia'); ?>>Georgia</option>
                                <option value="Verdana" <?php selected($template->text_font, 'Verdana'); ?>>Verdana</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hover_effect">Hover Effect</label></th>
                        <td>
                            <select name="hover_effect" id="hover_effect">
                                <option value="scale" <?php selected($template->hover_effect, 'scale'); ?>>Scale</option>
                                <option value="shadow" <?php selected($template->hover_effect, 'shadow'); ?>>Shadow</option>
                                <option value="opacity" <?php selected($template->hover_effect, 'opacity'); ?>>Opacity</option>
                                <option value="border-pulse" <?php selected($template->hover_effect, 'border-pulse'); ?>>Border Pulse</option>
                                <option value="glow" <?php selected($template->hover_effect, 'glow'); ?>>Glow</option>
                                <option value="underline" <?php selected($template->hover_effect, 'underline'); ?>>Underline</option>
                                <option value="rotate" <?php selected($template->hover_effect, 'rotate'); ?>>Rotate</option>
                                <option value="skew" <?php selected($template->hover_effect, 'skew'); ?>>Skew</option>
                                <option value="flip" <?php selected($template->hover_effect, 'flip'); ?>>Flip</option>
                                <option value="bounce" <?php selected($template->hover_effect, 'bounce'); ?>>Bounce</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hover_color">Hover Color</label></th>
                        <td>
                            <input type="color" name="hover_color" id="hover_color" value="<?php echo esc_attr($template->hover_color); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="animation">Animation</label></th>
                        <td>
                            <select name="animation" id="animation">
                                <option value="fadeIn" <?php selected($template->animation, 'fadeIn'); ?>>Fade In</option>
                                <option value="slideIn" <?php selected($template->animation, 'slideIn'); ?>>Slide In</option>
                                <option value="bounce" <?php selected($template->animation, 'bounce'); ?>>Bounce</option>
                                <option value="zoomIn" <?php selected($template->animation, 'zoomIn'); ?>>Zoom In</option>
                                <option value="pulse" <?php selected($template->animation, 'pulse'); ?>>Pulse</option>
                                <option value="rotateIn" <?php selected($template->animation, 'rotateIn'); ?>>Rotate In</option>
                                <option value="flipIn" <?php selected($template->animation, 'flipIn'); ?>>Flip In</option>
                                <option value="swing" <?php selected($template->animation, 'swing'); ?>>Swing</option>
                                <option value="slideUp" <?php selected($template->animation, 'slideUp'); ?>>Slide Up</option>
                                <option value="shake" <?php selected($template->animation, 'shake'); ?>>Shake</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="submit_button_width">Submit Button Width</label></th>
                        <td>
                            <select name="submit_button_width" id="submit_button_width">
                                <option value="max-width" <?php selected($template->submit_button_width, 'max-width'); ?>>Max Width</option>
                                <option value="full-width" <?php selected($template->submit_button_width, 'full-width'); ?>>Full Width</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="submit-button-position-config" style="display: <?php echo $template->submit_button_width === 'max-width' ? 'table-row' : 'none'; ?>;">
                        <th><label for="submit_button_position">Submit Button Position</label></th>
                        <td>
                            <select name="submit_button_position" id="submit_button_position">
                                <option value="left" <?php selected($template->submit_button_position, 'left'); ?>>Left</option>
                                <option value="middle" <?php selected($template->submit_button_position, 'middle'); ?>>Middle</option>
                                <option value="right" <?php selected($template->submit_button_position, 'right'); ?>>Right</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="border_enabled">Enable Border</label></th>
                        <td>
                            <input type="checkbox" name="border_enabled" id="border_enabled" value="1" <?php checked($template->border_enabled, 1); ?>>
                            <p class="description">Check to enable border for the form wrapper.</p>
                        </td>
                    </tr>
                    <tr id="border-type-config">
                        <th><label for="border_type">Border Type</label></th>
                        <td>
                            <select name="border_type" id="border_type">
                                <option value="solid" <?php selected($template->border_type, 'solid'); ?>>Solid</option>
                                <option value="dashed" <?php selected($template->border_type, 'dashed'); ?>>Dashed</option>
                                <option value="dotted" <?php selected($template->border_type, 'dotted'); ?>>Dotted</option>
                                <option value="double" <?php selected($template->border_type, 'double'); ?>>Double</option>
                            </select>
                            <p class="description">Select the type of border for the form wrapper and inputs.</p>
                        </td>
                    </tr>
                    <tr id="border-radius-config">
                        <th><label for="border_radius">Border Radius</label></th>
                        <td>
                            <input type="text" name="border_radius" id="border_radius" class="regular-text" value="<?php echo esc_attr($template->border_radius); ?>">
                            <p class="description">Enter the border radius (e.g., 4px, 10px).</p>
                        </td>
                    </tr>
                    <tr id="border-position-config">
                        <th><label>Border Position</label></th>
                        <td>
                            <label><input type="checkbox" name="border_position[]" value="top" <?php checked(in_array('top', explode(',', $template->border_position)) || $template->border_position === 'all'); ?>> Top</label><br>
                            <label><input type="checkbox" name="border_position[]" value="right" <?php checked(in_array('right', explode(',', $template->border_position)) || $template->border_position === 'all'); ?>> Right</label><br>
                            <label><input type="checkbox" name="border_position[]" value="bottom" <?php checked(in_array('bottom', explode(',', $template->border_position)) || $template->border_position === 'all'); ?>> Bottom</label><br>
                            <label><input type="checkbox" name="border_position[]" value="left" <?php checked(in_array('left', explode(',', $template->border_position)) || $template->border_position === 'all'); ?>> Left</label><br>
                            <label><input type="checkbox" name="border_position[]" value="all" <?php checked($template->border_position === 'all'); ?>> All</label><br>
                            <p class="description">Select the border positions to display (select 'All' for all sides).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="display_border">Display Border for Full Form</label></th>
                        <td>
                            <input type="checkbox" name="display_border" id="display_border" value="1" <?php checked($template->display_border, 1); ?>>
                            <p class="description">Check to display the outer border for the full form wrapper.</p>
                        </td>
                    </tr>
                    <tr id="multi-field-row-config" style="display: <?php echo $template->multi_field_row_enabled ? 'table-row' : 'none'; ?>;">
                        <th><label>Multi-Field Row Fields</label></th>
                        <td>
                            <?php 
                            $selected_fields = $template->multi_field_row_fields ? json_decode($template->multi_field_row_fields, true) : [];
                            foreach ($fields as $field): 
                            ?>
                                <label>
                                    <input type="checkbox" name="multi_field_row_fields[]" value="<?php echo esc_attr($field->id); ?>" <?php checked(in_array($field->id, $selected_fields)); ?>>
                                    <?php echo esc_html(ucfirst($field->field_name)); ?>
                                </label><br>
                            <?php endforeach; ?>
                            <p class="description">Select fields to include in the multi-field row.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="save_template" class="button button-primary" value="Save Template">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dcfm-templates')); ?>" class="button" id="cancel-button">Cancel</a>
                </p>
            </form>
        <?php else: ?>
            <p>No template selected for editing.</p>
        <?php endif; ?>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('#multi_field_row_enabled').change(function() {
                $('#multi-field-row-config').toggle(this.checked);
            });
            $('#submit_button_width').change(function() {
                $('#submit-button-position-config').toggle($(this).val() === 'max-width');
            });

            // Ensure cancel button works
            $('#cancel-button').on('click', function(e) {
                e.preventDefault();
                window.location.href = '<?php echo esc_url(admin_url('admin.php?page=dcfm-templates')); ?>';
            });
        });
    </script>
    <?php
}
public function handle_quick_edit_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dcfm_ajax_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
        wp_die();
    }

    global $wpdb;
    $templates_table = $wpdb->prefix . 'dcfm_templates';
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if ($template_id <= 0) {
        wp_send_json_error(['message' => 'Invalid template ID']);
        wp_die();
    }

    $template_color = isset($_POST['template_color']) ? sanitize_hex_color($_POST['template_color']) : '#2271b1';
    $text_color = isset($_POST['text_color']) ? sanitize_hex_color($_POST['text_color']) : '#333333';
    $input_color = isset($_POST['input_color']) ? sanitize_hex_color($_POST['input_color']) : '#ffffff';
    $text_font = isset($_POST['text_font']) ? sanitize_text_field($_POST['text_font']) : 'Arial';
    $hover_effect = isset($_POST['hover_effect']) ? sanitize_text_field($_POST['hover_effect']) : 'scale';
    $hover_color = isset($_POST['hover_color']) ? sanitize_hex_color($_POST['hover_color']) : '#1a5d93';
    $animation = isset($_POST['animation']) ? sanitize_text_field($_POST['animation']) : 'fadeIn';
    $submit_button_width = isset($_POST['submit_button_width']) ? sanitize_text_field($_POST['submit_button_width']) : 'full-width';
    $submit_button_position = ($submit_button_width === 'max-width' && isset($_POST['submit_button_position'])) ? sanitize_text_field($_POST['submit_button_position']) : 'middle';
    $border_enabled = isset($_POST['border_enabled']) ? intval($_POST['border_enabled']) : 0;
    $border_type = isset($_POST['border_type']) ? sanitize_text_field($_POST['border_type']) : 'solid';
    $border_radius = isset($_POST['border_radius']) ? sanitize_text_field($_POST['border_radius']) : '4px';
    $border_position = isset($_POST['border_position']) ? sanitize_text_field($_POST['border_position']) : 'all';
    $placeholder_icon_color = isset($_POST['placeholder_icon_color']) ? sanitize_hex_color($_POST['placeholder_icon_color']) : '#999999';
    $hover_placeholder_icon_color = isset($_POST['hover_placeholder_icon_color']) ? sanitize_hex_color($_POST['hover_placeholder_icon_color']) : '#666666';
    $display_border = isset($_POST['display_border']) ? intval($_POST['display_border']) : 1;

    $styles = $this->generate_template_css($template_color, $text_color, $input_color, $text_font, $hover_effect, $hover_color, $animation, $submit_button_width, $submit_button_position, $border_enabled, $border_radius, $border_position, $placeholder_icon_color, $hover_placeholder_icon_color, $border_type, $display_border);
    $css = $styles['css'];

    $result = $wpdb->update(
        $templates_table,
        [
            'template_color' => $template_color,
            'text_color' => $text_color,
            'input_color' => $input_color,
            'text_font' => $text_font,
            'hover_effect' => $hover_effect,
            'hover_color' => $hover_color,
            'animation' => $animation,
            'submit_button_width' => $submit_button_width,
            'submit_button_position' => $submit_button_position,
            'border_enabled' => $border_enabled,
            'border_type' => $border_type,
            'border_radius' => $border_radius,
            'border_position' => $border_position,
            'placeholder_icon_color' => $placeholder_icon_color,
            'hover_placeholder_icon_color' => $hover_placeholder_icon_color,
            'display_border' => $display_border,
            'css' => $css
        ],
        ['id' => $template_id],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s'],
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Template updated successfully']);
    } else {
        error_log('DCFM: Failed to update template ID ' . $template_id . ': ' . $wpdb->last_error);
        wp_send_json_error(['message' => 'Failed to update template: ' . $wpdb->last_error]);
    }

    wp_die();
}
private function generate_template_css(
    $template_color, 
    $text_color, 
    $input_color, 
    $text_font, 
    $hover_effect, 
    $hover_color, 
    $animation, 
    $submit_button_width, 
    $submit_button_position, 
    $border_enabled, 
    $border_radius, 
    $border_position = 'all',
    $placeholder_icon_color = '#999999',
    $hover_placeholder_icon_color = '#666666',
    $border_type = 'solid',
    $display_border = 1
) {
    // Log inputs for debugging
    error_log('DCFM: generate_template_css inputs: ' . print_r([
        'template_color' => $template_color,
        'text_color' => $text_color,
        'input_color' => $input_color,
        'text_font' => $text_font,
        'hover_effect' => $hover_effect,
        'hover_color' => $hover_color,
        'animation' => $animation,
        'submit_button_width' => $submit_button_width,
        'submit_button_position' => $submit_button_position,
        'border_enabled' => $border_enabled,
        'border_radius' => $border_radius,
        'border_position' => $border_position,
        'placeholder_icon_color' => $placeholder_icon_color,
        'hover_placeholder_icon_color' => $hover_placeholder_icon_color,
        'border_type' => $border_type,
        'display_border' => $display_border
    ], true));

    // Base transition for smooth hover effects
    $base_transition = 'transition: all 0.3s ease;';

    // Submit button inline style
    $submit_button_style = "background-color: {$template_color}; border: 1px {$border_type} {$template_color}; color: #ffffff; padding: 10px 20px; cursor: pointer; font-family: {$text_font}, sans-serif; {$base_transition}";
    if ($submit_button_width === 'max-width') {
        $submit_button_style .= " width: 200px; display: block;";
        switch ($submit_button_position) {
            case 'left':
                $submit_button_style .= " margin: 10px 0 10px 0;";
                break;
            case 'right':
                $submit_button_style .= " margin: 10px 0 10px auto;";
                break;
            case 'middle':
                $submit_button_style .= " margin: 10px auto;";
                break;
        }
    } else {
        $submit_button_style .= " width: 100%; display: block; margin: 10px 0; box-sizing: border-box;";
    }

    // Input inline style
    $input_style = "border: 1px {$border_type} {$template_color}; background-color: {$input_color}; color: {$text_color}; padding: 8px; margin-bottom: 10px; width: 100%; box-sizing: border-box; font-family: {$text_font}, sans-serif; {$base_transition}";
    $input_style .= " --placeholder-icon-color: {$placeholder_icon_color};";
    $input_style .= " color: {$placeholder_icon_color};";

    // CAPTCHA reset button inline style
    $captcha_reset_style = "background-color: {$input_color}; border: 1px {$border_type} {$template_color}; color: {$text_color}; padding: 5px 10px; cursor: pointer; font-family: {$text_font}, sans-serif; {$base_transition}";

    // CAPTCHA display inline style
    $captcha_display_style = "background-color: {$template_color}; color: #ffffff; border: 1px {$border_type} {$template_color}; padding: 5px 10px; display: inline-block; font-family: {$text_font}, sans-serif;";

    // Hover inline styles (to be applied via onmouseover/onmouseout)
    $hover_inline = "background-color: {$hover_color};";
    switch ($hover_effect) {
        case 'scale':
            $hover_inline .= " transform: scale(1.05);";
            break;
        case 'shadow':
            $hover_inline .= " box-shadow: 0 4px 8px rgba(0,0,0,0.2);";
            break;
        case 'opacity':
            $hover_inline .= " opacity: 0.8;";
            break;
        case 'border-pulse':
            $hover_inline .= " border-color: {$hover_color}; box-shadow: 0 0 0 3px {$hover_color}66;";
            break;
        case 'glow':
            $hover_inline .= " box-shadow: 0 0 10px {$hover_color}, 0 0 20px {$hover_color}80;";
            break;
        case 'underline':
            $hover_inline .= " position: relative;";
            break;
        case 'rotate':
            $hover_inline .= " transform: rotate(5deg);";
            break;
        case 'skew':
            $hover_inline .= " transform: skew(-10deg);";
            break;
        case 'flip':
            $hover_inline .= " transform: rotateY(180deg);";
            break;
        case 'bounce':
            $hover_inline .= " transform: translateY(-5px);";
            break;
    }

    // Animation styles
    $animation_styles = '';
    switch ($animation) {
        case 'fadeIn':
            $animation_styles = "
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                .dcfm-form-wrapper {
                    animation: fadeIn 0.5s ease-in;
                }";
            break;
        case 'slideIn':
            $animation_styles = "
                @keyframes slideIn {
                    from { transform: translateY(20px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                .dcfm-form-wrapper {
                    animation: slideIn 0.5s ease-in;
                }";
            break;
        case 'bounce':
            $animation_styles = "
                @keyframes bounce {
                    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                    40% { transform: translateY(-10px); }
                    60% { transform: translateY(-5px); }
                }
                .dcfm-form-wrapper {
                    animation: bounce 1s ease;
                }";
            break;
        case 'zoomIn':
            $animation_styles = "
                @keyframes zoomIn {
                    from { transform: scale(0.8); opacity: 0; }
                    to { transform: scale(1); opacity: 1; }
                }
                .dcfm-form-wrapper {
                    animation: zoomIn 0.5s ease-in;
                }";
            break;
        case 'pulse':
            $animation_styles = "
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
                .dcfm-form-wrapper {
                    animation: pulse 1s ease;
                }";
            break;
        case 'rotateIn':
            $animation_styles = "
                @keyframes rotateIn {
                    from { transform: rotate(-90deg); opacity: 0; }
                    to { transform: rotate(0); opacity: 1; }
                }
                .dcfm-form-wrapper {
                    animation: rotateIn 0.5s ease-in;
                }";
            break;
        case 'flipIn':
            $animation_styles = "
                @keyframes flipIn {
                    from { transform: rotateY(-90deg); opacity: 0; }
                    to { transform: rotateY(0); opacity: 1; }
                }
                .dcfm-form-wrapper {
                    animation: flipIn 0.5s ease-in;
                }";
            break;
        case 'swing':
            $animation_styles = "
                @keyframes swing {
                    20% { transform: rotate(15deg); }
                    40% { transform: rotate(-10deg); }
                    60% { transform: rotate(5deg); }
                    80% { transform: rotate(-5deg); }
                    100% { transform: rotate(0); }
                }
                .dcfm-form-wrapper {
                    animation: swing 1s ease;
                }";
            break;
        case 'slideUp':
            $animation_styles = "
                @keyframes slideUp {
                    from { transform: translateY(100%); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                .dcfm-form-wrapper {
                    animation: slideUp 0.5s ease-in;
                }";
            break;
        case 'shake':
            $animation_styles = "
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                    20%, 40%, 60%, 80% { transform: translateX(5px); }
                }
                .dcfm-form-wrapper {
                    animation: shake 0.5s ease;
                }";
            break;
    }

    // Base CSS for form wrapper and layout
    $border_style = '';
    if ($border_enabled && $display_border) {
        $border_positions = explode(',', $border_position);
        $border_styles = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (in_array($side, $border_positions) || $border_position === 'all') {
                $border_styles[] = "border-{$side}: 2px {$border_type} {$template_color};";
            } else {
                $border_styles[] = "border-{$side}: none;";
            }
        }
        $border_style = implode(' ', $border_styles);
        $border_style .= " border-radius: {$border_radius};";
    } else {
        $border_style = "border: none;";
    }

    // Input border style
    $input_border_style = '';
    if ($border_enabled) {
        $border_positions = explode(',', $border_position);
        $input_border_styles = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (in_array($side, $border_positions) || $border_position === 'all') {
                $input_border_styles[] = "border-{$side}: 1px {$border_type} {$template_color};";
            } else {
                $input_border_styles[] = "border-{$side}: none;";
            }
        }
        $input_border_style = implode(' ', $input_border_styles);
        $input_border_style .= " border-radius: {$border_radius};";
    } else {
        $input_border_style = "border: none;";
    }

    // Update input style with border style
    $input_style = str_replace(
        "border: 1px {$border_type} {$template_color};",
        $input_border_style,
        $input_style
    );

    // Update button styles with border type and radius
    $submit_button_style .= " border-radius: {$border_radius};";
    $captcha_reset_style .= " border-radius: {$border_radius};";
    $captcha_display_style .= " border-radius: {$border_radius};";

    $css = "
        .dcfm-form-wrapper {
            color: {$text_color};
            font-family: {$text_font}, sans-serif;
            {$border_style}
            padding: 20px;
            box-sizing: border-box;
        }
        .dcfm-form label {
            color: {$text_color};
            margin-bottom: 5px;
            display: block;
        }
        .dcfm-form .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .dcfm-form .form-field {
            flex: 1;
            min-width: 200px;
        }
        .dcfm-form .form-notification {
            margin-bottom: 20px;
        }
        .dcfm-form .required {
            color: red;
        }
        .dcfm-form input[type='checkbox'],
        .dcfm-form input[type='radio'] {
            margin-right: 5px;
        }
        .dcfm-form .input-wrapper {
            position: relative;
        }
        .dcfm-form .input-icon {
            position: absolute;
            left: 05px;
            top: 45%;
            transform: translateY(-50%);
            color: {$placeholder_icon_color};
        }
        .dcfm-form input::placeholder,
        .dcfm-form textarea::placeholder,
        .dcfm-form select:invalid {
            color: {$placeholder_icon_color};
        }
        .dcfm-form input:hover::placeholder,
        .dcfm-form textarea:hover::placeholder,
        .dcfm-form select:hover:invalid,
        .dcfm-form .input-wrapper:hover .input-icon {
            color: {$hover_placeholder_icon_color};
        }
        {$animation_styles}
    ";

    // Apply !important to CSS rules
    $css = $this->add_important_to_css($css);

    return [
        'css' => $css,
        'submit_button_style' => $submit_button_style,
        'input_style' => $input_style,
        'captcha_reset_style' => $captcha_reset_style,
        'captcha_display_style' => $captcha_display_style,
        'hover_inline' => $hover_inline,
        'hover_effect' => $hover_effect
    ];
}
public function render_form($form_id) {
    global $wpdb;
    $forms_table = $wpdb->prefix . 'dcfm_forms';
    $fields_table = $wpdb->prefix . 'dcfm_fields';
    $templates_table = $wpdb->prefix . 'dcfm_templates';

    $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM $forms_table WHERE id = %d", $form_id));
    if (!$form) {
        return '<p>Form not found.</p>';
    }

    $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $templates_table WHERE id = %d", $form->template_id));
    if (!$template) {
        $template = $wpdb->get_row("SELECT * FROM $templates_table WHERE id = 1");
    }

    $fields = json_decode($form->fields, true);
    $field_rows = $form->field_rows ? json_decode($form->field_rows, true) : [];
    $captcha = '';
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    for ($i = 0; $i < 4; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }

    $styles = $this->generate_template_css(
        $template->template_color,
        $template->text_color,
        $template->input_color,
        $template->text_font,
        $template->hover_effect,
        $template->hover_color,
        $template->animation,
        $template->submit_button_width,
        $template->submit_button_position,
        $template->border_enabled,
        $template->border_radius,
        $template->border_position,
        $template->placeholder_icon_color,
        $template->hover_placeholder_icon_color,
        $template->border_type,
        $template->display_border
    );

    // Reset styles for hover effects
    $reset_style = "background-color: {$template->input_color}; transform: none; box-shadow: none; opacity: 1;";
    $submit_reset_style = "background-color: {$template->template_color}; transform: none; box-shadow: none; opacity: 1;";

    // Apply border radius and position to form wrapper
    $border_style = '';
    if ($template->border_enabled && $template->display_border) {
        $border_positions = $template->border_position === 'all' ? ['top', 'right', 'bottom', 'left'] : explode(',', $template->border_position);
        $border_styles = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $border_styles[] = in_array($side, $border_positions) ? "border-{$side}: 2px {$template->border_type} {$template->template_color};" : "border-{$side}: none;";
        }
        $border_style = implode(' ', $border_styles) . " border-radius: {$template->border_radius};";
    } else {
        $border_style = "border: none; border-radius: 0;";
    }

    // Apply border position to input fields
    $input_border_style = '';
    if ($template->border_enabled) {
        $border_positions = $template->border_position === 'all' ? ['top', 'right', 'bottom', 'left'] : explode(',', $template->border_position);
        $input_border_styles = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $input_border_styles[] = in_array($side, $border_positions) ? "border-{$side}: 1px {$template->border_type} {$template->template_color};" : "border-{$side}: none;";
        }
        $input_border_style = implode(' ', $input_border_styles) . " border-radius: {$template->border_radius};";
    } else {
        $input_border_style = "border: none; border-radius: 0;";
    }

    // Update styles with border radius and position for inputs and buttons
    $styles['input_style'] = str_replace(
        "border: 1px {$template->border_type} {$template->template_color};",
        $input_border_style,
        $styles['input_style']
    );
    $styles['submit_button_style'] .= " border-radius: {$template->border_radius};";
    $styles['captcha_reset_style'] .= " border-radius: {$template->border_radius};";
    $styles['captcha_display_style'] .= " border-radius: {$template->border_radius};";

    ob_start();
    ?>
    <div class="dcfm-form-wrapper" style="<?php echo esc_attr($border_style); ?>">
        <style><?php echo esc_html($styles['css']); ?></style>
        <div class="form-notification" style="display: none;"></div>
        <form id="dcfm-form-<?php echo esc_attr($form_id); ?>" class="dcfm-form" data-form-id="<?php echo esc_attr($form_id); ?>">
            <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
            <input type="hidden" id="captcha_answer-<?php echo esc_attr($form_id); ?>" name="captcha_answer" value="<?php echo esc_attr($captcha); ?>">

            <?php if (!empty($field_rows)): ?>
                <?php foreach ($field_rows as $row): ?>
                    <div class="form-row">
                        <?php foreach ($row as $field_id): ?>
                            <?php
                            $field = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fields_table WHERE id = %d", $field_id));
                            if ($field) {
                                $this->render_field($field, $styles);
                            }
                            ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($fields as $field_id): ?>
                    <div class="form-row">
                        <?php
                        $field = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fields_table WHERE id = %d", $field_id));
                        if ($field) {
                            $this->render_field($field, $styles);
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="form-field captcha-field">
                <label for="captcha_input_<?php echo esc_attr($form_id); ?>">CAPTCHA <span class="required">*</span></label>
                <div class="captcha-wrapper">
                    <span id="captcha-display-<?php echo esc_attr($form_id); ?>" class="captcha-display" style="<?php echo esc_attr($styles['captcha_display_style']); ?>"><?php echo esc_html($captcha); ?></span>
                    <button type="button" class="captcha-reset btn btn-secondary" data-form-id="<?php echo esc_attr($form_id); ?>" style="<?php echo esc_attr($styles['captcha_reset_style']); ?>" onmouseover="this.style='<?php echo esc_attr($styles['captcha_reset_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" onmouseout="this.style='<?php echo esc_attr($styles['captcha_reset_style']); ?>'; this.classList.remove('underline-hover');">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <input type="text" id="captcha_input_<?php echo esc_attr($form_id); ?>" name="captcha_input" class="captcha-input" placeholder="Enter CAPTCHA" style="<?php echo esc_attr($styles['input_style']); ?>" onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');" required>
            </div>

            <button type="submit" class="submit-button btn btn-primary" style="<?php echo esc_attr($styles['submit_button_style']); ?>" onmouseover="this.style='<?php echo esc_attr($styles['submit_button_style'] . " background-color: {$template->hover_color}; border-color: {$template->hover_color};"); ?>'" onmouseout="this.style='<?php echo esc_attr($styles['submit_button_style']); ?>';"><?php echo esc_html($form->submit_button_text); ?></button>
        </form>
        <?php if ($styles['hover_effect'] === 'underline'): ?>
        <style>
            .underline-hover::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 2px;
                background-color: <?php echo esc_attr($template->hover_color); ?>;
                transition: all 0.3s ease;
            }
        </style>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

private function render_sample_form_for_preview($template = null) {
    global $wpdb;
    $fields = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dcfm_fields WHERE field_name IN ('name', 'email')");
    $captcha = '';
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    for ($i = 0; $i < 4; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    $multi_field_row_enabled = $template && $template->multi_field_row_enabled;
    $multi_field_row_fields = $template && $template->multi_field_row_fields ? json_decode($template->multi_field_row_fields, true) : [];

    $styles = $template ? $this->generate_template_css(
        $template->template_color,
        $template->text_color,
        $template->input_color,
        $template->text_font,
        $template->hover_effect,
        $template->hover_color,
        $template->animation,
        $template->submit_button_width,
        $template->submit_button_position,
        $template->border_enabled,
        $template->border_radius,
        $template->border_position,
        $template->placeholder_icon_color,
        $template->hover_placeholder_icon_color,
        $template->border_type,
        $template->display_border
    ) : [
        'css' => '',
        'submit_button_style' => '',
        'input_style' => '',
        'captcha_reset_style' => '',
        'captcha_display_style' => '',
        'hover_inline' => '',
        'hover_effect' => ''
    ];

    // Reset styles for hover effects
    $reset_style = $template ? "background-color: {$template->input_color}; transform: none; box-shadow: none; opacity: 1;" : '';
    $submit_reset_style = $template ? "background-color: {$template->template_color}; transform: none; box-shadow: none; opacity: 1;" : '';

    // Apply border radius and position to form wrapper
    $border_style = '';
    if ($template && $template->border_enabled && $template->display_border) {
        $border_positions = $template->border_position === 'all' ? ['top', 'right', 'bottom', 'left'] : explode(',', $template->border_position);
        $border_styles = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $border_styles[] = in_array($side, $border_positions) ? "border-{$side}: 2px {$template->border_type} {$template->template_color};" : "border-{$side}: none;";
        }
        $border_style = implode(' ', $border_styles) . " border-radius: {$template->border_radius};";
    } else {
        $border_style = "border: none; border-radius: 0;";
    }

    // Apply border position to input fields
    $input_border_style = '';
    if ($template && $template->border_enabled) {
        $border_positions = $template->border_position === 'all' ? ['top', 'right', 'bottom', 'left'] : explode(',', $template->border_position);
        $input_border_styles = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $input_border_styles[] = in_array($side, $border_positions) ? "border-{$side}: 1px {$template->border_type} {$template->template_color};" : "border-{$side}: none;";
        }
        $input_border_style = implode(' ', $input_border_styles) . " border-radius: {$template->border_radius};";
    } else {
        $input_border_style = "border: none; border-radius: 0;";
    }

    // Update styles with border radius and position for inputs and buttons
    if ($template) {
        $styles['input_style'] = str_replace(
            "border: 1px {$template->border_type} {$template->template_color};",
            $input_border_style,
            $styles['input_style']
        );
        $styles['submit_button_style'] .= " border-radius: {$template->border_radius};";
        $styles['captcha_reset_style'] .= " border-radius: {$template->border_radius};";
        $styles['captcha_display_style'] .= " border-radius: {$template->border_radius};";
    }

    error_log('DCFM: render_sample_form_for_preview styles: ' . print_r($styles, true));

    ob_start();
    ?>
    <div class="dcfm-form-wrapper" style="<?php echo esc_attr($border_style); ?>">
        <style scoped><?php echo esc_html($styles['css']); ?></style>
        <form class="dcfm-form">
            <?php if ($multi_field_row_enabled && !empty($multi_field_row_fields)): ?>
                <div class="form-row">
                    <?php foreach ($multi_field_row_fields as $field_id): ?>
                        <?php
                        $field = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dcfm_fields WHERE id = %d", $field_id));
                        if ($field):
                        ?>
                            <div class="form-field">
                                <label for="<?php echo esc_attr($field->field_name); ?>">
                                    <?php echo esc_html(ucfirst($field->field_name)); ?>
                                    <?php if ($field->is_required): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </label>
                                <?php if ($field->field_type === 'email'): ?>
                                    <input type="email" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Enter your <?php echo esc_attr($field->field_name); ?>" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php elseif ($field->field_type === 'date'): ?>
                                    <input type="date" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Select a date" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php elseif ($field->field_type === 'time'): ?>
                                    <input type="time" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Select a time" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php else: ?>
                                    <input type="text" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Enter your <?php echo esc_attr($field->field_name); ?>" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php
                $remaining_fields = array_filter($fields, function($f) use ($multi_field_row_fields) {
                    return !in_array($f->id, $multi_field_row_fields);
                });
                if (!empty($remaining_fields)):
                ?>
                    <div class="form-row">
                        <?php foreach ($remaining_fields as $field): ?>
                            <div class="form-field">
                                <label for="<?php echo esc_attr($field->field_name); ?>">
                                    <?php echo esc_html(ucfirst($field->field_name)); ?>
                                    <?php if ($field->is_required): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </label>
                                <?php if ($field->field_type === 'email'): ?>
                                    <input type="email" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Enter your <?php echo esc_attr($field->field_name); ?>" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php elseif ($field->field_type === 'date'): ?>
                                    <input type="date" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Select a date" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php elseif ($field->field_type === 'time'): ?>
                                    <input type="time" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Select a time" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php else: ?>
                                    <input type="text" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Enter your <?php echo esc_attr($field->field_name); ?>" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="form-row">
                    <?php foreach ($fields as $field): ?>
                        <div class="form-field">
                            <label for="<?php echo esc_attr($field->field_name); ?>">
                                <?php echo esc_html(ucfirst($field->field_name)); ?>
                                <?php if ($field->is_required): ?>
                                    <span class="required">*</span>
                                    <?php endif; ?>
                                </label>
                                <?php if ($field->field_type === 'email'): ?>
                                    <input type="email" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Enter your <?php echo esc_attr($field->field_name); ?>" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php elseif ($field->field_type === 'date'): ?>
                                    <input type="date" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Select a date" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php elseif ($field->field_type === 'time'): ?>
                                    <input type="time" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Select a time" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php else: ?>
                                    <input type="text" 
                                           name="<?php echo esc_attr($field->field_name); ?>" 
                                           placeholder="Enter your <?php echo esc_attr($field->field_name); ?>" 
                                           style="<?php echo esc_attr($styles['input_style']); ?>" 
                                           onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                                           onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="form-field captcha-field">
                    <label for="captcha_input_preview">CAPTCHA <span class="required">*</span></label>
                    <div class="captcha-wrapper">
                        <span id="captcha-display-preview" class="captcha-display" style="<?php echo esc_attr($styles['captcha_display_style']); ?>"><?php echo esc_html($captcha); ?></span>
                        <button type="button" class="captcha-reset btn btn-secondary" data-form-id="preview" style="<?php echo esc_attr($styles['captcha_reset_style']); ?>" onmouseover="this.style='<?php echo esc_attr($styles['captcha_reset_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" onmouseout="this.style='<?php echo esc_attr($styles['captcha_reset_style']); ?>'; this.classList.remove('underline-hover');">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <input type="text" id="captcha_input_preview" name="captcha_input" class="captcha-input" placeholder="Enter CAPTCHA" style="<?php echo esc_attr($styles['input_style']); ?>" onmouseover="this.style='<?php echo esc_attr($styles['input_style'] . $styles['hover_inline']); ?>'<?php if ($styles['hover_effect'] === 'underline') echo " this.classList.add('underline-hover');"; ?>" onmouseout="this.style='<?php echo esc_attr($styles['input_style']); ?>'; this.classList.remove('underline-hover');" required>
                </div>
                <button type="submit" class="submit-button btn btn-primary" style="<?php echo esc_attr($styles['submit_button_style']); ?>" onmouseover="this.style='<?php echo esc_attr($styles['submit_button_style'] . " background-color: {$template->hover_color}; border-color: {$template->hover_color};"); ?>'" onmouseout="this.style='<?php echo esc_attr($styles['submit_button_style']); ?>';"><?php echo $template ? 'Submit' : 'Submit'; ?></button>
            </form>
            <?php if ($styles['hover_effect'] === 'underline'): ?>
            <style>
                .underline-hover::after {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    width: 100%;
                    height: 2px;
                    background-color: <?php echo esc_attr($template->hover_color); ?>;
                    transition: all 0.3s ease;
                }
            </style>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }


// kjhvghjopijhg

public function check_and_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $fields_table = $wpdb->prefix . 'dcfm_fields';
    $forms_table = $wpdb->prefix . 'dcfm_forms';
    $submissions_table = $wpdb->prefix . 'dcfm_submissions';
    $templates_table = $wpdb->prefix . 'dcfm_templates';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Create or update fields table
    $sql_fields = "CREATE TABLE $fields_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        field_name varchar(100) NOT NULL,
        field_type varchar(50) NOT NULL,
        is_required tinyint(1) DEFAULT 0,
        options text DEFAULT NULL,
        placeholder varchar(255) DEFAULT NULL,
        placeholder_text varchar(255) DEFAULT NULL,
        show_label tinyint(1) DEFAULT 1,
        hyperlink varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    dbDelta($sql_fields);

    // Check if placeholder column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $fields_table LIKE 'placeholder'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $fields_table ADD placeholder varchar(255) DEFAULT NULL AFTER options");
        error_log('DCFM: Added placeholder column to dcfm_fields table');
    }

    // Check if placeholder_text column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $fields_table LIKE 'placeholder_text'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $fields_table ADD placeholder_text varchar(255) DEFAULT NULL AFTER placeholder");
        error_log('DCFM: Added placeholder_text column to dcfm_fields table');
    }

    // Check if show_label column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $fields_table LIKE 'show_label'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $fields_table ADD show_label tinyint(1) DEFAULT 1 AFTER placeholder_text");
        error_log('DCFM: Added show_label column to dcfm_fields table');
    }

    // Check if hyperlink column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $fields_table LIKE 'hyperlink'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $fields_table ADD hyperlink varchar(255) DEFAULT NULL AFTER show_label");
        error_log('DCFM: Added hyperlink column to dcfm_fields table');
    }

    // Create or update forms table
    $sql_forms = "CREATE TABLE $forms_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(200) NOT NULL,
        fields text NOT NULL,
        field_rows text DEFAULT NULL,
        shortcode varchar(100) NOT NULL,
        submit_button_text varchar(100) DEFAULT 'Submit',
        template_id mediumint(9) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    dbDelta($sql_forms);

    // Check if submit_button_text column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $forms_table LIKE 'submit_button_text'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $forms_table ADD submit_button_text varchar(100) DEFAULT 'Submit' AFTER shortcode");
        error_log('DCFM: Added submit_button_text column to dcfm_forms table');
    }

    // Check if template_id column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $forms_table LIKE 'template_id'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $forms_table ADD template_id mediumint(9) DEFAULT 1 AFTER submit_button_text");
        error_log('DCFM: Added template_id column to dcfm_forms table');
    }

    // Check if field_rows column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $forms_table LIKE 'field_rows'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $forms_table ADD field_rows text DEFAULT NULL AFTER fields");
        error_log('DCFM: Added field_rows column to dcfm_forms table');
    }

    // Create submissions table
    $sql_submissions = "CREATE TABLE $submissions_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        form_id mediumint(9) NOT NULL,
        submission_data text NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    dbDelta($sql_submissions);

    // Create templates table
    $sql_templates = "CREATE TABLE $templates_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(200) NOT NULL,
        css text NOT NULL,
        template_color varchar(7) NOT NULL,
        text_color varchar(7) NOT NULL,
        input_color varchar(7) NOT NULL,
        text_font varchar(100) NOT NULL,
        hover_effect varchar(50) NOT NULL,
        hover_color varchar(7) NOT NULL,
        animation varchar(50) NOT NULL,
        submit_button_width varchar(50) DEFAULT 'max-width',
        submit_button_position varchar(50) DEFAULT 'middle',
        border_enabled tinyint(1) DEFAULT 0,
        border_type varchar(20) DEFAULT 'solid',
        border_radius varchar(20) DEFAULT '4px',
        border_position varchar(100) DEFAULT 'all',
        placeholder_icon_color varchar(7) DEFAULT '#999999',
        hover_placeholder_icon_color varchar(7) DEFAULT '#666666',
        multi_field_row_enabled tinyint(1) DEFAULT 0,
        multi_field_row_fields text DEFAULT NULL,
        display_border tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    dbDelta($sql_templates);

    // Check if submit_button_width column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'submit_button_width'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD submit_button_width varchar(50) DEFAULT 'max-width' AFTER animation");
        error_log('DCFM: Added submit_button_width column to dcfm_templates table');
    }

    // Check if submit_button_position column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'submit_button_position'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD submit_button_position varchar(50) DEFAULT 'middle' AFTER submit_button_width");
        error_log('DCFM: Added submit_button_position column to dcfm_templates table');
    }

    // Check if border_enabled column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'border_enabled'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD border_enabled TINYINT(1) DEFAULT 0 AFTER submit_button_position");
        error_log('DCFM: Added border_enabled column to dcfm_templates table');
    }

    // Check if border_type column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'border_type'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD border_type varchar(20) DEFAULT 'solid' AFTER border_enabled");
        error_log('DCFM: Added border_type column to dcfm_templates table');
    }

    // Check if border_radius column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'border_radius'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD border_radius varchar(20) DEFAULT '4px' AFTER border_type");
        error_log('DCFM: Added border_radius column to dcfm_templates table');
    }

    // Check if border_position column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'border_position'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD border_position varchar(100) DEFAULT 'all' AFTER border_radius");
        error_log('DCFM: Added border_position column to dcfm_templates table');
    }

    // Check if placeholder_icon_color column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'placeholder_icon_color'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD placeholder_icon_color varchar(7) DEFAULT '#999999' AFTER border_position");
        error_log('DCFM: Added placeholder_icon_color column to dcfm_templates table');
    }

    // Check if hover_placeholder_icon_color column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'hover_placeholder_icon_color'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD hover_placeholder_icon_color varchar(7) DEFAULT '#666666' AFTER placeholder_icon_color");
        error_log('DCFM: Added hover_placeholder_icon_color column to dcfm_templates table');
    }

    // Check if multi_field_row_enabled column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'multi_field_row_enabled'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD multi_field_row_enabled TINYINT(1) DEFAULT 0 AFTER hover_placeholder_icon_color");
        error_log('DCFM: Added multi_field_row_enabled column to dcfm_templates table');
    }

    // Check if multi_field_row_fields column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'multi_field_row_fields'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD multi_field_row_fields text DEFAULT NULL AFTER multi_field_row_enabled");
        error_log('DCFM: Added multi_field_row_fields column to dcfm_templates table');
    }

    // Check if display_border column exists, and add it if missing
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $templates_table LIKE 'display_border'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $templates_table ADD display_border TINYINT(1) DEFAULT 1 AFTER multi_field_row_fields");
        error_log('DCFM: Added display_border column to dcfm_templates table');
    }

    // Insert default email field if no fields exist
    $field_count = $wpdb->get_var("SELECT COUNT(*) FROM $fields_table");
    if ($field_count == NULL) {
        $wpdb->insert(
            $fields_table,
            [
                'field_name' => 'email',
                'field_type' => 'email',
                'is_required' => 1,
                'show_label' => 1,
                'placeholder_text' => 'Enter your email'
            ],
            ['%s', '%s', '%d', '%d', '%s']
        );
        error_log('DCFM: Inserted default email field');
    }

    // Insert default template if no templates exist
    $template_count = $wpdb->get_var("SELECT COUNT(*) FROM $templates_table");
    if ($template_count == 0) {
        $default_styles = $this->generate_template_css(
            '#2271b1', // template_color
            '#333333', // text_color
            '#ffffff', // input_color
            'Arial',   // text_font
            'scale',   // hover_effect
            '#1a5d93', // hover_color
            'fadeIn',  // animation
            'max-width', // submit_button_width
            'middle',  // submit_button_position
            0,         // border_enabled
            '4px',     // border_radius
            'all',     // border_position
            '#999999', // placeholder_icon_color
            '#666666', // hover_placeholder_icon_color
            'solid',   // border_type
            1          // display_border
        );

        $wpdb->insert(
            $templates_table,
            [
                'title' => 'Default Template',
                'css' => $default_styles['css'],
                'template_color' => '#2271b1',
                'text_color' => '#333333',
                'input_color' => '#ffffff',
                'text_font' => 'Arial',
                'hover_effect' => 'scale',
                'hover_color' => '#1a5d93',
                'animation' => 'fadeIn',
                'submit_button_width' => 'max-width',
                'submit_button_position' => 'middle',
                'border_enabled' => 0,
                'border_type' => 'solid',
                'border_radius' => '4px',
                'border_position' => 'all',
                'placeholder_icon_color' => '#999999',
                'hover_placeholder_icon_color' => '#666666',
                'multi_field_row_enabled' => 0,
                'multi_field_row_fields' => NULL,
                'display_border' => 1
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d']
        );
        error_log('DCFM: Inserted default template');
    }

    // Log any database errors
    if ($wpdb->last_error) {
        error_log('DCFM: Database error during table creation: ' . $wpdb->last_error);
    }
}

public function render_templates_page() {
    global $wpdb;
    $templates_table = $wpdb->prefix . 'dcfm_templates';
    $fields_table = $wpdb->prefix . 'dcfm_fields';
    
    // Initialize error message variable
    $error_message = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dcfm_template_nonce'])) {
        if (!wp_verify_nonce($_POST['dcfm_template_nonce'], 'template_actions')) {
            $error_message = 'Security check failed. Please try again.';
            error_log('DCFM: Nonce verification failed in render_templates_page');
        } else {
            if (isset($_POST['add_template'])) {
                // Validate inputs
                $title = isset($_POST['template_title']) ? sanitize_text_field($_POST['template_title']) : '';
                $template_color = isset($_POST['template_color']) ? sanitize_hex_color($_POST['template_color']) : '#2271b1';
                $text_color = isset($_POST['text_color']) ? sanitize_hex_color($_POST['text_color']) : '#333333';
                $input_color = isset($_POST['input_color']) ? sanitize_hex_color($_POST['input_color']) : '#ffffff';
                $text_font = isset($_POST['text_font']) ? sanitize_text_field($_POST['text_font']) : 'Arial';
                $hover_effect = isset($_POST['hover_effect']) ? sanitize_text_field($_POST['hover_effect']) : 'scale';
                $hover_color = isset($_POST['hover_color']) ? sanitize_hex_color($_POST['hover_color']) : '#1a5d93';
                $animation = isset($_POST['animation']) ? sanitize_text_field($_POST['animation']) : 'fadeIn';
                $submit_button_width = isset($_POST['submit_button_width']) ? sanitize_text_field($_POST['submit_button_width']) : 'full-width';
                $submit_button_position = ($submit_button_width === 'max-width' && isset($_POST['submit_button_position'])) ? sanitize_text_field($_POST['submit_button_position']) : 'middle';
                $border_enabled = isset($_POST['border_enabled']) ? 1 : 0;
                $border_type = isset($_POST['border_type']) ? sanitize_text_field($_POST['border_type']) : 'solid';
                $border_radius = isset($_POST['border_radius']) ? sanitize_text_field($_POST['border_radius']) : '4px';
                $border_position = isset($_POST['border_position']) && is_array($_POST['border_position']) ? implode(',', array_map('sanitize_text_field', $_POST['border_position'])) : 'all';
                $placeholder_icon_color = isset($_POST['placeholder_icon_color']) ? sanitize_hex_color($_POST['placeholder_icon_color']) : '#999999';
                $hover_placeholder_icon_color = isset($_POST['hover_placeholder_icon_color']) ? sanitize_hex_color($_POST['hover_placeholder_icon_color']) : '#666666';
                $multi_field_row_enabled = isset($_POST['multi_field_row_enabled']) ? 1 : 0;
                $multi_field_row_fields = $multi_field_row_enabled && isset($_POST['multi_field_row_fields']) ? json_encode(array_map('intval', $_POST['multi_field_row_fields'])) : null;
                $display_border = isset($_POST['display_border']) ? 1 : 0;

                // Validate required fields
                if (empty($title)) {
                    $error_message = 'Template title is required.';
                    error_log('DCFM: Template title is empty');
                } elseif (!$template_color || !$text_color || !$input_color || !$hover_color || !$placeholder_icon_color || !$hover_placeholder_icon_color) {
                    $error_message = 'All color fields must be valid hex colors.';
                    error_log('DCFM: Invalid color input');
                } elseif ($multi_field_row_enabled && empty($multi_field_row_fields)) {
                    $error_message = 'At least one field must be selected for multi-field row.';
                    error_log('DCFM: No multi-field row fields selected');
                } else {
                    $css = $this->generate_template_css($template_color, $text_color, $input_color, $text_font, $hover_effect, $hover_color, $animation, $submit_button_width, $submit_button_position, $border_enabled, $border_radius, $border_position, $placeholder_icon_color, $hover_placeholder_icon_color, $border_type, $display_border);

                    // Log the data being inserted
                    error_log('DCFM: Attempting to insert template: ' . print_r([
                        'title' => $title,
                        'css' => $css,
                        'template_color' => $template_color,
                        'text_color' => $text_color,
                        'input_color' => $input_color,
                        'text_font' => $text_font,
                        'hover_effect' => $hover_effect,
                        'hover_color' => $hover_color,
                        'animation' => $animation,
                        'submit_button_width' => $submit_button_width,
                        'submit_button_position' => $submit_button_position,
                        'border_enabled' => $border_enabled,
                        'border_type' => $border_type,
                        'border_radius' => $border_radius,
                        'border_position' => $border_position,
                        'placeholder_icon_color' => $placeholder_icon_color,
                        'hover_placeholder_icon_color' => $hover_placeholder_icon_color,
                        'multi_field_row_enabled' => $multi_field_row_enabled,
                        'multi_field_row_fields' => $multi_field_row_fields,
                        'display_border' => $display_border
                    ], true));

                    $result = $wpdb->insert(
                        $templates_table,
                        [
                            'title' => $title,
                            'css' => $css['css'],
                            'template_color' => $template_color,
                            'text_color' => $text_color,
                            'input_color' => $input_color,
                            'text_font' => $text_font,
                            'hover_effect' => $hover_effect,
                            'hover_color' => $hover_color,
                            'animation' => $animation,
                            'submit_button_width' => $submit_button_width,
                            'submit_button_position' => $submit_button_position,
                            'border_enabled' => $border_enabled,
                            'border_type' => $border_type,
                            'border_radius' => $border_radius,
                            'border_position' => $border_position,
                            'placeholder_icon_color' => $placeholder_icon_color,
                            'hover_placeholder_icon_color' => $hover_placeholder_icon_color,
                            'multi_field_row_enabled' => $multi_field_row_enabled,
                            'multi_field_row_fields' => $multi_field_row_fields,
                            'display_border' => $display_border
                        ],
                        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d']
                    );

                    if ($result !== false) {
                        echo '<div class="updated"><p>Template created successfully!</p></div>';
                    } else {
                        $error_message = 'Failed to create template: ' . $wpdb->last_error;
                        error_log('DCFM: Database error during template insertion: ' . $wpdb->last_error);
                    }
                }
            } elseif (isset($_POST['delete_template']) && isset($_POST['template_id'])) {
                $template_id = intval($_POST['template_id']);
                if ($template_id !== 1) {
                    $result = $wpdb->delete($templates_table, ['id' => $template_id], ['%d']);
                    if ($result !== false) {
                        $wpdb->update(
                            $wpdb->prefix . 'dcfm_forms',
                            ['template_id' => 1],
                            ['template_id' => $template_id],
                            ['%d'],
                            ['%d']
                        );
                        echo '<div class="updated"><p>Successfully deleted!</p></div>';
                    } else {
                        $error_message = 'Failed to delete template: ' . $wpdb->last_error;
                        error_log('DCFM: Database error during template deletion: ' . $wpdb->last_error);
                    }
                } else {
                    $error_message = 'Cannot delete default template.';
                }
            }
        }
    }
    
    // Display error message if any
    if (!empty($error_message)) {
        echo '<div class="error"><p>' . esc_html($error_message) . '</p></div>';
    }
    
    $templates = $wpdb->get_results("SELECT * FROM $templates_table ORDER BY created_at DESC");
    $fields = $wpdb->get_results("SELECT * FROM $fields_table ORDER BY created_at ASC");
    $sample_form = $this->render_sample_form_for_preview();
    ?>
    <div class="wrap">
        <h1>Form Templates</h1>
        <h2>Add New Template</h2>
        <form method="post" id="add-template-form">
            <?php wp_nonce_field('template_actions', 'dcfm_template_nonce'); ?>
            <input type="hidden" name="dcfm_template_action" value="add_template">
            <table class="form-table">
                <tr>
                    <th><label for="template_title">Template Title</label></th>
                    <td>
                        <input type="text" name="template_title" id="template_title" class="regular-text" required>
                        <p class="description">Unique name for the template.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="template_color">Template Color</label></th>
                    <td>
                        <input type="color" name="template_color" id="template_color" value="#2271b1">
                        <p class="description">Main color for buttons, borders, and CAPTCHA background.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="text_color">Text Color</label></th>
                    <td>
                        <input type="color" name="text_color" id="text_color" value="#333333">
                        <p class="description">Color for form text and inputs.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="input_color">Input Field Background</label></th>
                    <td>
                        <input type="color" name="input_color" id="input_color" value="#ffffff">
                        <p class="description">Background color for input fields.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="placeholder_icon_color">Placeholder & Icon Color</label></th>
                    <td>
                        <input type="color" name="placeholder_icon_color" id="placeholder_icon_color" value="#999999">
                        <p class="description">Color for placeholder text and input icons.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hover_placeholder_icon_color">Hover Placeholder & Icon Color</label></th>
                    <td>
                        <input type="color" name="hover_placeholder_icon_color" id="hover_placeholder_icon_color" value="#666666">
                        <p class="description">Color for placeholder text and input icons on hover.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="text_font">Font</label></th>
                    <td>
                        <select name="text_font" id="text_font">
                            <option value="Arial">Arial</option>
                            <option value="Helvetica">Helvetica</option>
                            <option value="Times New Roman">Times New Roman</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Verdana">Verdana</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="hover_effect">Hover Effect</label></th>
                    <td>
                        <select name="hover_effect" id="hover_effect">
                            <option value="scale">Scale</option>
                            <option value="shadow">Shadow</option>
                            <option value="opacity">Opacity</option>
                            <option value="border-pulse">Border Pulse</option>
                            <option value="glow">Glow</option>
                            <option value="underline">Underline</option>
                            <option value="rotate">Rotate</option>
                            <option value="skew">Skew</option>
                            <option value="flip">Flip</option>
                            <option value="bounce">Bounce</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="hover_color">Hover Color</label></th>
                    <td>
                        <input type="color" name="hover_color" id="hover_color" value="#1a5d93">
                    </td>
                </tr>
                <tr>
                    <th><label for="animation">Animation</label></th>
                    <td>
                        <select name="animation" id="animation">
                            <option value="fadeIn">Fade In</option>
                            <option value="slideIn">Slide In</option>
                            <option value="bounce">Bounce</option>
                            <option value="zoomIn">Zoom In</option>
                            <option value="pulse">Pulse</option>
                            <option value="rotateIn">Rotate In</option>
                            <option value="flipIn">Flip In</option>
                            <option value="swing">Swing</option>
                            <option value="slideUp">Slide Up</option>
                            <option value="shake">Shake</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="submit_button_width">Submit Button Width</label></th>
                    <td>
                        <select name="submit_button_width" id="submit_button_width">
                            <option value="max-width">Max Width</option>
                            <option value="full-width">Full Width</option>
                        </select>
                    </td>
                </tr>
                <tr id="submit-button-position-config" style="display: none;">
                    <th><label for="submit_button_position">Submit Button Position</label></th>
                    <td>
                        <select name="submit_button_position" id="submit_button_position">
                            <option value="left">Left</option>
                            <option value="middle">Middle</option>
                            <option value="right">Right</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="border_enabled">Enable Border</label></th>
                    <td>
                        <input type="checkbox" name="border_enabled" id="border_enabled" value="1">
                        <p class="description">Check to enable border for the form wrapper.</p>
                    </td>
                </tr>
                <tr id="border-type-config">
                    <th><label for="border_type">Border Type</label></th>
                    <td>
                        <select name="border_type" id="border_type">
                            <option value="solid">Solid</option>
                            <option value="dashed">Dashed</option>
                            <option value="dotted">Dotted</option>
                            <option value="double">Double</option>
                        </select>
                        <p class="description">Select the type of border for the form wrapper and inputs.</p>
                    </td>
                </tr>
                <tr id="border-radius-config">
                    <th><label for="border_radius">Border Radius</label></th>
                    <td>
                        <input type="text" name="border_radius" id="border_radius" class="regular-text" value="4px">
                        <p class="description">Enter the border radius (e.g., 4px, 10px).</p>
                    </td>
                </tr>
                <tr id="border-position-config">
                    <th><label>Border Position</label></th>
                    <td>
                        <label><input type="checkbox" name="border_position[]" value="top" checked> Top</label><br>
                        <label><input type="checkbox" name="border_position[]" value="right" checked> Right</label><br>
                        <label><input type="checkbox" name="border_position[]" value="bottom" checked> Bottom</label><br>
                        <label><input type="checkbox" name="border_position[]" value="left" checked> Left</label><br>
                        <label><input type="checkbox" name="border_position[]" value="all" checked> All</label><br>
                        <p class="description">Select the border positions to display (select 'All' for all sides).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="display_border">Display Border for Full Form</label></th>
                    <td>
                        <input type="checkbox" name="display_border" id="display_border" value="1" checked>
                        <p class="description">Check to display the outer border for the full form wrapper.</p>
                    </td>
                </tr>
                <tr id="multi-field-row-config" style="display: none;">
                    <th><label>Multi-Field Row Fields</label></th>
                    <td>
                        <?php foreach ($fields as $field): ?>
                            <label>
                                <input type="checkbox" name="multi_field_row_fields[]" value="<?php echo esc_attr($field->id); ?>">
                                <?php echo esc_html(ucfirst($field->field_name)); ?>
                            </label><br>
                        <?php endforeach; ?>
                        <p class="description">Select fields to include in the multi-field row.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="add_template" class="button button-primary" value="Add Template">
            </p>
        </form>

        <h2>Existing Templates</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Template Name</th>
                    <th style="width: 50%;">Preview</th>
                    <th>Edit</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><?php echo esc_html($template->title); ?></td>
                        <td>
                            <div class="template-preview" style="max-width: 500px;">
                                <style scoped><?php echo $template->css; ?></style>
                                <?php echo $this->render_sample_form_for_preview($template); ?>
                            </div>
                        </td>
                        
                        <td>
                            <form method="get" action="<?php echo admin_url('admin.php?page=dcfm_edit_template'); ?>" style="display: inline;">
                                <input type="hidden" name="page" value="dcfm_edit_template">
                                <input type="hidden" name="template_id" value="<?php echo esc_attr($template->id); ?>">
                                <?php wp_nonce_field('edit_template', 'dcfm_edit_nonce'); ?>
                                <button type="submit" class="button">Edit</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="dcfm_template_nonce" value="<?php echo esc_attr(wp_create_nonce('template_actions')); ?>">
                                <input type="hidden" name="dcfm_template_action" value="delete_template">
                                <input type="hidden" name="template_id" value="<?php echo esc_attr($template->id); ?>">
                                <button type="submit" name="delete_template" class="button" 
                                        onclick="return confirm('Are you sure you want to delete this template?');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <style>
        .template-preview { max-width: 500px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9; }
        #quick-edit-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            max-width: 500px;
            width: 90%;
        }
        .close-modal { margin-left: 10px; }
        #multi-field-row-config, #submit-button-position-config, #border-radius-config, #border-position-config, #quick-border-radius-config, #quick-border-position-config { margin-top: 10px; }
    </style>
    
    <script>
        jQuery(document).ready(function($) {
            // Toggle multi-field row config
            $('#multi_field_row_enabled').change(function() {
                $('#multi-field-row-config').toggle(this.checked);
            });
            
            // Toggle submit button position config
            function toggleSubmitButtonPosition() {
                const width = $('#submit_button_width').val() || $('#quick-submit-button-width').val();
                $('#submit-button-position-config, #quick-submit-button-position-config').toggle(width === 'max-width');
            }
            $('#submit_button_width, #quick-submit-button-width').change(toggleSubmitButtonPosition);
            
            // Initialize submit button position visibility
            toggleSubmitButtonPosition();
            
            // Ensure 'All' border position controls other checkboxes
            $('input[name="border_position[]"][value="all"]').change(function() {
                const isChecked = $(this).is(':checked');
                $('input[name="border_position[]"]').not('[value="all"]').prop('checked', isChecked);
            });
            
            $('input[name="border_position[]"]').not('[value="all"]').change(function() {
                if (!$(this).is(':checked')) {
                    $('input[name="border_position[]"][value="all"]').prop('checked', false);
                } else if ($('input[name="border_position[]"]').not('[value="all"]').length === $('input[name="border_position[]"]').not('[value="all"]').filter(':checked').length) {
                    $('input[name="border_position[]"][value="all"]').prop('checked', true);
                }
            });
            
            $('#quick-border-position-config input[name="border_position[]"][value="all"]').change(function() {
                const isChecked = $(this).is(':checked');
                $('#quick-border-position-config input[name="border_position[]"]').not('[value="all"]').prop('checked', isChecked);
            });
            
            $('#quick-border-position-config input[name="border_position[]"]').not('[value="all"]').change(function() {
                if (!$(this).is(':checked')) {
                    $('#quick-border-position-config input[name="border_position[]"][value="all"]').prop('checked', false);
                } else if ($('#quick-border-position-config input[name="border_position[]"]').not('[value="all"]').length === $('#quick-border-position-config input[name="border_position[]"]').not('[value="all"]').filter(':checked').length) {
                    $('#quick-border-position-config input[name="border_position[]"][value="all"]').prop('checked', true);
                }
            });
            
            $('#quick-edit-form').submit(function(e) {
                e.preventDefault();
                const templateId = $('#quick-edit-template-id').val();
                const borderPosition = $('#quick-border-position-config input[value="all"]').is(':checked') ? 'all' : 
                    $('#quick-border-position-config input[name="border_position[]"]:checked').map(function() { return $(this).val(); }).get().join(',');
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'dcfm_quick_edit_template',
                        nonce: '<?php echo wp_create_nonce('dcfm_ajax_nonce'); ?>',
                        template_id: templateId,
                        template_color: $('#quick-template-color').val(),
                        text_color: $('#quick-text-color').val(),
                        input_color: $('#quick-input-color').val(),
                        text_font: $('#quick-text-font').val(),
                        hover_effect: $('#quick-hover-effect').val(),
                        hover_color: $('#quick-hover-color').val(),
                        animation: $('#quick-animation').val(),
                        submit_button_width: $('#quick-submit-button-width').val(),
                        submit_button_position: $('#quick-submit-button-position').val(),
                        border_enabled: $('#quick-border-enabled').is(':checked') ? 1 : 0,
                        border_type: $('#quick-border-type').val(),
                        border_radius: $('#quick-border-radius').val(),
                        border_position: borderPosition,
                        placeholder_icon_color: $('#quick-placeholder-icon-color').val(),
                        hover_placeholder_icon_color: $('#quick-hover-placeholder-icon-color').val(),
                        display_border: $('#quick-display-border').is(':checked') ? 1 : 0
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload(); // Refresh to update preview
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('AJAX error occurred.');
                    }
                });
            });
        });
    </script>
    <?php
}


// end of the code 

// start the new code 2

public function handle_ajax_submission() {
    error_log('DCFM: AJAX Submission - POST: ' . print_r($_POST, true));

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dcfm_ajax_nonce')) {
        error_log('DCFM: Nonce verification failed');
        wp_send_json(['success' => false, 'data' => 'Security check failed. Please refresh and try again.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'dcfm_submissions';
    $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

    if ($form_id <= 0) {
        error_log('DCFM: Invalid form ID: ' . $form_id);
        wp_send_json(['success' => false, 'data' => 'Invalid form ID']);
        return;
    }

    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    if ($email && $this->restrict_multiple_submissions($form_id, $email)) {
        error_log("DCFM: Duplicate submission blocked for email: $email, form_id: $form_id");
        wp_send_json(['success' => false, 'data' => 'This email has already submitted this form.']);
        return;
    }

    $user_answer = isset($_POST['captcha_input']) ? trim(sanitize_text_field($_POST['captcha_input'])) : '';
    $correct_answer = isset($_POST['captcha_answer']) ? trim(sanitize_text_field($_POST['captcha_answer'])) : '';

    error_log("DCFM: CAPTCHA - User: '$user_answer', Correct: '$correct_answer'");

    if ($user_answer !== $correct_answer) {
        error_log('DCFM: CAPTCHA mismatch detected');
        wp_send_json(['success' => false, 'data' => 'Incorrect CAPTCHA. Please enter the exact code shown.']);
        return;
    }

    $submission_data = [];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, ['action', 'nonce', 'form_id', 'captcha_input', 'captcha_answer'])) {
            $submission_data[$key] = sanitize_text_field($value);
        }
    }

    $result = $wpdb->insert(
        $table_name,
        [
            'form_id' => $form_id,
            'submission_data' => wp_json_encode($submission_data),
            'created_at' => current_time('mysql', true)
        ],
        ['%d', '%s', '%s']
    );

    if ($result === false) {
        error_log('DCFM: Database insert failed: ' . $wpdb->last_error);
        wp_send_json(['success' => false, 'data' => 'Failed to save submission. Please try again.']);
        return;
    }

    $email_result = $this->email_handler->send_notification_emails($submission_data);

    if ($email_result['success']) {
        error_log('DCFM: Submission and emails sent successfully');
        wp_send_json(['success' => true, 'data' => 'Form submitted successfully!']);
    } else {
        $message = 'Form submitted, but ';
        $message .= (!$email_result['admin_sent'] && !$email_result['user_sent']) ? 'notifications failed.' :
                    (!$email_result['admin_sent'] ? 'admin notification failed.' : 'user notification failed.');
        error_log('DCFM: ' . $message);
        wp_send_json(['success' => true, 'data' => $message]);
    }
}

public function render_add_form_page() {
    global $wpdb;
    $optional_fields = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}dcfm_fields 
        ORDER BY created_at ASC"
    );
    $templates = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}dcfm_templates ORDER BY id"
    );
    
    $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $form_data = $edit_id ? $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}dcfm_forms WHERE id = %d", 
        $edit_id
    )) : null;
    
    $selected_fields = $form_data ? json_decode($form_data->fields, true) : array();
    $selected_field_rows = $form_data && $form_data->field_rows ? json_decode($form_data->field_rows, true) : array();
    $selected_template_id = $form_data ? $form_data->template_id : 1;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dcfm_form_create'])) {
        $this->save_form();
    }

    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    ?>
    <div class="wrap">
        <h1><?php echo $edit_id ? 'Edit Form' : 'Add New Form'; ?></h1>
        
        <style>
            .field-container, .row-container { margin: 20px 0; }
            .field-item, .row-item {
                background: #fff;
                border: 1px solid #ddd;
                padding: 10px;
                margin: 5px 0;
                cursor: move;
                border-radius: 4px;
            }
            .field-item:hover, .row-item:hover { background: #f9f9f9; }
            .field-item.ui-sortable-helper, .row-item.ui-sortable-helper {
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            .field-placeholder, .row-placeholder {
                border: 2px dashed #ccc;
                height: 40px;
                margin: 5px 0;
            }
            .field-controls, .row-controls { float: right; }
            .required-label { color: red; margin-left: 10px; }
            .field-type { color: #666; margin-left: 10px; font-style: italic; }
            .field-item .dashicons, .row-item .dashicons {
                color: #666;
                cursor: pointer;
                margin-left: 5px;
            }
            .field-item .dashicons:hover, .row-item .dashicons:hover { color: #135e96; }
            .row-field-list {
                margin: 10px 0;
                padding: 10px;
                background: #f1f1f1;
                border-radius: 4px;
            }
            .field-icon { margin-right: 5px; }
            .error-message { color: red; margin-bottom: 10px; }
            .preview-container { margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9; }
            .preview-container h3 { margin-top: 0; }
        </style>

        <form method="post" id="create-form">
            <?php wp_nonce_field('dcfm_form_create', 'dcfm_form_create'); ?>
            <input type="hidden" name="form_id" value="<?php echo esc_attr($edit_id); ?>">
            <input type="hidden" name="field_order" id="field-order" value="<?php echo esc_attr(json_encode($selected_fields)); ?>">
            <input type="hidden" name="field_rows" id="field-rows" value="<?php echo esc_attr(json_encode($selected_field_rows)); ?>">
            
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message"><?php echo esc_html(urldecode($_GET['error'])); ?></div>
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="form_title">Form Title</label></th>
                    <td>
                        <input type="text" name="form_title" id="form_title" class="regular-text" 
                               value="<?php echo esc_attr($form_data->title ?? ''); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="submit_button_text">Submit Button Text</label></th>
                    <td>
                        <input type="text" name="submit_button_text" id="submit_button_text" class="regular-text" 
                               value="<?php echo esc_attr($form_data->submit_button_text ?? 'Submit'); ?>" required>
                        <p class="description">Enter the text to display on the,linebreak the form's submit button.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="template_id">Template</label></th>
                    <td>
                        <select name="template_id" id="template_id" required>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo esc_attr($template->id); ?>" 
                                        <?php echo ($selected_template_id == $template->id) ? 'selected' : ''; ?>
                                        data-template-id="<?php echo esc_attr($template->id); ?>">
                                    <?php echo esc_html($template->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select a template to style this form.</p>
                    </td>
                </tr>
                <tr id="form-fields-section" style="display: <?php echo empty($selected_field_rows) ? 'table-row' : 'none'; ?>;">
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
                                        $icon_class = $this->get_field_icon_class($field->field_type);
                                    ?>
                                        <div class="field-item" data-field-id="<?php echo esc_attr($field->id); ?>">
                                            <input type="hidden" name="fields[]" value="<?php echo esc_attr($field->id); ?>">
                                            <i class="fas <?php echo esc_attr($icon_class); ?> field-icon"></i>
                                            <?php echo esc_html(ucfirst($field->field_name)); ?>
                                            <span class="field-type"><?php echo esc_html($field->field_type); ?></span>
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
                                        $icon_class = $this->get_field_icon_class($field->field_type);
                                    ?>
                                        <div class="field-item" data-field-id="<?php echo esc_attr($field->id); ?>">
                                            <i class="fas <?php echo esc_attr($icon_class); ?> field-icon"></i>
                                            <?php echo esc_html(ucfirst($field->field_name)); ?>
                                            <span class="field-type">[<?php echo esc_html($field->field_type); ?>]</span>
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
                <tr>
                    <th>Multi-Field Rows</th>
                    <td>
                        <div class="row-container">
                            <label>
                                <input type="checkbox" id="enable-multi-field-rows" name="enable_multi_field_rows" 
                                       <?php echo !empty($selected_field_rows) ? 'checked' : ''; ?>>
                                Enable multiple fields in a single row
                            </label>
                            <div id="row-config" style="display: <?php echo !empty($selected_field_rows) ? 'block' : 'none'; ?>;">
                                <h4>Configure Rows</h4>
                                <div id="selected-rows" class="sortable-rows">
                                    <?php 
                                    foreach ($selected_field_rows as $index => $row):
                                        $row_fields = is_array($row) ? $row : [$row];
                                    ?>
                                        <div class="row-item" data-row-id="<?php echo esc_attr($index); ?>">
                                            <div class="row-fields">
                                                <?php
                                                foreach ($row_fields as $field_id):
                                                    $field = null;
                                                    foreach ($optional_fields as $of) {
                                                        if ($of->id == $field_id) {
                                                            $field = $of;
                                                            break;
                                                        }
                                                    }
                                                    if ($field):
                                                        $field_icon = $this->get_field_icon_class($field->field_type);
                                                    ?>
                                                        <span class="row-field-item" data-field-id="<?php echo esc_attr($field->id); ?>">
                                                            <i class="fas <?php echo esc_attr($field_icon); ?> field-icon"></i>
                                                            <?php echo esc_html(ucfirst($field->field_name)); ?>
                                                            <span class="dashicons dashicons-no remove-row-field"></span>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="row-controls">
                                                <select class="add-row-field">
                                                    <option value="">Add Field to Row</option>
                                                    <?php foreach ($optional_fields as $field): ?>
                                                        <option value="<?php echo esc_attr($field->id); ?>" 
                                                                data-icon="<?php echo esc_attr($this->get_field_icon_class($field->field_type)); ?>">
                                                            <?php echo esc_html(ucfirst($field->field_name)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <span class="dashicons dashicons-trash remove-row"></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" id="add-row" class="button">Add New Row</button>
                            </div>
                        </div>
                    </td>
                </tr>
                
            </table>
            <p class="submit">
                <input type="submit" name="submit_form" class="button button-primary" 
                       value="<?php echo $edit_id ? 'Update Form' : 'Create Form'; ?>">
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Initialize sortable fields
        $('#selected-fields').sortable({
            placeholder: 'field-placeholder',
            handle: '.dashicons-move',
            update: function(event, ui) {
                updateFieldOrder();
                updatePreview();
            }
        });

        // Initialize sortable rows
        $('#selected-rows').sortable({
            placeholder: 'row-placeholder',
            handle: '.dashicons-move',
            update: function(event, ui) {
                updateRowOrder();
                updatePreview();
            }
        });

        // Add field to selected fields
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
            updatePreview();
        });

        // Remove field from selected fields
        $(document).on('click', '.remove-field', function() {
            var fieldItem = $(this).closest('.field-item');
            var fieldId = fieldItem.data('field-id');
            var availableField = fieldItem.clone();
            availableField.find('.field-controls').html(
                '<span class="dashicons dashicons-plus-alt2 add-field"></span>'
            );
            availableField.find('input[name="fields[]"]').remove();
            
            $('#available-fields').append(availableField);
            fieldItem.remove();
            updateFieldOrder();
            updatePreview();
        });

        // Add field to row
        $(document).on('change', '.add-row-field', function() {
            var select = $(this);
            var fieldId = select.val();
            if (!fieldId) return;
            var fieldName = select.find('option:selected').text();
            var fieldIcon = select.find('option:selected').data('icon') || 'fa-text-width';
            
            var fieldHtml = '<span class="row-field-item" data-field-id="' + fieldId + '">' +
                            '<i class="fas ' + fieldIcon + ' field-icon"></i>' +
                            fieldName + ' <span class="dashicons dashicons-no remove-row-field"></span></span>';
            
            select.closest('.row-item').find('.row-fields').append(fieldHtml);
            select.val('');
            updateRowOrder();
            updatePreview();
        });

        // Remove field from row
        $(document).on('click', '.remove-row-field', function() {
            $(this).closest('.row-field-item').remove();
            updateRowOrder();
            updatePreview();
        });

        // Add new row
        $('#add-row').click(function() {
            var rowId = $('#selected-rows .row-item').length;
            var newRow = $('<div class="row-item" data-row-id="' + rowId + '">' +
                '<div class="row-fields"></div>' +
                '<div class="row-controls">' +
                '<span class="dashicons dashicons-move"></span>' +
                '<select class="add-row-field">' +
                '<option value="">Add Field to Row</option>' +
                '<?php foreach ($optional_fields as $field): ?>' +
                '<option value="<?php echo esc_attr($field->id); ?>" data-icon="<?php echo esc_attr($this->get_field_icon_class($field->field_type)); ?>">' +
                '<?php echo esc_html(ucfirst($field->field_name)); ?>' +
                '</option>' +
                '<?php endforeach; ?>' +
                '</select>' +
                '<span class="dashicons dashicons-trash remove-row"></span>' +
                '</div>' +
                '</div>');
            
            $('#selected-rows').append(newRow);
            updateRowOrder();
            updatePreview();
        });

        // Remove row
        $(document).on('click', '.remove-row', function() {
            $(this).closest('.row-item').remove();
            updateRowOrder();
            updatePreview();
        });

        // Toggle row configuration and hide/show form fields
        $('#enable-multi-field-rows').on('change', function() {
            $('#row-config').toggle(this.checked);
            $('#form-fields-section').toggle(!this.checked);
            if (this.checked) {
                $('#selected-fields').empty();
                $('#field-order').val('[]');
            }
            updateRowOrder();
            updatePreview();
        });

        // Update field order
        function updateFieldOrder() {
            var order = $('#selected-fields .field-item').map(function() {
                return $(this).data('field-id');
            }).get();
            $('#field-order').val(JSON.stringify(order));
        }

        // Update row order
        function updateRowOrder() {
            var rows = [];
            $('#selected-rows .row-item').each(function() {
                var fieldIds = $(this).find('.row-field-item').map(function() {
                    return $(this).data('field-id');
                }).get();
                if (fieldIds.length > 0) {
                    rows.push(fieldIds);
                }
            });
            $('#field-rows').val(JSON.stringify(rows));
            if ($('#enable-multi-field-rows').is(':checked')) {
                var fieldOrder = [];
                rows.forEach(function(row) {
                    fieldOrder = fieldOrder.concat(row);
                });
                $('#field-order').val(JSON.stringify(fieldOrder));
            }
        }

        // Update preview
        function updatePreview() {
            var templateId = $('#template_id').val();
            var fieldOrder = JSON.parse($('#field-order').val() || '[]');
            var fieldRows = JSON.parse($('#field-rows').val() || '[]');
            var enableMultiFieldRows = $('#enable-multi-field-rows').is(':checked');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'dcfm_preview_form',
                    template_id: templateId,
                    fields: fieldOrder,
                    field_rows: fieldRows,
                    multi_field_row_enabled: enableMultiFieldRows ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        $('#form-preview').html(response.data.preview);
                    } else {
                        $('#form-preview').html('<p>Error loading preview: ' + response.data.message + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#form-preview').html('<p>AJAX error: ' + error + '</p>');
                    console.log('AJAX error:', error);
                }
            });
        }

        // Update preview on template change
        $('#template_id').on('change', function() {
            updatePreview();
        });

        // Initialize field and row order
        updateFieldOrder();
        updateRowOrder();
        updatePreview();

        // Form submission validation
        $('#create-form').on('submit', function(e) {
            var fieldOrder = JSON.parse($('#field-order').val() || '[]');
            var fieldRows = JSON.parse($('#field-rows').val() || '[]');
            if ($('#enable-multi-field-rows').is(':checked') && fieldRows.length === 0) {
                e.preventDefault();
                alert('Please configure at least one row with fields when Multi-Field Rows is enabled.');
                return false;
            }
            if (!$('#enable-multi-field-rows').is(':checked') && fieldOrder.length === 0) {
                e.preventDefault();
                alert('Please select at least one field for the form.');
                return false;
            }
            if (!$('#form_title').val().trim()) {
                e.preventDefault();
                alert('Form title is required.');
                return false;
            }
            if (!$('#submit_button_text').val().trim()) {
                e.preventDefault();
                alert('Submit button text is required.');
                return false;
            }
        });
    });
    </script>
    <?php
}

private function get_field_icon_class($field_type) {
    switch ($field_type) {
        case 'text':
            return 'fa-solid fa-text-width';
        case 'email':
            return 'fa-solid fa-envelope';
        case 'textarea':
            return 'fa-solid fa-paragraph';
        case 'select':
            return 'fa-solid fa-caret-down';
        case 'checkbox':
            return 'fa-solid fa-square-check';
        case 'radio':
            return 'fa-solid fa-circle-dot';
        case 'phone':
            return 'fa-solid fa-phone';
        case 'date':
            return 'fa-solid fa-calendar';
        case 'time':
            return 'fa-solid fa-clock';
        default:
            return 'fa-solid fa-text-width';
    }
}
public function render_fields_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dcfm_fields';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dcfm_field_nonce'])) {
        if (!wp_verify_nonce($_POST['dcfm_field_nonce'], 'add_field')) {
            wp_die('Security check failed');
        }

        if (isset($_POST['add_field'])) {
            $field_name = sanitize_text_field($_POST['field_name']);
            $field_type = sanitize_text_field($_POST['field_type']);
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $options = isset($_POST['options']) ? sanitize_textarea_field($_POST['options']) : null;
            $placeholder_icon = isset($_POST['placeholder_icon']) ? wp_kses($_POST['placeholder_icon'], array(
                'i' => array('class' => array(), 'style' => array()),
                'span' => array('class' => array(), 'style' => array())
            )) : '';
            $placeholder_text = isset($_POST['placeholder_text']) ? sanitize_text_field($_POST['placeholder_text']) : '';
            $show_label = isset($_POST['show_label']) ? 1 : 0;
            $hyperlink = isset($_POST['hyperlink']) ? esc_url_raw($_POST['hyperlink']) : '';

            // Check if field with same name and type already exists
            $existing_field = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE field_name = %s AND field_type = %s",
                    $field_name,
                    $field_type
                )
            );

            if ($existing_field) {
                echo '<div class="error"><p>Failed to add field: A field with the same name and type already exists.</p></div>';
            } else {
                $result = $wpdb->insert(
                    $table_name,
                    [
                        'field_name' => $field_name,
                        'field_type' => $field_type,
                        'is_required' => $is_required,
                        'options' => $options,
                        'placeholder' => $placeholder_icon,
                        'placeholder_text' => $placeholder_text,
                        'show_label' => $show_label,
                        'hyperlink' => $hyperlink
                    ],
                    ['%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s']
                );

                if ($result !== false) {
                    echo '<div class="updated"><p>Field added successfully!</p></div>';
                } else {
                    echo '<div class="error"><p>Failed to add field: ' . $wpdb->last_error . '</p></div>';
                }
            }
        } elseif (isset($_POST['delete_field']) && isset($_POST['field_id'])) {
            $field_id = intval($_POST['field_id']);
            $wpdb->delete($table_name, ['id' => $field_id], ['%d']);
            echo '<div class="updated"><p>Field deleted successfully!</p></div>';
        } elseif (isset($_POST['edit_field']) && isset($_POST['field_id'])) {
            $field_id = intval($_POST['field_id']);
            $field_name = sanitize_text_field($_POST['field_name']);
            $field_type = sanitize_text_field($_POST['field_type']);
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $options = isset($_POST['options']) ? sanitize_textarea_field($_POST['options']) : null;
            $placeholder_icon = isset($_POST['placeholder_icon']) ? wp_kses($_POST['placeholder_icon'], array(
                'i' => array('class' => array(), 'style' => array()),
                'span' => array('class' => array(), 'style' => array())
            )) : '';
            $placeholder_text = isset($_POST['placeholder_text']) ? sanitize_text_field($_POST['placeholder_text']) : '';
            $show_label = isset($_POST['show_label']) ? 1 : 0;
            $hyperlink = isset($_POST['hyperlink']) ? esc_url_raw($_POST['hyperlink']) : '';

            // Check if another field with same name and type exists (excluding current field)
            $existing_field = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE field_name = %s AND field_type = %s AND id != %d",
                    $field_name,
                    $field_type,
                    $field_id
                )
            );

            if ($existing_field) {
                echo '<div class="error"><p>Failed to update field: A field with the same name and type already exists.</p></div>';
            } else {
                $result = $wpdb->update(
                    $table_name,
                    [
                        'field_name' => $field_name,
                        'field_type' => $field_type,
                        'is_required' => $is_required,
                        'options' => $options,
                        'placeholder' => $placeholder_icon,
                        'placeholder_text' => $placeholder_text,
                        'show_label' => $show_label,
                        'hyperlink' => $hyperlink
                    ],
                    ['id' => $field_id],
                    ['%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s'],
                    ['%d']
                );

                if ($result !== false) {
                    echo '<div class="updated"><p>Field updated successfully!</p></div>';
                } else {
                    echo '<div class="error"><p>Failed to update field: ' . $wpdb->last_error . '</p></div>';
                }
            }
        }
    }

    $fields = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id");
    $edit_field = null;
    if (isset($_GET['edit_field']) && is_numeric($_GET['edit_field'])) {
        $edit_field_id = intval($_GET['edit_field']);
        $edit_field = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_field_id));
    }

    // Define available icons for dropdown
    $available_icons = [
        '' => 'None',
        '<i class="fa-solid fa-text-width"></i>' => 'Text Width',
        '<i class="fa-solid fa-envelope"></i>' => 'Envelope',
        '<i class="fa-solid fa-paragraph"></i>' => 'Paragraph',
        '<i class="fa-solid fa-caret-down"></i>' => 'Caret Down',
        '<i class="fa-solid fa-square-check"></i>' => 'Square Check',
        '<i class="fa-solid fa-circle-dot"></i>' => 'Circle Dot',
        '<i class="fa-solid fa-phone"></i>' => 'Phone',
        '<i class="fa-solid fa-user"></i>' => 'User',
        '<i class="fa-solid fa-lock"></i>' => 'Lock',
        '<i class="fa-solid fa-comment"></i>' => 'Comment',
        '<i class="fa-solid fa-calendar"></i>' => 'Calendar',
        '<i class="fa-solid fa-map-marker-alt"></i>' => 'Map Marker',
        '<i class="fa-solid fa-star"></i>' => 'Star',
        '<i class="fa-solid fa-globe"></i>' => 'Globe',
        '<i class="fa-solid fa-id-card"></i>' => 'ID Card'
    ];
    ?>
    <div class="wrap">
        <h1>Custom Fields</h1>
        <h2><?php echo $edit_field ? 'Edit Field' : 'Add New Field'; ?></h2>
        <style>
            .field-title-row {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            .field-title-row input[type="text"] {
                flex: 1;
            }
        </style>
        <form method="post">
            <?php wp_nonce_field('add_field', 'dcfm_field_nonce'); ?>
            <?php if ($edit_field): ?>
                <input type="hidden" name="field_id" value="<?php echo esc_attr($edit_field->id); ?>">
            <?php endif; ?>
            <table class="form-table">
                <tr>
                    <th><label for="field_name">Field Name</label></th>
                    <td>
                        <div class="field-title-row">
                            <input type="text" name="field_name" id="field_name" class="regular-text" 
                                   value="<?php echo $edit_field ? esc_attr($edit_field->field_name) : ''; ?>" required>
                            <label for="is_required" style="white-space: nowrap;">
                                <input type="checkbox" name="is_required" id="is_required" 
                                       <?php checked($edit_field && $edit_field->is_required); ?>>
                                Required
                            </label>
                        </div>
                        <p class="description">Unique name for the field (e.g., "phone").</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hyperlink">Hyperlink URL</label></th>
                    <td>
                        <input type="url" name="hyperlink" id="hyperlink" class="regular-text" 
                               value="<?php echo $edit_field ? esc_attr($edit_field->hyperlink) : ''; ?>">
                        <p class="description">Enter a URL to hyperlink the field title and checkbox options (if applicable). The title and options will be underlined if a link is provided.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="field_type">Field Type</label></th>
                    <td>
                        <select name="field_type" id="field_type" required>
                            <option value="text" <?php selected($edit_field && $edit_field->field_type === 'text'); ?>>Text</option>
                            <option value="email" <?php selected($edit_field && $edit_field->field_type === 'email'); ?>>Email</option>
                            <option value="tel" <?php selected($edit_field && $edit_field->field_type === 'tel'); ?>>Telephone</option>
                            <option value="textarea" <?php selected($edit_field && $edit_field->field_type === 'textarea'); ?>>Textarea</option>
                            <option value="select" <?php selected($edit_field && $edit_field->field_type === 'select'); ?>>Select</option>
                            <option value="checkbox" <?php selected($edit_field && $edit_field->field_type === 'checkbox'); ?>>Checkbox</option>
                            <option value="radio" <?php selected($edit_field && $edit_field->field_type === 'radio'); ?>>Radio</option>
                            <option value="date" <?php selected($edit_field && $edit_field->field_type === 'date'); ?>>Date</option>
                            <option value="time" <?php selected($edit_field && $edit_field->field_type === 'time'); ?>>Time</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="options">Options</label></th>
                    <td>
                        <textarea name="options" id="options" rows="4" cols="50"><?php echo $edit_field ? esc_textarea($edit_field->options) : ''; ?></textarea>
                        <p class="description">Enter options for select, checkbox, or radio fields, one per line.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="placeholder_icon">Placeholder Icon</label></th>
                    <td>
                        <select name="placeholder_icon" id="placeholder_icon">
                            <?php foreach ($available_icons as $icon_value => $icon_label): ?>
                                <option value="<?php echo esc_attr($icon_value); ?>" 
                                        <?php selected($edit_field && $edit_field->placeholder === $icon_value); ?>>
                                    <?php echo esc_html($icon_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select an icon to display with the field.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="placeholder_text">Placeholder Text</label></th>
                    <td>
                        <input type="text" name="placeholder_text" id="placeholder_text" class="regular-text" 
                               value="<?php echo $edit_field ? esc_attr($edit_field->placeholder_text) : ''; ?>">
                        <p class="description">Enter placeholder text for the field.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="show_label">Show Label</label></th>
                    <td>
                        <input type="checkbox" name="show_label" id="show_label" 
                               <?php checked($edit_field ? $edit_field->show_label : true); ?>>
                        <p class="description">Check to display the field label.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="<?php echo $edit_field ? 'edit_field' : 'add_field'; ?>" 
                       class="button button-primary" 
                       value="<?php echo $edit_field ? 'Update Field' : 'Add Field'; ?>">
                <?php if ($edit_field): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dcfm-fields')); ?>" class="button">Cancel</a>
                <?php endif; ?>
            </p>
        </form>

        <h2>Existing Fields</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Hyperlink</th>
                    <th>Type</th>
                    <th>Required</th>
                    <th>Options</th>
                    <th>Placeholder Icon</th>
                    <th>Placeholder Text</th>
                    <th>Show Label</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $field): ?>
                    <tr>
                        <td><?php echo esc_html($field->field_name); ?></td>
                        <td><?php echo $field->hyperlink ? esc_html($field->hyperlink) : '-'; ?></td>
                        <td><?php echo esc_html($field->field_type); ?></td>
                        <td><?php echo $field->is_required ? 'Yes' : 'No'; ?></td>
                        <td><?php echo esc_html($field->options ?: '-'); ?></td>
                        <td><?php echo $field->placeholder ? wp_kses($field->placeholder, array(
                            'i' => array('class' => array(), 'style' => array()),
                            'span' => array('class' => array(), 'style' => array())
                        )) : '-'; ?></td>
                        <td><?php echo esc_html($field->placeholder_text ?: '-'); ?></td>
                        <td><?php echo $field->show_label ? 'Yes' : 'No'; ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('edit_field', $field->id, admin_url('admin.php?page=dcfm-fields'))); ?>" 
                               class="button">Edit</a>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('add_field', 'dcfm_field_nonce'); ?>
                                <input type="hidden" name="field_id" value="<?php echo esc_attr($field->id); ?>">
                                <button type="submit" name="delete_field" class="button" 
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
    <?php
}


// end of the code 

private function render_field($field, $styles = null) {
    $field_name = esc_attr($field->field_name);
    $field_type = $field->field_type;
    $is_required = $field->is_required ? 'required' : '';
    $placeholder_text = !empty($field->placeholder_text) ? esc_attr($field->placeholder_text) : 
                        "Enter your " . ucfirst(str_replace('_', ' ', $field->field_name));
    
    $icon = !empty($field->placeholder) ? $field->placeholder : '';
    if (!empty($icon)) {
        if (preg_match('/<i\b[^>]*>.*?\<\/i>|<span\b[^>]*>.*?\<\/span>|<svg\b[^>]*>.*?\<\/svg>/i', $icon, $matches)) {
            $icon = $matches[0];
        } else {
            $icon = '';
        }
    }
    
    $has_icon = !empty($icon);
    $icon_class = $this->get_field_icon_class($field->field_type);
    $input_style = $styles ? $styles['input_style'] : '';
    $hover_inline = $styles ? $styles['hover_inline'] : '';
    $hover_effect = $styles ? $styles['hover_effect'] : '';
    $reset_style = '';
    if ($styles && isset($styles['input_color']) && isset($styles['template_color'])) {
        $reset_style = "background-color: {$styles['input_color']}; border: 1px solid {$styles['template_color']}; transform: none; box-shadow: none; opacity: 1;";
    }
    error_log('Field Placeholder: ' . print_r($field->placeholder, true));
    error_log('Sanitized Icon: ' . print_r($icon, true));
    ?>
    <div class="form-field <?php echo $has_icon ? 'has-icon' : ''; ?>">
        <?php if ($field->show_label): ?>
            <label for="<?php echo $field_name; ?>" style="<?php echo $field->hyperlink ? 'text-decoration: underline; color: ' . $styles['text_color'] . ';' : ''; ?>">
            <?php if ($field->hyperlink): ?>
                    <a href="<?php echo esc_url($field->hyperlink); ?>" target="_blank">
                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $field->field_name))); ?>
                    </a>
                <?php else: ?>
                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $field->field_name))); ?>
                <?php endif; ?>
                <?php if ($field->is_required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
        <?php endif; ?>

        <?php
        switch ($field_type) {
            case 'text':
            case 'email':
                ?>
                <div class="input-wrapper">
                    <?php if ($has_icon): ?>
                        <span class="input-icon"><?php echo $icon; ?></span>
                    <?php endif; ?>
                    <input type="<?php echo esc_attr($field_type); ?>" 
                           name="<?php echo $field_name; ?>" 
                           id="<?php echo $field_name; ?>" 
                           placeholder="<?php echo $placeholder_text; ?>" 
                           <?php echo $is_required; ?> 
                           style="<?php echo esc_attr($input_style); ?>" 
                           onmouseover="this.style='<?php echo esc_attr($input_style . $hover_inline); ?>'<?php if ($hover_effect === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                           onmouseout="this.style='<?php echo esc_attr($input_style); ?>'; this.classList.remove('underline-hover');"
                           data-icon-class="<?php echo esc_attr($icon_class); ?>">
                </div>
                <?php
                break;
            case 'tel':
                ?>
                <div class="input-wrapper">
                    <?php if ($has_icon): ?>
                        <span class="input-icon"><?php echo $icon; ?></span>
                    <?php endif; ?>
                    <input type="tel" 
                           name="<?php echo $field_name; ?>" 
                           id="<?php echo $field_name; ?>" 
                           placeholder="<?php echo $placeholder_text; ?>" 
                           <?php echo $is_required; ?> 
                           pattern="[0-9]{10}" 
                           maxlength="10" 
                           style="<?php echo esc_attr($input_style); ?>" 
                           onmouseover="this.style='<?php echo esc_attr($input_style . $hover_inline); ?>'<?php if ($hover_effect === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                           onmouseout="this.style='<?php echo esc_attr($input_style); ?>'; this.classList.remove('underline-hover');"
                           data-icon-class="<?php echo esc_attr($icon_class); ?>"
                           onkeypress="return (event.charCode !=8 && event.charCode ==0 || (event.charCode >= 48 && event.charCode <= 57))"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);">
                    </div>
                <script>
                    jQuery(document).ready(function($) {
                        $('#<?php echo $field_name; ?>').on('input', function() {
                            var value = $(this).val();
                            if (value.length > 10) {
                                $(this).val(value.slice(0, 10));
                            }
                            if (!/^\d{0,10}$/.test(value)) {
                                $(this).val(value.replace(/[^0-9]/g, ''));
                            }
                        });
                    });
                </script>
                <?php
                break;
            case 'phone':
                ?>
                <div class="input-wrapper">
                    <?php if ($has_icon): ?>
                        <span class="input-icon"><?php echo $icon; ?></span>
                    <?php endif; ?>
                    <input type="tel" 
                           name="<?php echo $field_name; ?>" 
                           id="<?php echo $field_name; ?>" 
                           placeholder="<?php echo $placeholder_text; ?>" 
                           <?php echo $is_required; ?> 
                           style="<?php echo esc_attr($input_style); ?>" 
                           onmouseover="this.style='<?php echo esc_attr($input_style . $hover_inline); ?>'<?php if ($hover_effect === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                           onmouseout="this.style='<?php echo esc_attr($input_style); ?>'; this.classList.remove('underline-hover');"
                           data-icon-class="<?php echo esc_attr($icon_class); ?>">
                </div>
                <?php
                break;
            case 'textarea':
                ?>
                <div class="input-wrapper" style="position: relative;">
                    <?php if ($has_icon): ?>
                        <span class="input-icon" style="position: absolute; margin: -10px; padding-left: 10px;"><?php echo $icon; ?></span>
                    <?php endif; ?>
                    <textarea name="<?php echo $field_name; ?>" 
                              id="<?php echo $field_name; ?>" 
                              placeholder="<?php echo $placeholder_text; ?>" 
                              <?php echo $is_required; ?> 
                              style="<?php echo esc_attr($input_style); ?>" 
                              onmouseover="this.style='<?php echo esc_attr($input_style . $hover_inline); ?>'<?php if ($hover_effect === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                              onmouseout="this.style='<?php echo esc_attr($input_style); ?>'; this.classList.remove('underline-hover');"
                              data-icon-class="<?php echo esc_attr($icon_class); ?>"></textarea>
                </div>
                <?php
                break;
            case 'select':
                $options = explode(",", trim($field->options));
                ?>
                <div class="input-wrapper">
                    <?php if ($has_icon): ?>
                        <span class="input-icon"><?php echo $icon; ?></span>
                    <?php endif; ?>
                    <select name="<?php echo $field_name; ?>" 
                            id="<?php echo $field_name; ?>" 
                            <?php echo $is_required; ?> 
                            style="<?php echo esc_attr($input_style); ?>" 
                            onmouseover="this.style='<?php echo esc_attr($input_style . $hover_inline); ?>'<?php if ($hover_effect === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                            onmouseout="this.style='<?php echo esc_attr($input_style); ?>'; this.classList.remove('underline-hover');"
                            data-icon-class="<?php echo esc_attr($icon_class); ?>">
                        <option value=""><?php echo $placeholder_text; ?></option>
                        <?php foreach ($options as $option): ?>
                            <option value="<?php echo esc_attr(trim($option)); ?>">
                                <?php echo esc_html(trim($option)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php
                break;
            case 'checkbox':
                $options = explode(",", trim($field->options));
                if (!empty($options)):
                    foreach ($options as $index => $option):
                        $option = trim($option);
                        ?>
                        <label style="<?php echo $field->hyperlink ? "text-decoration: underline; color: {$styles['text_color']};" : ''; ?>">
                        
                        <input type="checkbox" 
                                   name="<?php echo $field_name; ?>[]" 
                                   value="<?php echo esc_attr($option); ?>" 
                                   <?php echo $is_required && $index === 0 ? 'required' : ''; ?> 
                                   data-icon-class="<?php echo esc_attr($icon_class); ?>">
                            <?php if ($field->hyperlink): ?>
                                <a href="<?php echo esc_url($field->hyperlink); ?>" target="_blank">
                                    <?php echo esc_html($option); ?>
                                </a>
                            <?php else: ?>
                                <?php echo esc_html($option); ?>
                            <?php endif; ?>
                        </label><br>
                        <?php
                    endforeach;
                endif;
                break;
            case 'radio':
                $options = explode(",", trim($field->options));
                if (!empty($options)):
                    foreach ($options as $option):
                        $option = trim($option);
                        ?>
                        <label>
                            <input type="radio" 
                                   name="<?php echo $field_name; ?>" 
                                   value="<?php echo esc_attr($option); ?>" 
                                   <?php echo $is_required ? 'required' : ''; ?> 
                                   data-icon-class="<?php echo esc_attr($icon_class); ?>">
                            <?php echo esc_html($option); ?>
                        </label><br>
                        <?php
                    endforeach;
                endif;
                break;
            case 'date':
                ?>
                <div class="input-wrapper">
                    <?php if ($has_icon): ?>
                        <span class="input-icon"><?php echo $icon; ?></span>
                    <?php endif; ?>
                    <input type="date" 
                           name="<?php echo $field_name; ?>" 
                           id="<?php echo $field_name; ?>" 
                           placeholder="<?php echo $placeholder_text; ?>" 
                           <?php echo $is_required; ?> 
                           style="<?php echo esc_attr($input_style); ?>" 
                           onmouseover="this.style='<?php echo esc_attr($input_style . $hover_inline); ?>'<?php if ($hover_effect === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                           onmouseout="this.style='<?php echo esc_attr($input_style); ?>'; this.classList.remove('underline-hover');"
                           data-icon-class="<?php echo esc_attr($icon_class); ?>">
                </div>
                <?php
                break;
            case 'time':
                ?>
                <div class="input-wrapper">
                    <?php if ($has_icon): ?>
                        <span class="input-icon"><?php echo $icon; ?></span>
                    <?php endif; ?>
                    <input type="time" 
                           name="<?php echo $field_name; ?>" 
                           id="<?php echo $field_name; ?>" 
                           placeholder="<?php echo $placeholder_text; ?>" 
                           <?php echo $is_required; ?> 
                           style="<?php echo esc_attr($input_style); ?>" 
                           onmouseover="this.style='<?php echo esc_attr($input_style . $hover_inline); ?>'<?php if ($hover_effect === 'underline') echo " this.classList.add('underline-hover');"; ?>" 
                           onmouseout="this.style='<?php echo esc_attr($input_style); ?>'; this.classList.remove('underline-hover');"
                           data-icon-class="<?php echo esc_attr($icon_class); ?>">
                </div>
                <?php
                break;
        }
        ?>
    </div>
    <?php
}

// Modified save_form method in DynamicContactFormManager class
public function save_form() {
    if (!isset($_POST['dcfm_form_create']) || !wp_verify_nonce($_POST['dcfm_form_create'], 'dcfm_form_create')) {
        wp_redirect(add_query_arg('error', urlencode('Security check failed'), admin_url('admin.php?page=dcfm-add-form')));
        exit;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'dcfm_forms';

    $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
    $title = sanitize_text_field($_POST['form_title']);
    $fields = isset($_POST['field_order']) ? json_decode(stripslashes($_POST['field_order']), true) : [];
    $field_rows = isset($_POST['field_rows']) ? json_decode(stripslashes($_POST['field_rows']), true) : [];
    $submit_button_text = sanitize_text_field($_POST['submit_button_text']);
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 1;

    if (empty($title) || empty($submit_button_text)) {
        wp_redirect(add_query_arg('error', urlencode('Form title and submit button text are required'), 
                                 admin_url('admin.php?page=dcfm-add-form' . ($form_id ? '&edit=' . $form_id : ''))));
        exit;
    }

    if (isset($_POST['enable_multi_field_rows']) && empty($field_rows)) {
        wp_redirect(add_query_arg('error', urlencode('At least one row with fields is required when Multi-Field Rows is enabled'), 
                                 admin_url('admin.php?page=dcfm-add-form' . ($form_id ? '&edit=' . $form_id : ''))));
        exit;
    }

    if (!isset($_POST['enable_multi_field_rows']) && empty($fields)) {
        wp_redirect(add_query_arg('error', urlencode('At least one field is required when Multi-Field Rows is disabled'), 
                                 admin_url('admin.php?page=dcfm-add-form' . ($form_id ? '&edit=' . $form_id : ''))));
        exit;
    }

    $data = [
        'title' => $title,
        'fields' => json_encode($fields),
        'field_rows' => !empty($field_rows) && isset($_POST['enable_multi_field_rows']) ? json_encode($field_rows) : '',
        'shortcode' => '[contact_form id="' . ($form_id > 0 ? $form_id : 'NEW') . '"]',
        'submit_button_text' => $submit_button_text,
        'template_id' => $template_id
    ];

    if ($form_id > 0) {
        $result = $wpdb->update($table_name, $data, ['id' => $form_id], ['%s', '%s', '%s', '%s', '%s', '%d'], ['%d']);
        if ($result === false) {
            wp_redirect(add_query_arg('error', urlencode('Failed to update form: ' . $wpdb->last_error), 
                                     admin_url('admin.php?page=dcfm-add-form&edit=' . $form_id)));
            exit;
        } else {
            wp_redirect(add_query_arg('updated', urlencode('Form updated successfully'), 
                                     admin_url('admin.php?page=dcfm-forms')));
            exit;
        }
    } else {
        $result = $wpdb->insert($table_name, $data, ['%s', '%s', '%s', '%s', '%s', '%d']);
        if ($result === false) {
            wp_redirect(add_query_arg('error', urlencode('Failed to create form: ' . $wpdb->last_error), 
                                     admin_url('admin.php?page=dcfm-add-form')));
            exit;
        } else {
            $new_form_id = $wpdb->insert_id;
            $wpdb->update(
                $table_name,
                ['shortcode' => '[contact_form id="' . $new_form_id . '"]'],
                ['id' => $new_form_id],
                ['%s'],
                ['%d']
            );
            wp_redirect(add_query_arg('updated', urlencode('Form created successfully'), 
                                     admin_url('admin.php?page=dcfm-forms')));
            exit;
        }
    }
}
public function render_forms_page() {
        global $wpdb;
        $forms_table = $wpdb->prefix . 'dcfm_forms';
        $templates_table = $wpdb->prefix . 'dcfm_templates';
    
        if (isset($_POST['delete_form']) && isset($_POST['form_id'])) {
            check_admin_referer('dcfm_form_nonce_' . $_POST['form_id']);
    
            $form_id = intval($_POST['form_id']);
            $wpdb->delete($forms_table, ['id' => $form_id], ['%d']);
    
            echo '<div class="updated"><p>Form deleted successfully!</p></div>';
        }
    
        $forms = $wpdb->get_results("SELECT f.*, t.title AS template_title FROM {$forms_table} f LEFT JOIN {$templates_table} t ON f.template_id = t.id ORDER BY f.created_at DESC");
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
                            <th>Template</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forms as $form): ?>
                            <tr id="form-row-<?php echo esc_attr($form->id); ?>">
                                <td><?php echo esc_html($form->title); ?></td>
                                <td><code>[contact_form id="<?php echo esc_html($form->id); ?>"]</code></td>
                                <td><?php echo esc_html($form->created_at); ?></td>
                                <td><?php echo esc_html($form->template_title ?: 'Default Template'); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url("admin.php?page=dcfm-add-form&edit={$form->id}")); ?>">Edit</a> |
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('dcfm_form_nonce_' . $form->id); ?>
                                        <input type="hidden" name="form_id" value="<?php echo esc_attr($form->id); ?>">
                                        <button type="submit" name="delete_form" class="button" onclick="return confirm('Are you sure you want to delete this form?');">
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
    









// Render the edit template page




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
                $submission->created_at
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
        SELECT s.*, f.title as form_title
        FROM $submissions_table s
        JOIN $forms_table f ON s.form_id = f.id
        ORDER BY s.created_at DESC
    ");

    wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], '2.5.1', true);

    ?>
    <div class="wrap">
        <h1>Form Submissions</h1>
        <form method="post" style="margin-bottom: 20px;">
            <?php wp_nonce_field('csv_export', 'csv_export_nonce'); ?>
            <input type="submit" name="export_csv" class="button button-primary" value="Export as CSV">
            <button type="button" id="export-pdf" class="button button-primary">Export as PDF</button>
        </form>

        <?php if (empty($submissions)): ?>
            <p>No submissions found.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
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
                                        echo '<strong>' . esc_html(ucfirst($key)) . ':</strong> ' . esc_html($value) . '<br>';
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($submission->created_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('#export-pdf').click(function() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                doc.text('Form Submissions', 10, 10);
                let y = 20;

                <?php foreach ($submissions as $submission): ?>
                    doc.text('Form: <?php echo esc_js($submission->form_title); ?>', 10, y);
                    y += 10;
                    <?php
                    $data = json_decode($submission->submission_data, true);
                    if (is_array($data)) {
                        foreach ($data as $key => $value) {
                            ?>
                            doc.text('<?php echo esc_js(ucfirst($key)); ?>: <?php echo esc_js($value); ?>', 10, y);
                            y += 10;
                            <?php
                        }
                    }
                    ?>
                    doc.text('Date: <?php echo esc_js($submission->created_at); ?>', 10, y);
                    y += 20;
                <?php endforeach; ?>

                doc.save('form-submissions-<?php echo date('Y-m-d'); ?>.pdf');
            });
        });
    </script>
    <?php
}
public function render_email_settings_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dcfm_email_settings_nonce'])) {
        if (!wp_verify_nonce($_POST['dcfm_email_settings_nonce'], 'dcfm_email_settings')) {
            wp_die('Security check failed');
        }

        update_option('dcfm_admin_email', sanitize_email($_POST['admin_email']));
        update_option('dcfm_user_email_subject', sanitize_text_field($_POST['user_email_subject']));
        update_option('dcfm_admin_email_subject', sanitize_text_field($_POST['admin_email_subject']));
        
        $header_color = sanitize_text_field($_POST['email_header_color']);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $header_color)) {
            update_option('dcfm_email_header_color', $header_color);
        } else {
            echo '<div class="error"><p>Invalid hex color code. Please use a valid hex color (e.g., #RRGGBB or #RGB).</p></div>';
        }
        
        update_option('dcfm_user_email_header', wp_kses_post($_POST['user_email_header']));
        update_option('dcfm_user_email_footer', wp_kses_post($_POST['user_email_footer']));
        
        $test_emails = isset($_POST['test_emails']) ? sanitize_text_field($_POST['test_emails']) : '';
        $test_email_array = array_map('trim', explode(',', $test_emails));
        $valid_test_emails = array_filter($test_email_array, 'is_email');
        update_option('dcfm_test_emails', implode(',', $valid_test_emails));

        echo '<div class="updated"><p>Email settings saved successfully!</p></div>';
    }

    $admin_email = get_option('dcfm_admin_email', get_option('admin_email'));
    $user_email_subject = get_option('dcfm_user_email_subject', 'Thank you for contacting us!');
    $admin_email_subject = get_option('dcfm_admin_email_subject', 'New form submission received');
    $email_header_color = get_option('dcfm_email_header_color', '#2271b1');
    $user_email_header = get_option('dcfm_user_email_header', '<p>Thank you for reaching out to us. We have received your submission and will get back to you soon.</p><p>Here\'s a copy of the information you submitted:</p>');
    $user_email_footer = get_option('dcfm_user_email_footer', '<p style="color: #666; font-size: 12px; margin-top: 20px;">This is an automated response from ' . esc_html(get_bloginfo('name')) . '. Please do not reply to this email.</p>');
    $test_emails = get_option('dcfm_test_emails', '');

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
                    <th><label for="test_emails">Test Emails</label></th>
                    <td>
                        <input type="text" name="test_emails" id="test_emails" class="regular-text" 
                            value="<?php echo esc_attr($test_emails); ?>">
                        <p class="description">Enter comma-separated email addresses that are allowed to submit forms multiple times (e.g., test1@example.com,test2@example.com).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="user_email_subject">User Email Subject</label></th>
                    <td>
                        <input type="text" name="user_email_subject" id="user_email_subject" class="regular-text" 
                            value="<?php echo esc_attr($user_email_subject); ?>" required>
                        <p class="description">Subject line for the confirmation email sent to users.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="admin_email_subject">Admin Email Subject</label></th>
                    <td>
                        <input type="text" name="admin_email_subject" id="admin_email_subject" class="regular-text" 
                            value="<?php echo esc_attr($admin_email_subject); ?>" required>
                        <p class="description">Subject line for the notification email sent to the admin.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="email_header_color">Email Header Color</label></th>
                    <td>
                        <input type="text" name="email_header_color" id="email_header_color" class="regular-text" 
                            value="<?php echo esc_attr($email_header_color); ?>" 
                            pattern="^#[0-9a-fA-F]{3,6}$" 
                            placeholder="#RRGGBB or #RGB">
                        <p class="description">Enter a valid hex color code for the email header background (e.g., #2271b1).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="user_email_header">User Email Header</label></th>
                    <td>
                        <textarea name="user_email_header" id="user_email_header" class="large-text" rows="5"><?php echo esc_textarea($user_email_header); ?></textarea>
                        <p class="description">Header content for the user confirmation email. HTML is allowed.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="user_email_footer">User Email Footer</label></th>
                    <td>
                        <textarea name="user_email_footer" id="user_email_footer" class="large-text" rows="5"><?php echo esc_textarea($user_email_footer); ?></textarea>
                        <p class="description">Footer content for the user confirmation email. HTML is allowed.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="Save Email Settings">
                <button type="button" id="test-email" class="button">Send Test Email</button>
            </p>
        </form>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('#test-email').click(function() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'dcfm_test_email',
                        nonce: '<?php echo wp_create_nonce('dcfm_ajax_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                        } else {
                            alert('Test email failed: ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Test email failed: ' + error);
                    }
                });
            });
        });
    </script>
    <?php
}
public function render_style_settings_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dcfm_style_nonce'])) {
        if (!wp_verify_nonce($_POST['dcfm_style_nonce'], 'dcfm_style_settings')) {
            wp_die('Security check failed');
        }

        $custom_css = wp_kses_post($_POST['custom_css']);
        update_option('dcfm_custom_css', $custom_css);
        echo '<div class="updated"><p>Style settings saved successfully!</p></div>';
    }

    $custom_css = get_option('dcfm_custom_css', '');
    ?>
    <div class="wrap">
        <h1>Style Settings</h1>
        <form method="post">
            <?php wp_nonce_field('dcfm_style_settings', 'dcfm_style_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="custom_css">Custom CSS</label></th>
                    <td>
                        <textarea name="custom_css" id="custom_css" rows="10" cols="50" class="large-text"><?php echo esc_textarea($custom_css); ?></textarea>
                        <p class="description">Add custom CSS to style your forms. Use <code>.dcfm-form</code> to target forms.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="Save Styles">
            </p>
        </form>
    </div>
    <?php
}
public function register_shortcode() {
    add_shortcode('contact_form', [$this, 'render_form_shortcode']);
}
public function render_form_shortcode($atts) {
    $atts = shortcode_atts(['id' => 0], $atts, 'contact_form');
    $form_id = intval($atts['id']);

    if ($form_id <= 0) {
        return '<p>Invalid form ID.</p>';
    }

    return $this->render_form($form_id);
}
private function validate_form_submission($form_id, $data) {
    global $wpdb;
    $forms_table = $wpdb->prefix . 'dcfm_forms';
    $fields_table = $wpdb->prefix . 'dcfm_fields';

    $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM $forms_table WHERE id = %d", $form_id));
    if (!$form) {
        return ['success' => false, 'message' => 'Form not found.'];
    }

    $fields = json_decode($form->fields, true);
    $this->submission_errors = [];

    foreach ($fields as $field_id) {
        $field = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fields_table WHERE id = %d", $field_id));
        if (!$field) {
            continue;
        }

        $field_name = $field->field_name;
        $field_type = $field->field_type;

        if ($field->is_required && (!isset($data[$field_name]) || empty($data[$field_name]))) {
            $this->submission_errors[] = ucfirst($field_name) . ' is required.';
        }

        if ($field_type === 'email' && isset($data[$field_name]) && !empty($data[$field_name])) {
            if (!is_email($data[$field_name])) {
                $this->submission_errors[] = 'Please enter a valid email address for ' . ucfirst($field_name) . '.';
            }
        }
    }

    if (!isset($data['captcha_input']) || $data['captcha_input'] !== $data['captcha_answer']) {
        $this->submission_errors[] = 'Invalid CAPTCHA.';
    }

    if (!empty($this->submission_errors)) {
        return ['success' => false, 'message' => implode('<br>', $this->submission_errors)];
    }

    return ['success' => true];
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

    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    if ($email && $this->restrict_multiple_submissions($form_id, $email)) {
        $this->submission_errors[] = 'This email has already submitted this form.';
        return false;
    }

    $user_answer = isset($_POST['captcha_input']) ? trim(sanitize_text_field($_POST['captcha_input'])) : '';
    $correct_answer = isset($_POST['captcha_answer']) ? trim(sanitize_text_field($_POST['captcha_answer'])) : '';
    
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
// Add CSS and JavaScript to register_scripts method
public function register_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'dcfm-form-script',
        plugins_url('js/form-handler.js', __FILE__),
        ['jquery'],
        '1.2.6',
        true
    );

    wp_enqueue_style(
        'dcfm-form-styles',
        plugins_url('css/styles.css', __FILE__),
        [],
        '1.1.3'
    );

    // Load a minimal Bootstrap CSS (e.g., only form-related styles) to avoid conflicts
    // wp_enqueue_style(
    //     'bootstrap_css',
    //     'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    //     [],
    //     '5.3.0'
    // );

    wp_enqueue_style(
        'font_awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css',
        [],
        '6.4.2'
    );

    $custom_css = get_option('dcfm_custom_css', '');
    error_log('DCFM: Retrieved custom CSS from database: ' . (empty($custom_css) ? 'Empty' : $custom_css));

    if (!empty($custom_css)) {
        // Wrap custom CSS in a high-specificity selector to override Bootstrap
        $wrapped_css = ".dcfm-form-wrapper {\n" . $this->add_important_to_css($custom_css) . "\n}";
        wp_enqueue_style(
            'dcfm-custom-styles',
            plugins_url('css/custom-styles.css', __FILE__),
            ['dcfm-form-styles', 'bootstrap_css'],
            '1.1.3'
        );
        wp_add_inline_style('dcfm-custom-styles', $wrapped_css);
    }

    // Inline CSS to reset Bootstrap styles and ensure form-specific styling
    $inline_css = "
        .dcfm-form-wrapper {
            box-sizing: border-box;
            width: 100% !important;
            max-width: 100% !important;
        }
        .dcfm-form-wrapper .dcfm-form {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 auto !important;
        }
        .dcfm-form-wrapper .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .dcfm-form-wrapper .form-field {
            flex: 1 1 auto;
            min-width: 0;
        }
        .dcfm-form-wrapper .submit-button {
            width: auto;
        }
        .form-notification {
            position: relative;
            z-index: 1000;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 10px;
            color: #666;
            pointer-events: none;
        }
        .input-wrapper input,
        .input-wrapper textarea,
        .input-wrapper select {
            padding-left: 35px !important;
        }
        .form-field.has-icon input:focus + .input-icon,
        .form-field.has-icon textarea:focus + .input-icon,
        .form-field.has-icon select:focus + .input-icon {
            display: none;
        }
    ";
    wp_add_inline_style('dcfm-form-styles', $inline_css);

    wp_localize_script('dcfm-form-script', 'dcfmAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('dcfm_ajax_nonce')
    ]);

    // Inline script for CAPTCHA reset
    $captcha_script = "
        jQuery(document).ready(function($) {
            $('.captcha-reset').on('click', function() {
                var formId = $(this).data('form-id');
                var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                var captcha = '';
                for (var i = 0; i < 4; i++) {
                    captcha += characters.charAt(Math.floor(Math.random() * characters.length));
                }
                $('#captcha-display-' + formId).text(captcha);
                $('#captcha_answer-' + formId).val(captcha);
            });
        });
    ";
    wp_add_inline_script('dcfm-form-script', $captcha_script);
}
private function restrict_multiple_submissions($form_id, $email) {
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'dcfm_submissions';
        
        if (!is_email($email)) {
            return false;
        }
        
        // Get test emails from options
        $test_emails = get_option('dcfm_test_emails', '');
        $test_email_array = array_map('trim', explode(',', $test_emails));
        
        // If the email is in test emails, allow multiple submissions
        if (in_array($email, $test_email_array)) {
            error_log("DCFM: Allowing multiple submissions for test email: $email, form_id: $form_id");
            return false;
        }
        
        $existing_submission = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $submissions_table 
             WHERE form_id = %d 
             AND submission_data LIKE %s",
            $form_id,
            '%"' . sanitize_email($email) . '"%'
        ));
        
        if ($existing_submission > 0) {
            error_log("DCFM: Duplicate submission attempt blocked for email: $email, form_id: $form_id");
            return true;
        }
        
        return false;
    }
    }
    
    new DynamicContactFormManager();
?>