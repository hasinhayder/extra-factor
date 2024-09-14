<?php
/*
 * Plugin Name: Extra Factor
 * Description: A plugin that enhances the WordPress login process by adding an extra layer of security through a two-factor authentication mechanism. Users will receive a unique code via email that must be entered to complete the login process, ensuring that only authorized users can access their accounts.
 * Version: 1.0
 * Author: Hasin Hayder
 * Author URI: https://github.com/hasinhayder
 * Text Domain: extra-factor
 * Domain Path: /languages
 * License: GPL2
 */

class ExtraFactor {
    const VERSION = '1.0';
    const NONCE_ACTION = 'extra-factor-nonce';
    const META_LAST_SENT = 'last_sent';
    const META_LOGIN_CODE = 'login_code';
    const META_EXTRA_FACTOR = 'extra_factor';
    const EXTRA_FACTOR_TIMEOUT = 600;

    public function __construct() {
        add_action('login_form', [$this, 'add_extra_factor_input']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_nopriv_send_extra_factor_code', [$this, 'send_extra_factor_code']);
        add_filter('authenticate', [$this, 'authenticate'], 10, 3);
        add_action('wp_logout', [$this, 'clean_user_meta']);
        add_action('init', [$this, 'check_extra_factor_meta']);
    }

    public function check_extra_factor_meta() {
        $user_id = get_current_user_id();
        if ($user_id && !get_user_meta($user_id, self::META_EXTRA_FACTOR, true)) {
            add_user_meta($user_id, self::META_EXTRA_FACTOR, 1, true);
            wp_logout();
        }
    }

    public function add_extra_factor_input() {
        ?>
        <p id="extra_factor" style="display: none;">
            <label for="extra_factor"><?php _e('Email Code', 'extra-factor'); ?></label>
            <input type="text" name="extra_factor" id="extra_factor" class="input" value="" size="20">
        </p>
        <?php
    }

    public function enqueue_scripts() {
        wp_enqueue_script('extra-factor', plugin_dir_url(__FILE__) . 'assets/js/scripts.js', ['jquery'], self::VERSION, true);
        wp_localize_script('extra-factor', 'extraFactor', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'message' => __('Please check your email for the extra factor code.', 'extra-factor')
        ]);
    }

    public function send_extra_factor_code() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $username = sanitize_user($_POST['username']);

        if (empty($username)) {
            wp_die();
        }

        $user = get_user_by('login', $username);

        if (!$user) {
            wp_die();
        }

        $email = $user->user_email;
        $code = apply_filters('extra_factor_generate_code', rand(1000, 9999));

        $subject = apply_filters('extra_factor_email_subject', __('Extra Factor Login Code', 'extra-factor'));
        $message = apply_filters('extra_factor_email_message', sprintf(__('Your extra factor login code is: %s', 'extra-factor'), $code));

        $last_sent = get_user_meta($user->ID, self::META_LAST_SENT, true);
        $current_time = current_time('timestamp');

        $timeout = apply_filters('extra_factor_timeout', self::EXTRA_FACTOR_TIMEOUT);

        if ($last_sent && ($current_time - $last_sent < $timeout)) {
            wp_send_json_error(__('Please wait before requesting a new code.', 'extra-factor'));
        } else {
            update_user_meta($user->ID, self::META_LAST_SENT, $current_time);
            update_user_meta($user->ID, self::META_LOGIN_CODE, $code);
            if (wp_mail($email, $subject, $message)) {
                wp_send_json_success(__('Code sent successfully. Please check your email.', 'extra-factor'));
            } else {
                wp_send_json_error(__('Failed to send email. Please try again.', 'extra-factor'));
            }
        }
        wp_die();
    }

    public function authenticate($user, $username, $password) {
        if (empty($username) || empty($password)) {
            return $user;
        }

        if (empty($_POST['extra_factor'])) {
            return $this->block_login();
        }

        $extra_factor = sanitize_text_field($_POST['extra_factor']);
        $user = get_user_by('login', $username);
        $login_code = get_user_meta($user->ID, self::META_LOGIN_CODE, true);

        if ($extra_factor !== $login_code) {
            return $this->block_login();
        }

        delete_user_meta($user->ID, self::META_LOGIN_CODE);
        delete_user_meta($user->ID, self::META_LAST_SENT);

        return $user;
    }

    private function block_login() {
        remove_action('authenticate', 'wp_authenticate_username_password', 20);
        remove_action('authenticate', 'wp_authenticate_email_password', 20);
        return new WP_Error('extra_factor_error', __('Invalid code. Please check your email for the correct code.', 'extra-factor'));
    }

    public function clean_user_meta() {
        delete_user_meta(get_current_user_id(), self::META_LOGIN_CODE);
        delete_user_meta(get_current_user_id(), self::META_LAST_SENT);
    }
}

$extra_factor = new ExtraFactor();
