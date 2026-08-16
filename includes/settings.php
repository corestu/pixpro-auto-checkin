<?php

if (!defined('ABSPATH')) exit;


// =============================================================
//  表单注册 & 保存
// =============================================================

add_action('admin_init', 'ppo_checkin_handle_actions');
function ppo_checkin_handle_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }
    register_setting('ppo_checkin_group', 'ppo_checkin_settings', [
        'sanitize_callback' => 'ppo_checkin_sanitize_settings',
    ]);
}

function ppo_checkin_sanitize_settings($input) {
    $settings = ppo_checkin_get_settings();

    // 支持“部分保存”：表单里没提交的字段保留原值，不必每次把所有配置都填一遍。
    if (array_key_exists('cookie', $input)) {
        $settings['cookie'] = wp_unslash($input['cookie']);
    }
    if (array_key_exists('url', $input)) {
        $settings['url'] = esc_url_raw($input['url'], ['http', 'https']);
    }
    if (array_key_exists('notify', $input)) {
        $settings['notify'] = !empty($input['notify']) ? 1 : 0;
    }

    // IP 验证
    if (array_key_exists('ip', $input)) {
        $raw_ip = trim($input['ip']);
        $settings['ip'] = ($raw_ip !== '' && filter_var($raw_ip, FILTER_VALIDATE_IP)) ? $raw_ip : '';
    }

    if (array_key_exists('moment_id', $input)) {
        $settings['moment_id'] = max(0, intval($input['moment_id']));
    }
    if (array_key_exists('comment_id', $input)) {
        $settings['comment_id'] = max(0, intval($input['comment_id']));
    }
    if (array_key_exists('follow_id', $input)) {
        $settings['follow_id'] = max(0, intval($input['follow_id']));
    }

    if (array_key_exists('second_cookie', $input)) {
        $settings['second_cookie'] = wp_unslash($input['second_cookie']);
    }
    if (array_key_exists('target_follow_id', $input)) {
        $settings['target_follow_id'] = max(0, intval($input['target_follow_id']));
    }
    if (array_key_exists('target_comment_id', $input)) {
        $settings['target_comment_id'] = max(0, intval($input['target_comment_id']));
    }
    if (array_key_exists('msg_id', $input)) {
        $settings['msg_id'] = max(0, intval($input['msg_id']));
    }

    // 保存自定义执行时/分
    if (array_key_exists('cron_hour', $input)) {
        $hour = min(23, max(0, intval($input['cron_hour'])));
        update_option('ppo_checkin_cron_hour', $hour);
    }
    if (array_key_exists('cron_minute', $input)) {
        $minute = min(59, max(0, intval($input['cron_minute'])));
        update_option('ppo_checkin_cron_minute', $minute);
    }

    ppo_checkin_schedule_cron();

    return $settings;
}

// =============================================================
//  Cron 调度（支持自定义时/分 + 随机秒数）
// =============================================================

function ppo_checkin_schedule_cron() {
    $hook = 'ppo_checkin_cron_hook';

    $old_timestamp = wp_next_scheduled($hook);
    if ($old_timestamp) {
        wp_unschedule_event($old_timestamp, $hook);
    }

    $hour   = (int) get_option('ppo_checkin_cron_hour', 8);
    $minute = (int) get_option('ppo_checkin_cron_minute', 0);
    $second = wp_rand(0, 59);

    $now  = time();
    $tz   = wp_timezone();
    $next = (new DateTime('now', $tz))
        ->setTime($hour, $minute, $second)
        ->getTimestamp();
    if ($next <= $now) {
        $next = (new DateTime('+1 day', $tz))
            ->setTime($hour, $minute, $second)
            ->getTimestamp();
    }

    wp_schedule_event($next, 'daily', $hook);
}
// =============================================================
//  获取设置
// =============================================================

function ppo_checkin_get_settings() {
    $defaults = [
        'cookie'            => '',
        'url'               => '',
        'ip'                => '',
        'notify'            => 1,
        'moment_id'         => 0,
        'comment_id'        => 0,
        'follow_id'         => 0,
        'second_cookie'     => '',
        'target_follow_id'  => 0,
        'target_comment_id' => 0,
        'msg_id'            => 0,
    ];
    $saved = get_option(PPO_CHECKIN_OPTION, []);
    if (!is_array($saved)) $saved = [];
    return wp_parse_args($saved, $defaults);
}

// =============================================================
//  插件激活 / 停用
// =============================================================

register_activation_hook(PPO_CHECKIN_FILE, 'ppo_checkin_activate');
function ppo_checkin_activate() {
    ppo_checkin_schedule_cron();
}

register_deactivation_hook(PPO_CHECKIN_FILE, 'ppo_checkin_deactivate');
function ppo_checkin_deactivate() {
    $timestamp = wp_next_scheduled('ppo_checkin_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'ppo_checkin_cron_hook');
    }
}

// 卸载时清理所有数据
register_uninstall_hook(PPO_CHECKIN_FILE, 'ppo_checkin_uninstall');
function ppo_checkin_uninstall() {
    delete_option(PPO_CHECKIN_OPTION);
    delete_option('ppo_checkin_last_result');
    delete_option('ppo_checkin_cron_hour');
    delete_option('ppo_checkin_cron_minute');
}
