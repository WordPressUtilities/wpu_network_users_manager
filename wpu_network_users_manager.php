<?php
/*
Plugin Name: WPU Network Users Manager
Plugin URI: https://github.com/WordPressUtilities/wpu_network_users_manager
Update URI: https://github.com/WordPressUtilities/wpu_network_users_manager
Description: Add new user management features to the WP network admin
Version: 0.1.0
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_network_users_manager
Requires at least: 6.2
Requires PHP: 8.0
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

if (!defined('ABSPATH')) {
    exit;
}

class wpu_network_users_manager {
    private $user_level = 'manage_network_users';
    private $plugin_name = 'Network Users Manager';

    public function __construct() {
        if (!is_multisite()) {
            return;
        }

        add_action('admin_init', array($this, 'load_translation'));
        add_action('admin_init', array($this, 'save_user'));
        add_action('network_admin_menu', array($this, 'admin_page'));
    }

    function load_translation() {
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (strpos(__DIR__, 'mu-plugins') !== false) {
            load_muplugin_textdomain('wpu_network_users_manager', $lang_dir);
        } else {
            load_plugin_textdomain('wpu_network_users_manager', false, $lang_dir);
        }
        __('Add new user management features to the WP network admin', 'wpu_network_users_manager');
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
            $this->admin_page_user();
        } else {
            $this->admin_page_list();
        }
        echo '</div>';
    }

    /* ----------------------------------------------------------
      User
    ---------------------------------------------------------- */

    private function admin_page_user() {
        $user_id = intval($_GET['user_id']);
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            echo wpautop(__('User not found.', 'wpu_network_users_manager'));
            return;
        }
        echo '<h2>' . sprintf(__('Edit user #%d : %s', 'wpu_network_users_manager'), $user->ID, esc_html($user->display_name)) . '</h2>';

        if (isset($_GET['updated']) && $_GET['updated'] == '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('User updated successfully.', 'wpu_network_users_manager') . '</p></div>';
        }

        echo '<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager')) . '" class="button">' . __('Back to users list', 'wpu_network_users_manager') . '</a>';
        echo '<hr>';

        global $wpdb;
        $blogs = $this->get_blogs();

        if (!$blogs) {
            echo wpautop(__('No blogs found.', 'wpu_network_users_manager'));
            return;
        }

        echo '<form method="post" action="' . esc_url(network_admin_url('users.php')) . '">';
        echo '<input type="hidden" name="action" value="wpu_network_users_manager_save_user">';
        echo '<input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th scope="col" width="50">ID</th>';
        echo '<th scope="col">' . __('Blog Name', 'wpu_network_users_manager') . '</th>';
        echo '<th scope="col">' . __('Blog URL', 'wpu_network_users_manager') . '</th>';
        echo '<th scope="col">' . __('Role', 'wpu_network_users_manager') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($blogs as $blog) {
            /* Blog details */
            $blog_id = $blog->blog_id;
            $blog_url = get_site_url($blog_id);
            $blog_name = get_blog_option($blog_id, 'blogname');

            /* Roles */
            $user_roles = $this->get_user_roles_on_blog($user->ID, $blog_id);
            $current_role = !empty($user_roles) ? $user_roles[0] : '';

            $roles = [];
            $blog_details = get_blog_details($blog_id);
            if ($blog_details) {
                $roles = get_editable_roles();
            }
            echo '<tr>';
            echo '<td>' . esc_html($blog_id) . '</td>';
            echo '<td>' . esc_html($blog_name) . '</td>';
            echo '<td><a href="' . esc_url($blog_url) . '" target="_blank">' . esc_html($blog_url) . '</a></td>';
            echo '<td>';
            echo '<select name="role_' . esc_attr($blog_id) . '">';
            echo '<option value="">-</option>';
            foreach ($roles as $role_key => $role_data) {
                echo '<option value="' . esc_attr($role_key) . '" ' . selected($current_role, $role_key, false) . '>' . esc_html($role_data['name']) . '</option>';
            }
            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        wp_nonce_field('wpu_network_users_manager_save_user', 'wpu_network_users_manager_nonce');
        submit_button(__('Save Changes', 'wpu_network_users_manager'));
        echo '</form>';

    }

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

        $users = $this->get_users();

        echo '<h2>' . __('Users list', 'wpu_network_users_manager') . '</h2>';

        echo '<table class="wp-list-table widefat fixed striped users">';
        echo '<thead><tr>';
        echo '<th scope="col" width="20" class="manage-column">' . __('ID', 'wpu_network_users_manager') . '</th>';
        echo '<th scope="col" class="manage-column">' . __('Login', 'wpu_network_users_manager') . '</th>';
        echo '<th scope="col" class="manage-column">' . __('Email', 'wpu_network_users_manager') . '</th>';
        echo '<th scope="col" class="manage-column">' . __('Actions', 'wpu_network_users_manager') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($users as $user) {
            echo '<tr>';
            echo '<td>' . esc_html($user->ID) . '</td>';
            echo '<td>' . esc_html($user->user_login) . '</td>';
            echo '<td>' . esc_html($user->user_email) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager&user_id=' . $user->ID)) . '">Edit</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    function get_user_roles_on_blog($user_id, $blog_id) {
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
