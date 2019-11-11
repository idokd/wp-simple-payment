<?php

defined( 'ABSPATH' ) or exit;

// Make sure Invisibile Recaptcha is active
if (!in_array('invisible-recaptcha/invisible-recaptcha.php', apply_filters('active_plugins', get_option('active_plugins')))) 
    return;
    
add_action('sp_form_render', 'sp_recaptcha_form_render');
add_filter('sp_form_validation', 'sp_recaptcha_form_validation');

function sp_recaptcha_form_render() {
    do_action(‘google_invre_render_widget_action’);
}

function sp_recaptcha_form_validation($value) {
    $is_valid = apply_filters(‘google_invre_is_valid_request_filter’, true);
    return($value && $is_valid);
}