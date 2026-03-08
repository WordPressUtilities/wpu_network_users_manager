<?php
/*
Plugin Name: WPU Network Users Manager
Plugin URI: https://github.com/WordPressUtilities/wpu_network_users_manager
Update URI: https://github.com/WordPressUtilities/wpu_network_users_manager
Description: Add new user management features to the WP network admin
Version: 0.2.0
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

    public function __construct() {
        if (!is_multisite() || !is_network_admin()) {
            return;
        }

        add_action('admin_init', array($this, 'load_translation'));
        add_action('admin_init', array($this, 'load_dependencies'));
        add_action('admin_init', array($this, 'save_user'));
        add_action('admin_init', array($this, 'save_blog_users'));
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
            'need_form_js' => false
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

        foreach ($users as $user) {
            $role_key = isset($_POST['role_' . $user->ID]) ? sanitize_text_field($_POST['role_' . $user->ID]) : '';
            if ($role_key) {
                add_user_to_blog($blog_id, $user->ID, $role_key);
            } else {
                remove_user_from_blog($user->ID, $blog_id);
            }
        }

        wp_redirect(network_admin_url('users.php?page=wpu_network_users_manager&blog_id=' . $blog_id . '&updated=1'));
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

        foreach ($blogs as $blog) {
            $blog_id = $blog->blog_id;
            $role_key = isset($_POST['role_' . $blog_id]) ? sanitize_text_field($_POST['role_' . $blog_id]) : '';
            if ($role_key) {
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
        echo '<h2>' . __('Users list', 'wpu_network_users_manager') . '</h2>';
        echo '<details>';
        echo '<summary>' . __('View list', 'wpu_network_users_manager') . '</summary>';
        require_once __DIR__ . '/inc/tpl/list-users.php';
        echo '</details>';
        echo '<hr />';
        echo '<h2>' . __('Sites list', 'wpu_network_users_manager') . '</h2>';
        echo '<details>';
        echo '<summary>' . __('View list', 'wpu_network_users_manager') . '</summary>';
        require_once __DIR__ . '/inc/tpl/list-blogs.php';
        echo '</details>';
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
