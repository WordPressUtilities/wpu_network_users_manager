<?php
defined('ABSPATH') || die;

/* ----------------------------------------------------------
  Save / apply user config
---------------------------------------------------------- */

$config = $this->get_config();

echo '<hr />';
echo '<h3>' . __('User config', 'wpu_network_users_manager') . '</h3>';

/* Save current blog roles as the config */
echo '<form method="post" action="' . esc_url(network_admin_url('users.php')) . '" style="display:inline-block;margin-right:1em;">';
echo '<input type="hidden" name="action" value="wpu_network_users_manager_save_config">';
echo '<input type="hidden" name="blog_id" value="' . esc_attr($blog_id) . '">';
wp_nonce_field('wpu_network_users_manager_save_config', 'wpu_network_users_manager_nonce');
submit_button(__('Save current roles as config', 'wpu_network_users_manager'), 'secondary', 'submit', false);
echo '</form>';

/* Apply the saved config to this blog (JS prefill, server save still required) */
if (empty($config)) {
    return;
}

echo '<button type="button" class="button" id="wpu_apply_config_button">' . __('Apply config', 'wpu_network_users_manager') . '</button>';
echo '<p id="wpu_apply_config_notice" style="display:none;"><em>' . esc_html__('Config applied to the form. Review the roles, then click "Save Changes" to persist.', 'wpu_network_users_manager') . '</em></p>';
echo '<script>';
echo '(function(){';
echo 'var config = ' . wp_json_encode((object) $config) . ';';
echo 'var btn = document.getElementById("wpu_apply_config_button");';
echo 'if (!btn) { return; }';
echo 'btn.addEventListener("click", function(e) {';
echo 'e.preventDefault();';
echo 'Object.keys(config).forEach(function(userId) {';
echo 'var sel = document.querySelector("select[name=\\"role_" + userId + "\\"]");';
echo 'if (sel) { sel.value = config[userId]; }';
echo '});';
echo 'var notice = document.getElementById("wpu_apply_config_notice");';
echo 'if (notice) { notice.style.display = "block"; }';
echo '});';
echo '})();';
echo '</script>';
