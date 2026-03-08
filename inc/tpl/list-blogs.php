<?php
defined('ABSPATH') || die;
/* ----------------------------------------------------------
  Retrieve blogs
---------------------------------------------------------- */

$blogs = $this->get_blogs();
$blogs_rows = array();
foreach ($blogs as $blog) {
    $blogs_rows[] = array(
        'id' => $blog->blog_id,
        'name' => get_blog_option($blog->blog_id, 'blogname'),
        'url' => get_site_url($blog->blog_id),
        'actions' => '<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager&blog_id=' . $blog->blog_id)) . '">' . __('View users', 'wpu_network_users_manager') . '</a>'
    );
}

/* ----------------------------------------------------------
  Display list
---------------------------------------------------------- */

echo $this->basetoolbox->array_to_html_table($blogs_rows, array(
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
        'name' => __('Name', 'wpu_network_users_manager'),
        'url' => __('URL', 'wpu_network_users_manager'),
        'actions' => __('Actions', 'wpu_network_users_manager')
    )
));
