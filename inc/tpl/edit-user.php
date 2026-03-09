<?php
defined('ABSPATH') || die;
/* ----------------------------------------------------------
  Load infos
---------------------------------------------------------- */

$user_id = intval($_GET['user_id']);
$user = get_user_by('ID', $user_id);
if (!$user) {
    echo wpautop(__('User not found.', 'wpu_network_users_manager'));
    return;
}

/* ----------------------------------------------------------
  Heading
---------------------------------------------------------- */

echo '<h2>' . sprintf(__('Edit user #%d : %s', 'wpu_network_users_manager'), $user->ID, esc_html($user->display_name)) . '</h2>';

if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('User updated successfully.', 'wpu_network_users_manager') . '</p></div>';
}

echo '<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager')) . '" class="button">' . __('Back to users list', 'wpu_network_users_manager') . '</a>';
echo '<hr />';

/* ----------------------------------------------------------
  Retrieve blogs and roles
---------------------------------------------------------- */

$blogs = $this->get_blogs();

if (!$blogs) {
    echo wpautop(__('No blogs found.', 'wpu_network_users_manager'));
    return;
}

$blogs_rows = array();
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

    $html_select = '<select name="role_' . esc_attr($blog_id) . '">';
    $html_select .= '<option value="">-</option>';
    foreach ($roles as $role_key => $role_data) {
        $html_select .= '<option value="' . esc_attr($role_key) . '" ' . selected($current_role, $role_key, false) . '>' . esc_html($role_data['name']) . '</option>';
    }
    $html_select .= '</select>';
    $blogs_rows[] = array(
        'id' => $blog_id,
        'name' => $blog_name,
        'url' => $blog_url,
        'role_select' => $html_select
    );
}

/* ----------------------------------------------------------
  Display form and table
---------------------------------------------------------- */

echo '<form method="post" action="' . esc_url(network_admin_url('users.php')) . '">';
echo '<input type="hidden" name="action" value="wpu_network_users_manager_save_user">';
echo '<input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '">';

echo $this->basetoolbox->array_to_html_table($blogs_rows, array(
    'table_classname' => 'wp-list-table widefat fixed striped sites',
    'htmlspecialchars_td' => false,
    'htmlspecialchars_th' => false,
    'colnames' => array(
        'id' => array(
            'label' => __('ID', 'wpu_network_users_manager'),
            'attributes' => array(
                'width' => 20
            )
        ),
        'name' => __('Blog Name', 'wpu_network_users_manager'),
        'url' => __('Blog URL', 'wpu_network_users_manager'),
        'role_select' => __('Role', 'wpu_network_users_manager')
    )
));

wp_nonce_field('wpu_network_users_manager_save_user', 'wpu_network_users_manager_nonce');
submit_button(__('Save Changes', 'wpu_network_users_manager'));
echo '</form>';

include __DIR__ . '/set-all-roles.php';
