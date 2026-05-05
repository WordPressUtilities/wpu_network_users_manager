<?php
defined('ABSPATH') || die;

/* ----------------------------------------------------------
  Retrieve blogs and their users
---------------------------------------------------------- */

echo '<h2>' . __('Sites and their users', 'wpu_network_users_manager') . '</h2>';
echo '<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager')) . '" class="button">' . __('Back to sites list', 'wpu_network_users_manager') . '</a>';
echo '<hr />';

$blogs = $this->get_blogs();

foreach ($blogs as $blog) {
    $blog_id = (int) $blog->blog_id;
    $blog_name = get_blog_option($blog_id, 'blogname');
    $blog_url = get_site_url($blog_id);

    echo '<h3>';
    echo '#' . esc_html($blog_id) . ' &mdash; ' . esc_html($blog_name);
    echo ' <small>(<a href="' . esc_url($blog_url) . '" target="_blank" rel="noopener">' . esc_html($blog_url) . '</a>)</small>';
    echo '</h3>';

    $users = get_users(array(
        'blog_id' => $blog_id,
        'orderby' => 'display_name',
        'order' => 'ASC',
        'number' => -1
    ));

    if (empty($users)) {
        echo '<p><em>' . esc_html__('No users.', 'wpu_network_users_manager') . '</em></p>';
        continue;
    }

    $users_rows = array();
    foreach ($users as $user) {
        $roles = !empty($user->roles) ? implode(', ', $user->roles) : '&mdash;';
        $users_rows[] = array(
            'name' => esc_html($user->display_name),
            'email' => '<a href="mailto:' . esc_attr($user->user_email) . '">' . esc_html($user->user_email) . '</a>',
            'roles' => esc_html($roles)
        );
    }

    echo $this->basetoolbox->array_to_html_table($users_rows, array(
        'table_classname' => 'wp-list-table wpubasetoolbox-table-sort widefat fixed striped users',
        'htmlspecialchars_td' => false,
        'htmlspecialchars_th' => false,
        'colnames' => array(
            'name' => __('Name', 'wpu_network_users_manager'),
            'email' => __('Email', 'wpu_network_users_manager'),
            'roles' => __('Role(s)', 'wpu_network_users_manager')
        )
    ));
}
