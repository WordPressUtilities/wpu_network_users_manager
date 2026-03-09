<?php
defined('ABSPATH') || die;

/* ----------------------------------------------------------
  All roles
---------------------------------------------------------- */

echo '<hr />';
echo '<h3>' . __('Set all roles', 'wpu_network_users_manager') . '</h3>';
echo '<select id="wpu_set_all_role_select">';
echo '<option value=""> - </option>';
foreach ($roles as $role_key => $role_data) {
    echo '<option value="' . esc_attr($role_key) . '">' . esc_html($role_data['name']) . '</option>';
}
echo '</select>';
echo '<button type="button" class="button" id="wpu_set_all_role_button">' . __('Set all', 'wpu_network_users_manager') . '</button>';
echo '<script>
document.getElementById("wpu_set_all_role_button").addEventListener("click", function(e) {
    e.preventDefault();
    var select = document.getElementById("wpu_set_all_role_select");
    var selectedValue = select.value;
    var roleSelects = document.querySelectorAll("select[name^=\"role_\"]");
    roleSelects.forEach(function(roleSelect) {
        roleSelect.value = selectedValue;
    });
});
</script>';
