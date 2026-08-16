<?php

if (!defined('ABSPATH')) exit;


// =============================================================
//  REST API 手动触发
//  访问 https://站点/wp-json/ppo-checkin/v1/run
//  需管理员登录
// =============================================================

add_action('rest_api_init', function () {
    register_rest_route('ppo-checkin/v1', '/run', [
        'methods'             => 'GET',
        'callback'            => 'ppo_checkin_rest_handler',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ]);
});

function ppo_checkin_rest_handler() {
    ppo_checkin_do_checkin();
    $last = get_option('ppo_checkin_last_result', []);
    return rest_ensure_response($last);
}
