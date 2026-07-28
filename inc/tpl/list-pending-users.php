<?php
defined('ABSPATH') || die;

echo '<h2>' . __('Pending users', 'wpu_network_users_manager') . '</h2>';
echo '<a href="' . esc_url(network_admin_url('users.php?page=wpu_network_users_manager')) . '" class="button">' . __('Back', 'wpu_network_users_manager') . '</a>';
echo '<hr />';

/* ----------------------------------------------------------
  Notices
---------------------------------------------------------- */

if (isset($_GET['activated'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('User activated.', 'wpu_network_users_manager') . '</p></div>';
}
if (isset($_GET['activate_error'])) {
    echo '<div class="notice notice-error is-dismissible"><p>' . __('Could not activate this user (already activated?).', 'wpu_network_users_manager') . '</p></div>';
}
if (isset($_GET['deleted'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Pending user deleted.', 'wpu_network_users_manager') . '</p></div>';
}

/* ----------------------------------------------------------
  Retrieve pending signups
---------------------------------------------------------- */

$signups = $this->get_pending_signups();
if (empty($signups)) {
    echo wpautop(__('No pending user.', 'wpu_network_users_manager'));
    return;
}

$base_url = esc_url(network_admin_url('users.php'));
$confirm_msg = esc_js(__('Delete this pending user?', 'wpu_network_users_manager'));

$signups_rows = array();
foreach ($signups as $signup) {

    $activate = '<form method="post" action="' . $base_url . '" style="display:inline;">';
    $activate .= '<input type="hidden" name="action" value="wpu_network_users_manager_activate_signup">';
    $activate .= '<input type="hidden" name="signup_id" value="' . esc_attr($signup->signup_id) . '">';
    $activate .= wp_nonce_field('wpu_network_users_manager_activate_signup', 'wpu_network_users_manager_nonce', true, false);
    $activate .= '<button type="submit" class="button button-primary">' . __('Activate', 'wpu_network_users_manager') . '</button>';
    $activate .= '</form>';

    $delete = '<form method="post" action="' . $base_url . '" style="display:inline; margin-left:.5em;">';
    $delete .= '<input type="hidden" name="action" value="wpu_network_users_manager_delete_signup">';
    $delete .= '<input type="hidden" name="signup_id" value="' . esc_attr($signup->signup_id) . '">';
    $delete .= wp_nonce_field('wpu_network_users_manager_delete_signup', 'wpu_network_users_manager_nonce', true, false);
    $delete .= '<button type="submit" class="button button-secondary delete" onclick="return confirm(\'' . $confirm_msg . '\');">' . __('Delete', 'wpu_network_users_manager') . '</button>';
    $delete .= '</form>';

    $signups_rows[] = array(
        'login' => esc_html($signup->user_login),
        'email' => esc_html($signup->user_email),
        'registered' => esc_html($signup->registered),
        'actions' => $activate . $delete
    );
}

/* ----------------------------------------------------------
  Display list
---------------------------------------------------------- */

echo $this->basetoolbox->array_to_html_table($signups_rows, array(
    'table_classname' => 'wp-list-table wpubasetoolbox-table-sort widefat fixed striped users',
    'htmlspecialchars_td' => false,
    'htmlspecialchars_th' => false,
    'colnames' => array(
        'login' => __('Login', 'wpu_network_users_manager'),
        'email' => __('Email', 'wpu_network_users_manager'),
        'registered' => __('Registered', 'wpu_network_users_manager'),
        'actions' => __('Actions', 'wpu_network_users_manager')
    )
));
