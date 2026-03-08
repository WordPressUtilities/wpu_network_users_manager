<?php
defined('ABSPATH') || die;

/* ----------------------------------------------------------
  Retrieve users
---------------------------------------------------------- */

$users = $this->get_users();
$users_rows = array();
foreach ($users as $user) {
    $users_rows[] = array(
        'id' => $user->ID,
        'login' => $user->user_login,
        'email' => $user->user_email,
        'actions' => '<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager&user_id=' . $user->ID)) . '">' . __('Edit', 'wpu_network_users_manager') . '</a>'
    );
}

/* ----------------------------------------------------------
  Display list
---------------------------------------------------------- */

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
        'actions' => __('Actions', 'wpu_network_users_manager')
    )
));
