<?php

if (!defined('ABSPATH')) exit;


// =============================================================
//  执行签到（核心逻辑，返回数组）
// =============================================================

function ppo_checkin_do_checkin_raw($settings) {
    if (empty($settings['cookie']) || empty($settings['url'])) {
        return ['success' => false, 'message' => __('配置不完整', 'pixpro-checkin')];
    }

    $ajax_url = ppo_checkin_build_ajax_url($settings);
    $resolve  = ppo_checkin_get_resolve_ip($settings);

    $result = ppo_checkin_curl_direct($ajax_url, $settings['cookie'], $resolve, 30);
    $http_code = $result['code'];
    $body      = $result['body'] ?? '';
    $error     = $result['error'] ?? '';

    if (!empty($error) || empty($http_code)) {
        return ['success' => false, 'message' => sprintf(__('curl 请求失败：%s', 'pixpro-checkin'), $error ?: 'HTTP 状态码 0')];
    }

    if ($http_code !== 200) {
        return ['success' => false, 'message' => sprintf(__('HTTP 状态码异常：%d', 'pixpro-checkin'), $http_code)];
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        if (stripos($body, 'login') !== false) {
            return ['success' => false, 'message' => __('Cookie 已失效或未登录，请重新获取', 'pixpro-checkin')];
        }
        return ['success' => false, 'message' => __('响应解析失败，返回非 JSON 数据', 'pixpro-checkin')];
    }

    $msg = $data['msg'] ?? '';

    if ($msg === '签到成功') {
        return [
            'success' => true,
            'message' => $msg,
            'xp'      => intval($data['xp'] ?? 0),
            'point'   => intval($data['point'] ?? 0),
        ];
    } elseif ($msg === '今日已签到') {
        return ['success' => true, 'message' => $msg];
    }

    return ['success' => false, 'message' => $msg ?: wp_json_encode($data, JSON_UNESCAPED_UNICODE)];
}

// WP Cron 调用入口（自动记录日志 + 邮件通知 + 一键任务）
function ppo_checkin_do_checkin() {
    $settings = ppo_checkin_get_settings();
    $result   = ppo_checkin_do_checkin_raw($settings);

    $log = [
        'success' => !empty($result['success']),
        'time'    => wp_date('Y-m-d H:i:s'),
        'message' => $result['message'] ?? __('未知错误', 'pixpro-checkin'),
    ];
    if (isset($result['xp']))    $log['xp']    = $result['xp'];
    if (isset($result['point'])) $log['point'] = $result['point'];

    if (!empty($result['success'])) {
        $log['checkin_msg'] = '✅ 签到成功';

        // 主动任务（有任意一项配置即执行，未配置的部分内部自动跳过）
        $moment_id  = absint($settings['moment_id'] ?? 0);
        $comment_id = absint($settings['comment_id'] ?? 0);
        $follow_id  = absint($settings['follow_id'] ?? 0);
        $msg_id     = absint($settings['msg_id'] ?? 0);
        $resolve    = ppo_checkin_get_resolve_ip($settings);
        $log['active_msg'] = '';
        if ($moment_id || $comment_id || $follow_id || $msg_id) {
            try {
                $task_ret = ppo_checkin_run_tasks($settings['cookie'], $settings['url'], $resolve, $moment_id, $comment_id, 10, $follow_id, 5, $msg_id);
                $log['active_msg'] = $task_ret['message'];
            } catch (\Throwable $e) {
                $log['active_msg'] = '❌ 主动任务异常：' . $e->getMessage();
            }
        }

        // 被动任务（第二个账号）
        $second_cookie     = $settings['second_cookie'] ?? '';
        $target_follow_id  = absint($settings['target_follow_id'] ?? 0);
        $target_comment_id = absint($settings['target_comment_id'] ?? 0);
        $log['passive_msg'] = '';
        if ($second_cookie && ($target_follow_id || $target_comment_id)) {
            try {
                $passive_ret = ppo_checkin_run_passive_tasks(
                    $second_cookie, $settings['url'], $resolve,
                    $target_follow_id, $target_comment_id, 5, 10
                );
                $log['passive_msg'] = $passive_ret['message'];
            } catch (\Throwable $e) {
                $log['passive_msg'] = '❌ 被动任务异常：' . $e->getMessage();
            }
        }
    }

    update_option('ppo_checkin_last_result', $log);

    // 失败通知只关注“签到本身是否成功”，主动/被动任务失败不再触发邮件打扰。
    if (empty($result['success'])) {
        ppo_checkin_send_notify($result['message'] ?? __('未知错误', 'pixpro-checkin'));
    }
}

// WP Cron 回调
add_action('ppo_checkin_cron_hook', 'ppo_checkin_do_checkin');

// =============================================================
//  发送邮件通知
// =============================================================

function ppo_checkin_send_notify($message) {
    $settings = ppo_checkin_get_settings();
    if (empty($settings['notify'])) return;

    $admin_email = get_option('admin_email');
    if (!$admin_email) return;

    $subject = sprintf(
        __('[%s] PixPro 签到失败提醒', 'pixpro-checkin'),
        wp_specialchars_decode(get_option('blogname'), ENT_QUOTES)
    );

    $body = sprintf(
        __("%s\n\n━━━━━━━━━━━━━━━━━━\n目标站点：%s\n失败原因：%s\n执行时间：%s\n━━━━━━━━━━━━━━━━━━\n\n请登录后台重新设置 Cookie。", 'pixpro-checkin'),
        $subject,
        $settings['url'],
        $message,
        wp_date('Y-m-d H:i:s')
    );

    wp_mail($admin_email, $subject, $body, [
        'Content-Type: text/plain; charset=UTF-8',
    ]);
}
