<?php
defined('ABSPATH') || die;

/* ----------------------------------------------------------
  Load infos
---------------------------------------------------------- */

$blog_id = intval($_GET['blog_id']);
$blog_details = get_blog_details($blog_id);
if (!$blog_details) {
    echo wpautop(__('Blog not found.', 'wpu_network_users_manager'));
    return;
}

/* ----------------------------------------------------------
  Heading
---------------------------------------------------------- */

echo '<h2>' . sprintf(__('Users of blog #%d : %s', 'wpu_network_users_manager'), $blog_details->blog_id, esc_html($blog_details->blogname)) . '</h2>';
echo '<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager')) . '" class="button">' . __('Back to sites list', 'wpu_network_users_manager') . '</a>';
echo '<hr />';

if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Blog updated successfully.', 'wpu_network_users_manager') . '</p></div>';
}

/* ----------------------------------------------------------
  Retrieve users and roles
---------------------------------------------------------- */

$users = $this->get_users();

if (!$users) {
    echo wpautop(__('No users found.', 'wpu_network_users_manager'));
    return;
}

$roles = [];
$blog_details = get_blog_details($blog_id);
if ($blog_details) {
    $roles = get_editable_roles();
}

$users_rows = array();
foreach ($users as $user) {
    /* User details */
    $user_roles = $this->get_user_roles_on_blog($user->ID, $blog_id);
    $current_role = !empty($user_roles) ? $user_roles[0] : '';
    $html_select = '<select name="role_' . esc_attr($user->ID) . '">';
    $html_select .= '<option value="" ' . selected($current_role, '', false) . '> - </option>';
    foreach ($roles as $role_key => $role_data) {
        $html_select .= '<option value="' . esc_attr($role_key) . '" ' . selected($current_role, $role_key, false) . '>' . esc_html($role_data['name']) . '</option>';
    }
    $html_select .= '</select>';
    $users_rows[] = array(
        'id' => $user->ID,
        'login' => $user->user_login,
        'email' => $user->user_email,
        'role_select' => $html_select
    );
}

/* ----------------------------------------------------------
  Display form and table
---------------------------------------------------------- */

echo '<form method="post" action="' . esc_url(network_admin_url('users.php')) . '">';
echo '<input type="hidden" name="action" value="wpu_network_users_manager_save_blog">';
echo '<input type="hidden" name="blog_id" value="' . esc_attr($blog_id) . '">';

echo $this->basetoolbox->array_to_html_table($users_rows, array(
    'table_classname' => 'wp-list-table widefat fixed striped users',
    'htmlspecialchars_td' => false,
    'htmlspecialchars_th' => false,
    'colnames' => array(
        'id' => array(
            'label' => __('ID', 'wpu_network_users_manager'),
            'attributes' => array(
                'width' => 20
            )
        ),
        'login' => __('Login', 'wpu_network_users_manager'),
        'email' => __('Email', 'wpu_network_users_manager'),
        'role_select' => __('Role', 'wpu_network_users_manager')
    )
));

wp_nonce_field('wpu_network_users_manager_save_blog', 'wpu_network_users_manager_nonce');
submit_button(__('Save Changes', 'wpu_network_users_manager'));
echo '</form>';
