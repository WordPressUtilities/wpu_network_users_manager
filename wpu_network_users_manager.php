<?php
/*
Plugin Name: WPU Network Users Manager
Plugin URI: https://github.com/WordPressUtilities/wpu_network_users_manager
Update URI: https://github.com/WordPressUtilities/wpu_network_users_manager
Description: Add new user management features to the WP network admin
Version: 0.7.0
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_network_users_manager
Requires at least: 6.2
Requires PHP: 8.0
Domain Path: /lang
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

if (!defined('ABSPATH')) {
    exit;
}

class wpu_network_users_manager {
    public $basetoolbox;
    private $user_level = 'manage_network_users';
    private $plugin_name = 'Network Users Manager';
    private $config_option = 'wpu_network_users_manager_config';

    public function __construct() {
        if (!is_multisite() || !is_network_admin()) {
            return;
        }

        add_action('admin_init', array($this, 'load_translation'));
        add_action('admin_init', array($this, 'load_dependencies'));
        add_action('admin_init', array($this, 'save_user'));
        add_action('admin_init', array($this, 'save_blog_users'));
        add_action('admin_init', array($this, 'activate_signup'));
        add_action('admin_init', array($this, 'delete_signup'));
        add_action('admin_init', array($this, 'save_config'));
        add_action('admin_init', array($this, 'delete_config'));
        add_action('admin_init', array($this, 'remove_config_user'));
        add_action('network_admin_menu', array($this, 'admin_page'));
    }

    public function load_translation() {
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (strpos(__DIR__, 'mu-plugins') !== false) {
            load_muplugin_textdomain('wpu_network_users_manager', $lang_dir);
        } else {
            load_plugin_textdomain('wpu_network_users_manager', false, $lang_dir);
        }
        __('Add new user management features to the WP network admin', 'wpu_network_users_manager');
    }

    public function load_dependencies() {
        require_once __DIR__ . '/inc/WPUBaseToolbox/WPUBaseToolbox.php';
        $this->basetoolbox = new \wpu_network_users_manager\WPUBaseToolbox(array(
            'need_form_js' => false,
            'need_table_js' => true
        ));
    }

    /* ----------------------------------------------------------
      Admin page
    ---------------------------------------------------------- */

    public function admin_page() {
        add_submenu_page(
            'users.php',
            $this->plugin_name,
            $this->plugin_name,
            $this->user_level,
            'wpu_network_users_manager',
            array($this, 'admin_page_content')
        );
    }

    public function admin_page_content() {
        if (!current_user_can($this->user_level)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpu_network_users_manager'));
        }
        echo '<div class="wrap"><h1>' . esc_html($this->plugin_name) . '</h1>';
        if (isset($_GET['user_id'])) {
            require_once __DIR__ . '/inc/tpl/edit-user.php';
        } else if (isset($_GET['blog_id'])) {
            require_once __DIR__ . '/inc/tpl/edit-blog.php';
        } else if (isset($_GET['pending_users'])) {
            require_once __DIR__ . '/inc/tpl/list-pending-users.php';
        } else if (isset($_GET['show_list'])) {
            require_once __DIR__ . '/inc/tpl/show-list.php';
        } else if (isset($_GET['list_users'])) {
            require_once __DIR__ . '/inc/tpl/list-users.php';
        } else if (isset($_GET['list_blogs'])) {
            require_once __DIR__ . '/inc/tpl/list-blogs.php';
        } else {
            $this->admin_page_list();
        }
        echo '</div>';
    }

    /* ----------------------------------------------------------
      Blog users
    ---------------------------------------------------------- */

    public function save_blog_users() {
        if (!is_network_admin() || empty($_POST) || !isset($_POST['action']) || $_POST['action'] !== 'wpu_network_users_manager_save_blog') {
            return;
        }

        if (!current_user_can($this->user_level)) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'wpu_network_users_manager'));
        }

        if (!isset($_POST['wpu_network_users_manager_nonce']) || !wp_verify_nonce($_POST['wpu_network_users_manager_nonce'], 'wpu_network_users_manager_save_blog')) {
            wp_die(__('Invalid nonce. Please try again.', 'wpu_network_users_manager'));
        }

        $blog_id = intval($_POST['blog_id']);
        $blog_details = get_blog_details($blog_id);
        if (!$blog_details) {
            wp_die(__('Blog not found.', 'wpu_network_users_manager'));
        }

        $users = $this->get_users();
        $editable_roles = get_editable_roles();

        foreach ($users as $user) {
            $role_key = isset($_POST['role_' . $user->ID]) ? sanitize_text_field($_POST['role_' . $user->ID]) : '';
            if ($role_key) {
                if (!isset($editable_roles[$role_key])) {
                    wp_die(__('Invalid role selected for user: %s', 'wpu_network_users_manager'), $user->user_login);
                }
                add_user_to_blog($blog_id, $user->ID, $role_key);
            } else {
                remove_user_from_blog($user->ID, $blog_id);
            }
        }

        wp_redirect(network_admin_url('users.php?page=wpu_network_users_manager&blog_id=' . $blog_id . '&updated=1'));
        exit;
    }

    /* ----------------------------------------------------------
      Pending users (signups)
    ---------------------------------------------------------- */

    public function get_pending_signups() {
        global $wpdb;
        return $wpdb->get_results("SELECT signup_id, user_login, user_email, registered, activation_key FROM {$wpdb->signups} WHERE domain = '' AND active = '0' ORDER BY registered DESC");
    }

    public function activate_signup() {
        if (!is_network_admin() || empty($_POST) || !isset($_POST['action']) || $_POST['action'] !== 'wpu_network_users_manager_activate_signup') {
            return;
        }

        if (!current_user_can($this->user_level)) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'wpu_network_users_manager'));
        }

        if (!isset($_POST['wpu_network_users_manager_nonce']) || !wp_verify_nonce($_POST['wpu_network_users_manager_nonce'], 'wpu_network_users_manager_activate_signup')) {
            wp_die(__('Invalid nonce. Please try again.', 'wpu_network_users_manager'));
        }

        global $wpdb;
        $signup_id = intval($_POST['signup_id']);
        $signup = $wpdb->get_row($wpdb->prepare("SELECT activation_key FROM {$wpdb->signups} WHERE signup_id = %d AND domain = '' AND active = '0'", $signup_id));
        if (!$signup) {
            wp_die(__('Signup not found.', 'wpu_network_users_manager'));
        }

        /* Activate silently: no welcome email to the user */
        add_filter('wpmu_welcome_user_notification', '__return_false');
        $result = wpmu_activate_signup($signup->activation_key);
        remove_filter('wpmu_welcome_user_notification', '__return_false');

        $notice = is_wp_error($result) ? 'activate_error=1' : 'activated=1';
        wp_redirect(network_admin_url('users.php?page=wpu_network_users_manager&pending_users&' . $notice));
        exit;
    }

    public function delete_signup() {
        if (!is_network_admin() || empty($_POST) || !isset($_POST['action']) || $_POST['action'] !== 'wpu_network_users_manager_delete_signup') {
            return;
        }

        if (!current_user_can($this->user_level)) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'wpu_network_users_manager'));
        }

        if (!isset($_POST['wpu_network_users_manager_nonce']) || !wp_verify_nonce($_POST['wpu_network_users_manager_nonce'], 'wpu_network_users_manager_delete_signup')) {
            wp_die(__('Invalid nonce. Please try again.', 'wpu_network_users_manager'));
        }

        global $wpdb;
        $signup_id = intval($_POST['signup_id']);
        $wpdb->delete($wpdb->signups, array('signup_id' => $signup_id), array('%d'));

        wp_redirect(network_admin_url('users.php?page=wpu_network_users_manager&pending_users&deleted=1'));
        exit;
    }

    /* ----------------------------------------------------------
      Config
    ---------------------------------------------------------- */

    public function get_config() {
        $config = get_site_option($this->config_option, array());
        return is_array($config) ? $config : array();
    }

    public function save_config() {
        if (!is_network_admin() || empty($_POST) || !isset($_POST['action']) || $_POST['action'] !== 'wpu_network_users_manager_save_config') {
            return;
        }

        if (!current_user_can($this->user_level)) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'wpu_network_users_manager'));
        }

        if (!isset($_POST['wpu_network_users_manager_nonce']) || !wp_verify_nonce($_POST['wpu_network_users_manager_nonce'], 'wpu_network_users_manager_save_config')) {
            wp_die(__('Invalid nonce. Please try again.', 'wpu_network_users_manager'));
        }

        $blog_id = intval($_POST['blog_id']);
        $blog_details = get_blog_details($blog_id);
        if (!$blog_details) {
            wp_die(__('Blog not found.', 'wpu_network_users_manager'));
        }

        $users = $this->get_users();
        $config = array();
        foreach ($users as $user) {
            $user_roles = $this->get_user_roles_on_blog($user->ID, $blog_id);
            if (!empty($user_roles)) {
                $config[$user->ID] = $user_roles[0];
            }
        }

        update_site_option($this->config_option, $config);

        wp_redirect(network_admin_url('users.php?page=wpu_network_users_manager&blog_id=' . $blog_id . '&config_saved=1'));
        exit;
    }

    public function delete_config() {
        if (!is_network_admin() || empty($_POST) || !isset($_POST['action']) || $_POST['action'] !== 'wpu_network_users_manager_delete_config') {
            return;
        }

        if (!current_user_can($this->user_level)) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'wpu_network_users_manager'));
        }

        if (!isset($_POST['wpu_network_users_manager_nonce']) || !wp_verify_nonce($_POST['wpu_network_users_manager_nonce'], 'wpu_network_users_manager_delete_config')) {
            wp_die(__('Invalid nonce. Please try again.', 'wpu_network_users_manager'));
        }

        delete_site_option($this->config_option);

        wp_redirect(network_admin_url('users.php?page=wpu_network_users_manager&config_deleted=1'));
        exit;
    }

    public function remove_config_user() {
        if (!is_network_admin() || empty($_POST) || !isset($_POST['action']) || $_POST['action'] !== 'wpu_network_users_manager_remove_config_user') {
            return;
        }

        if (!current_user_can($this->user_level)) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'wpu_network_users_manager'));
        }

        if (!isset($_POST['wpu_network_users_manager_nonce']) || !wp_verify_nonce($_POST['wpu_network_users_manager_nonce'], 'wpu_network_users_manager_remove_config_user')) {
            wp_die(__('Invalid nonce. Please try again.', 'wpu_network_users_manager'));
        }

        $user_id = intval($_POST['remove_user_id']);
        $config = $this->get_config();
        if (isset($config[$user_id])) {
            unset($config[$user_id]);
            if (empty($config)) {
                delete_site_option($this->config_option);
            } else {
                update_site_option($this->config_option, $config);
            }
        }

        wp_redirect(network_admin_url('users.php?page=wpu_network_users_manager&config_user_removed=1'));
        exit;
    }

    /* ----------------------------------------------------------
      User
    ---------------------------------------------------------- */

    public function save_user() {

        if (!is_network_admin() || empty($_POST) || !isset($_POST['action']) || $_POST['action'] !== 'wpu_network_users_manager_save_user') {
            return;
        }

        if (!current_user_can($this->user_level)) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'wpu_network_users_manager'));
        }

        if (!isset($_POST['wpu_network_users_manager_nonce']) || !wp_verify_nonce($_POST['wpu_network_users_manager_nonce'], 'wpu_network_users_manager_save_user')) {
            wp_die(__('Invalid nonce. Please try again.', 'wpu_network_users_manager'));
        }

        $user_id = intval($_POST['user_id']);
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_die(__('User not found.', 'wpu_network_users_manager'));
        }

        $blogs = $this->get_blogs();
        $editable_roles = get_editable_roles();

        foreach ($blogs as $blog) {
            $blog_id = $blog->blog_id;
            $role_key = isset($_POST['role_' . $blog_id]) ? sanitize_text_field($_POST['role_' . $blog_id]) : '';
            if ($role_key) {
                if (!isset($editable_roles[$role_key])) {
                    wp_die(__('Invalid role selected for user: %s', 'wpu_network_users_manager'), $user->user_login);
                }
                add_user_to_blog($blog_id, $user_id, $role_key);
            } else {
                remove_user_from_blog($user_id, $blog_id);
            }
        }

        wp_redirect(network_admin_url('users.php?page=wpu_network_users_manager&user_id=' . $user_id . '&updated=1'));
        exit;
    }

    /* ----------------------------------------------------------
      List
    ---------------------------------------------------------- */

    private function admin_page_list() {
        if (isset($_GET['config_deleted']) && $_GET['config_deleted'] == '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Config deleted.', 'wpu_network_users_manager') . '</p></div>';
        }

        if (isset($_GET['config_user_removed']) && $_GET['config_user_removed'] == '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('User removed from config.', 'wpu_network_users_manager') . '</p></div>';
        }

        echo '<h2>' . __('Users list', 'wpu_network_users_manager') . '</h2>';
        echo wpautop('<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager&list_users')) . '" class="button">' . __('View list', 'wpu_network_users_manager') . '</a>');

        echo '<hr />';
        echo '<h2>' . __('Sites list', 'wpu_network_users_manager') . '</h2>';
        echo wpautop('<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager&list_blogs')) . '" class="button">' . __('View list', 'wpu_network_users_manager') . '</a>');

        echo '<hr />';
        echo '<h2>' . __('Sites and their users', 'wpu_network_users_manager') . '</h2>';
        echo wpautop('<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager&show_list=1')) . '" class="button">' . __('View list', 'wpu_network_users_manager') . '</a>');

        echo '<hr />';
        $pending_count = count($this->get_pending_signups());
        echo '<h2>' . __('Pending users', 'wpu_network_users_manager') . ' (' . intval($pending_count) . ')</h2>';
        echo wpautop('<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager&pending_users')) . '" class="button">' . __('View list', 'wpu_network_users_manager') . '</a>');

        echo '<hr />';
        echo '<h2>' . __('Saved user config', 'wpu_network_users_manager') . '</h2>';
        $config = $this->get_config();
        if (empty($config)) {
            echo wpautop(__('No config saved yet. Save one from a site page.', 'wpu_network_users_manager'));
            return;
        }

        $editable_roles = get_editable_roles();
        $confirm_msg = esc_js(__('Remove this user from the config?', 'wpu_network_users_manager'));

        $config_open = false;
        if(isset($_GET['config_deleted']) || isset($_GET['config_user_removed'])) {
            $config_open = true;
        }

        echo '<details style="margin-bottom: 1em;" ' . ($config_open ? 'open' : '') . '>';
        echo '<summary>' . __('View config', 'wpu_network_users_manager') . ' (' . count($config) . ')</summary>';
        echo '<form method="post" action="' . esc_url(network_admin_url('users.php')) . '">';
        echo '<input type="hidden" name="action" value="wpu_network_users_manager_remove_config_user">';
        wp_nonce_field('wpu_network_users_manager_remove_config_user', 'wpu_network_users_manager_nonce');
        echo '<ul class="ul-disc">';
        foreach ($config as $user_id => $role_key) {
            $user = get_user_by('ID', $user_id);
            $role_name = isset($editable_roles[$role_key]) ? $editable_roles[$role_key]['name'] : $role_key;
            $label = $user ? esc_html($user->user_login) : '<em>' . sprintf(__('Deleted user #%d', 'wpu_network_users_manager'), intval($user_id)) . '</em>';
            echo '<li>';
            echo $label . ' &mdash; ' . esc_html($role_name) . ' ';
            echo '<button type="submit" name="remove_user_id" value="' . esc_attr($user_id) . '" class="button-link delete" onclick="return confirm(\'' . $confirm_msg . '\');">' . __('Remove', 'wpu_network_users_manager') . '</button>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</form>';
        echo '</details>';

        echo '<form method="post" action="' . esc_url(network_admin_url('users.php')) . '">';
        echo '<input type="hidden" name="action" value="wpu_network_users_manager_delete_config">';
        wp_nonce_field('wpu_network_users_manager_delete_config', 'wpu_network_users_manager_nonce');
        submit_button(__('Delete config', 'wpu_network_users_manager'), 'delete', 'submit', false);
        echo '</form>';
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    public function get_user_roles_on_blog($user_id, $blog_id) {
        global $wpdb;
        $table = $wpdb->get_blog_prefix($blog_id) . 'capabilities';
        $user_meta = get_user_meta($user_id, $table, true);
        if (is_array($user_meta)) {
            return array_keys($user_meta);
        }
        return [];
    }

    public function get_blogs() {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT blog_id, domain, path FROM {$wpdb->blogs} WHERE site_id = %d AND archived = '0' AND spam = '0' AND deleted = '0' ORDER BY blog_id ASC",
            $wpdb->siteid
        );
        return $wpdb->get_results($query);
    }

    public function get_users() {
        return get_users([
            'blog_id' => 0,
            'orderby' => 'login',
            'order' => 'ASC',
            'fields' => [
                'ID',
                'user_login',
                'display_name',
                'user_email'
            ],
            'number' => -1
        ]);

    }

}

$wpu_network_users_manager = new wpu_network_users_manager();
