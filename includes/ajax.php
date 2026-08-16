<?php

if (!defined('ABSPATH')) exit;


// =============================================================
//  AJAX 处理器
// =============================================================

// 连接测试（不消耗每日签到，只检测连通性）
add_action('wp_ajax_ppo_checkin_conn_test', 'ppo_checkin_ajax_conn_test');
function ppo_checkin_ajax_conn_test() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['lines' => ['⛔ 权限不足']]);
    }
    check_ajax_referer('ppo_checkin_conn_test');

    $cookie = wp_unslash($_POST['cookie'] ?? '');
    $url    = esc_url_raw($_POST['url'] ?? '', ['http', 'https']);
    $ip     = trim(sanitize_text_field($_POST['ip'] ?? ''));
    $lines  = [];

    if (!$cookie || !$url) {
        wp_send_json_error(['lines' => ['⚠️ 请先填写 Cookie 和目标站点 URL']]);
    }

    $url = rtrim($url, '/');
    $resolve = ppo_checkin_get_resolve_ip(['url' => $url, 'ip' => $ip]);

    // 步骤一：站点连通性（只做 GET，不触发签到）
    $home = ppo_checkin_curl_request($url . '/', $cookie, 'GET', '', [], $resolve, 20);
    if (empty($home['code']) && !empty($home['error'])) {
        $lines[] = '❌ 站点无法访问：' . $home['error'];
        wp_send_json_error(['lines' => $lines]);
    }
    if ($home['code'] > 0) {
        $lines[] = '✅ 站点可达（HTTP ' . $home['code'] . '）';
    } else {
        $lines[] = '❌ 站点无响应';
        wp_send_json_error(['lines' => $lines]);
    }

    // 步骤二：Cookie 登录态验证（不消耗签到）
    // 优先通过页面里的 uid 判断；若拿不到再通过 REST users/me 二次确认。
    $page      = ppo_checkin_fetch_page_nonces($url, $cookie, $resolve);
    $logged_in = !empty($page['uid']);

    if (!$logged_in) {
        $rest_nonce = ppo_checkin_fetch_rest_nonce($url, $cookie, $resolve);
        if ($rest_nonce) {
            $me = ppo_checkin_curl_request(
                $url . '/wp-json/wp/v2/users/me',
                $cookie,
                'GET',
                '',
                ['X-WP-Nonce: ' . $rest_nonce],
                $resolve,
                15
            );
            $me_data = json_decode($me['body'] ?? '', true);
            if (is_array($me_data) && !empty($me_data['id'])) {
                $logged_in = true;
            }
        }
    }

    if ($logged_in) {
        $lines[] = '✅ Cookie 有效，已登录';
    } else {
        $lines[] = '❌ Cookie 无效或未登录，请重新获取';
    }

    // 步骤三：Cookie 过期时间
    if (preg_match('/wordpress_(?:logged_in|sec)_[^=]+=([^;]+)/', $cookie, $m)) {
        $parts = explode('|', $m[1]);
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $expiry    = (int) $parts[1];
            $remaining = $expiry - time();
            $lines[]   = '';
            if ($remaining > 0) {
                $days = floor($remaining / 86400);
                $lines[] = '🍪 Cookie 有效期：约 ' . $days . ' 天后（' . wp_date('Y-m-d H:i:s', $expiry) . '）';
            } else {
                $lines[] = '⚠️ Cookie 已过期！';
            }
        }
    }

    if ($logged_in) {
        wp_send_json_success(['lines' => $lines]);
    }

    wp_send_json_error(['lines' => $lines]);
}

// AJAX 手动签到
add_action('wp_ajax_ppo_checkin_manual_ajax', 'ppo_checkin_ajax_manual_checkin');
function ppo_checkin_ajax_manual_checkin() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['lines' => ['⛔ 权限不足']]);
    }
    check_ajax_referer('ppo_checkin_manual_ajax');

    $cookie = wp_unslash($_POST['cookie'] ?? '');
    $url    = rtrim(esc_url_raw($_POST['url'] ?? '', ['http', 'https']), '/');
    $ip     = trim(sanitize_text_field($_POST['ip'] ?? ''));

    $settings = ppo_checkin_get_settings();
    if ($cookie) $settings['cookie'] = $cookie;
    if ($url)    $settings['url']    = $url;
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) $settings['ip'] = $ip;

    $result = ppo_checkin_do_checkin_raw($settings);
    $lines  = [];

    // 保存签到记录（各阶段日志分开存放）
    $log = [
        'success' => !empty($result['success']),
        'time'    => wp_date('Y-m-d H:i:s'),
        'message' => $result['message'] ?? __('未知错误', 'pixpro-checkin'),
    ];
    if (isset($result['xp']))    $log['xp']    = $result['xp'];
    if (isset($result['point'])) $log['point'] = $result['point'];

    if (!empty($result['success'])) {
        $lines[] = '✅ 签到成功';
        $log['checkin_msg'] = '✅ 签到成功';
    } else {
        $lines[] = '❌ ' . ($result['message'] ?? '签到失败');
        update_option('ppo_checkin_last_result', $log);
        wp_send_json_error(['lines' => $lines]);
    }

    // 签到成功后执行主动任务（未配置的部分自动跳过）
    $moment_id  = absint($settings['moment_id'] ?? 0);
    $comment_id = absint($settings['comment_id'] ?? 0);
    $follow_id  = absint($settings['follow_id'] ?? 0);
    $msg_id     = absint($settings['msg_id'] ?? 0);
    $resolve    = ppo_checkin_get_resolve_ip($settings);
    $task_ret   = ['total_fail' => 0];
    $log['active_msg'] = '';
    if ($moment_id || $comment_id || $follow_id || $msg_id) {
        $task_ret = ppo_checkin_run_tasks($settings['cookie'], $url, $resolve, $moment_id, $comment_id, 10, $follow_id, 5, $msg_id);
        $lines[]  = $task_ret['message'];
        $log['active_msg'] = $task_ret['message'];
    }

    // 被动任务（第二个账号）
    $second_cookie     = $settings['second_cookie'] ?? '';
    $target_follow_id  = absint($settings['target_follow_id'] ?? 0);
    $target_comment_id = absint($settings['target_comment_id'] ?? 0);
    $passive_ret       = ['total_fail' => 0];
    $log['passive_msg'] = '';
    if ($second_cookie && ($target_follow_id || $target_comment_id)) {
        $passive_ret = ppo_checkin_run_passive_tasks(
            $second_cookie, $url, $resolve,
            $target_follow_id, $target_comment_id, 5, 10
        );
        $lines[] = $passive_ret['message'];
        $log['passive_msg'] = $passive_ret['message'];
    }

    update_option('ppo_checkin_last_result', $log);

    if (($task_ret['total_fail'] ?? 0) > 0 || ($passive_ret['total_fail'] ?? 0) > 0) {
        wp_send_json_error(['lines' => $lines]);
    } else {
        wp_send_json_success(['lines' => $lines]);
    }
}

// AJAX 清除记录
add_action('wp_ajax_ppo_checkin_clear_log_ajax', 'ppo_checkin_ajax_clear_log');
function ppo_checkin_ajax_clear_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['lines' => ['⛔ 权限不足']]);
    }
    check_ajax_referer('ppo_checkin_clear_log_ajax');

    delete_option('ppo_checkin_last_result');
    wp_send_json_success(['lines' => ['🗑️ 记录已清除']]);
}

// AJAX 测试邮件
add_action('wp_ajax_ppo_checkin_test_mail_ajax', 'ppo_checkin_ajax_test_mail');
function ppo_checkin_ajax_test_mail() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['lines' => ['⛔ 权限不足']]);
    }
    check_ajax_referer('ppo_checkin_test_mail_ajax');

    $admin_email = get_option('admin_email');
    $sent = wp_mail(
        $admin_email,
        '【PixPro 签到】测试邮件',
        "这是一封测试邮件，确认邮件通知功能正常。\n\n如果您收到此邮件，说明配置正确。",
        ['Content-Type: text/plain; charset=UTF-8']
    );

    if ($sent) {
        wp_send_json_success(['lines' => ['✅ 测试邮件已发送到：' . esc_html($admin_email)]]);
    } else {
        wp_send_json_error(['lines' => ['❌ 邮件发送失败，请检查服务器邮件配置']]);
    }
}
// AJAX 一键任务
add_action('wp_ajax_ppo_checkin_oneclick_task', 'ppo_checkin_ajax_oneclick_task');
function ppo_checkin_ajax_oneclick_task() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['lines' => ['⛔ 权限不足']]);
    }
    check_ajax_referer('ppo_checkin_oneclick_task');

    $cookie     = wp_unslash($_POST['cookie'] ?? '');
    $url        = rtrim(esc_url_raw($_POST['url'] ?? '', ['http', 'https']), '/');
    $ip         = trim(sanitize_text_field($_POST['ip'] ?? ''));
    $moment_id  = absint($_POST['moment_id'] ?? 0);
    $comment_id = absint($_POST['comment_id'] ?? 0);

    if (!$cookie || !$url) {
        wp_send_json_error(['lines' => ['⚠️ 请先填写 Cookie 和目标站点 URL']]);
    }
    if (!$moment_id)  wp_send_json_error(['lines' => ['⚠️ 请先填写片刻 ID']]);
    if (!$comment_id) wp_send_json_error(['lines' => ['⚠️ 请先填写评论 ID']]);

    $resolve = ppo_checkin_get_resolve_ip(['ip' => $ip, 'url' => $url]);

    $lines = [];

    // 用 run_tasks 跑 1 轮（nonce 获取和执行方式与 cron 完全一致）
    $settings = ppo_checkin_get_settings();
    $follow_id = absint($settings['follow_id'] ?? 0);
    $msg_id    = absint($settings['msg_id'] ?? 0);
    $task_ret  = ppo_checkin_run_tasks($cookie, $url, $resolve, $moment_id, $comment_id, 1, $follow_id, 1, $msg_id, 1);

    // 解析返回的多行消息，拆成独立行显示
    $msg_parts = explode("\n", $task_ret['message']);
    foreach ($msg_parts as $line) {
        $lines[] = $line;
    }

    if ($task_ret['total_fail'] === 0) {
        $lines[] = '═════════════════';
        $lines[] = '🎉 全部操作成功，配置可用';
        wp_send_json_success(['lines' => $lines]);
    } else {
        $lines[] = '⚠️ 有 ' . $task_ret['total_fail'] . ' 个操作失败，请检查配置';
        wp_send_json_error(['lines' => $lines]);
    }
}

// =============================================================
//  被动任务测试 AJAX
// =============================================================

add_action('wp_ajax_ppo_checkin_passive_task', 'ppo_checkin_ajax_passive_task');
function ppo_checkin_ajax_passive_task() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['lines' => ['⛔ 权限不足']]);
    }
    check_ajax_referer('ppo_checkin_passive_task');

    $cookie     = wp_unslash($_POST['second_cookie'] ?? '');
    $url        = rtrim(esc_url_raw($_POST['url'] ?? '', ['http', 'https']), '/');
    $ip         = trim(sanitize_text_field($_POST['ip'] ?? ''));
    $target_follow_id  = absint($_POST['target_follow_id'] ?? 0);
    $target_comment_id = absint($_POST['target_comment_id'] ?? 0);

    if (!$cookie || !$url) {
        wp_send_json_error(['lines' => ['⚠️ 请先填写第二个账号 Cookie 和目标站点 URL']]);
    }

    $resolve = ppo_checkin_get_resolve_ip(['ip' => $ip, 'url' => $url]);

    $lines = [];

    // 被动关注测试（1轮）
    if ($target_follow_id) {
        $ret = ppo_checkin_run_follow_cycle($cookie, $url, $resolve, $target_follow_id);
        if ($ret['fail'] === 0) {
            $lines[] = '👤 被动关注操作... ✅';
        } else {
            $lines[] = '⚠️ 被动关注操作失败，请检查被关注用户 ID';
            wp_send_json_error(['lines' => $lines]);
        }
    } else {
        $lines[] = '⏭️ 被动关注：未填写被关注用户 ID，已跳过';
    }

    // 被动评论点赞测试（1轮）
    if ($target_comment_id) {
        $ret2 = ppo_checkin_run_passive_comment_cycle($cookie, $url, $resolve, $target_comment_id);
        if ($ret2['fail'] === 0) {
            $lines[] = '💬 被动评论点赞操作... ✅';
        } else {
            $lines[] = '⚠️ 被动评论点赞操作失败，请检查被点赞评论 ID';
            wp_send_json_error(['lines' => $lines]);
        }
    } else {
        $lines[] = '⏭️ 被动评论点赞：未填写被点赞评论 ID，已跳过';
    }

    $lines[] = '═════════════════';
    $lines[] = '🎉 被动任务测试通过，第二个账号配置可用';
    wp_send_json_success(['lines' => $lines]);
}

// =============================================================
//  补挂区 AJAX 处理器
// =============================================================

add_action('wp_ajax_ppo_checkin_rerun_task', 'ppo_checkin_ajax_rerun_task');
function ppo_checkin_ajax_rerun_task() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['lines' => ['⛔ 权限不足']]);
    }
    check_ajax_referer('ppo_checkin_rerun_task');

    $operation = $_POST['operation'] ?? '';
    $times     = max(1, intval($_POST['times'] ?? 1));
    $cookie    = wp_unslash($_POST['cookie'] ?? '');
    $url       = rtrim(esc_url_raw($_POST['url'] ?? '', ['http', 'https']), '/');
    $ip        = trim(sanitize_text_field($_POST['ip'] ?? ''));
    $second_cookie = wp_unslash($_POST['second_cookie'] ?? '');

    if (!$cookie || !$url) {
        wp_send_json_error(['lines' => ['⚠️ 请先填写 Cookie 和目标站点 URL']]);
    }

    $resolve = ppo_checkin_get_resolve_ip(['ip' => $ip, 'url' => $url]);
    $settings = ppo_checkin_get_settings();
    // 补挂区尽量使用表单当前值，避免未保存时仍拿旧配置执行。
    if ($cookie) $settings['cookie'] = $cookie;
    if ($url)    $settings['url']    = $url;
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) $settings['ip'] = $ip;

    $lines = [];
    $ok = 0; $fail = 0;

    $moment_id  = absint($settings['moment_id'] ?? 0);
    $comment_id = absint($settings['comment_id'] ?? 0);
    $follow_id  = absint($settings['follow_id'] ?? 0);
    $msg_id     = absint($settings['msg_id'] ?? 0);
    $target_follow_id  = absint($settings['target_follow_id'] ?? 0);
    $target_comment_id = absint($settings['target_comment_id'] ?? 0);

    $ajax_url     = $url . '/wp-admin/admin-ajax.php';
    $content_form = ['Content-Type: application/x-www-form-urlencoded'];

    switch ($operation) {
        case 'checkin':
            $r = ppo_checkin_do_checkin_raw($settings);
            if ($r['success']) {
                $parts = array_filter([
                    isset($r['xp']) ? '经验 +' . $r['xp'] : '',
                    isset($r['point']) ? '积分 +' . $r['point'] : '',
                ]);
                $lines[] = '✅ 签到成功：' . implode('，', $parts);
            } else {
                $lines[] = '❌ ' . ($r['message'] ?? '签到失败');
                wp_send_json_error(['lines' => $lines]);
            }
            break;

        case 'collect':
            $user_nonce = '';
            for ($try = 0; $try < 3; $try++) {
                if (!$user_nonce) {
                    $user_nonce = ppo_checkin_fetch_user_nonce($url, $cookie, $resolve);
                }
                if ($user_nonce) break;
            }
            if (!$user_nonce) {
                wp_send_json_error(['lines' => ['⚠️ 重试3次后仍无法获取 user_nonce，请确保已登录']]);
            }
            if (!$moment_id) {
                wp_send_json_error(['lines' => ['⚠️ 请先填写片刻 ID']]);
            }
            for ($i = 0; $i < $times; $i++) {
                $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
                    'action=post_collect_action&nonce=' . urlencode($user_nonce) . '&post_id=' . $moment_id,
                    $content_form, $resolve, 15);
                $d = json_decode($r['body'], true);
                if (!empty($d['collected'])) $ok++; else $fail++;
                $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
                    'action=post_collect_action&nonce=' . urlencode($user_nonce) . '&post_id=' . $moment_id,
                    $content_form, $resolve, 15);
                $d = json_decode($r['body'], true);
                if (isset($d['collected']) && !$d['collected']) $ok++; else $fail++;
            }
            $lines[] = '📥 收藏：成功 ' . $ok . '/' . ($times * 2) . ' 次';
            break;

        case 'like_moment':
            $rest_nonce = '';
            for ($try = 0; $try < 3; $try++) {
                if (!$rest_nonce) {
                    $r = ppo_checkin_curl_request($url . '/wp-admin/admin-ajax.php?action=rest-nonce', $cookie, 'GET', '', [], $resolve, 20);
                    if ($r['code'] === 200 && !empty($r['body'])) {
                        $rest_nonce = trim($r['body']);
                    }
                }
                if ($rest_nonce) break;
            }
            if (!$rest_nonce) {
                wp_send_json_error(['lines' => ['⚠️ 重试3次后仍无法获取 rest_nonce，请确保已登录']]);
            }
            if (!$moment_id) {
                wp_send_json_error(['lines' => ['⚠️ 请先填写片刻 ID']]);
            }
            $like_url = $url . '/wp-json/ppo/v1/moments/' . $moment_id . '/like';
            for ($i = 0; $i < $times; $i++) {
                $r = ppo_checkin_curl_request($like_url, $cookie, 'POST', '',
                    ['X-WP-Nonce: ' . $rest_nonce, 'Content-Type: application/json'], $resolve, 15);
                $d = json_decode($r['body'], true);
                if (!empty($d['message']) && $d['message'] === '已点赞') $ok++; else $fail++;
                $r = ppo_checkin_curl_request($like_url, $cookie, 'DELETE', '',
                    ['X-WP-Nonce: ' . $rest_nonce, 'Content-Type: application/json'], $resolve, 15);
                $d = json_decode($r['body'], true);
                if (!empty($d['message']) && $d['message'] === '已取消点赞') $ok++; else $fail++;
            }
            $lines[] = '❤️ 点赞片刻：成功 ' . $ok . '/' . ($times * 2) . ' 次';
            break;

        case 'comment_like':
            if (!$comment_id) {
                wp_send_json_error(['lines' => ['⚠️ 请先填写评论 ID']]);
            }
            for ($i = 0; $i < $times; $i++) {
                $ret = ppo_checkin_run_passive_comment_cycle($cookie, $url, $resolve, $comment_id);
                $ok += $ret['ok']; $fail += $ret['fail'];
            }
            $lines[] = '💬 评论点赞：成功 ' . $ok . '/' . ($times * 2) . ' 次';
            break;

        case 'follow':
            if (!$follow_id) {
                wp_send_json_error(['lines' => ['⚠️ 请先填写关注用户 ID']]);
            }
            for ($i = 0; $i < $times; $i++) {
                $ret = ppo_checkin_run_follow_cycle($cookie, $url, $resolve, $follow_id);
                $ok += $ret['ok']; $fail += $ret['fail'];
            }
            $lines[] = '👤 关注：成功 ' . $ok . '/' . ($times * 2) . ' 次';
            break;

        case 'msg':
            if (!$msg_id) {
                wp_send_json_error(['lines' => ['⚠️ 请先填写私信用户 ID']]);
            }
            $page = ppo_checkin_fetch_page_nonces($url, $cookie, $resolve);
            $msg_nonce = $page['msg_nonce'] ?? '';
            if (!$msg_nonce) {
                wp_send_json_error(['lines' => ['⚠️ 无法获取 msg_nonce，请确认已登录且主题资源正常输出']]);
            }
            for ($i = 0; $i < $times; $i++) {
                $ret = ppo_checkin_run_msg_cycle($cookie, $url, $resolve, $msg_id, $msg_nonce);
                $ok += $ret['ok']; $fail += $ret['fail'];
            }
            $lines[] = '✉️ 私信：成功 ' . $ok . '/' . $times . ' 次';
            break;

        case 'passive_follow':
            if (!$second_cookie) {
                wp_send_json_error(['lines' => ['⚠️ 请先填写第二个账号 Cookie']]);
            }
            if (!$target_follow_id) {
                wp_send_json_error(['lines' => ['⚠️ 请先填写被关注用户 ID']]);
            }
            for ($i = 0; $i < $times; $i++) {
                $ret = ppo_checkin_run_follow_cycle($second_cookie, $url, $resolve, $target_follow_id);
                $ok += $ret['ok']; $fail += $ret['fail'];
            }
            $lines[] = '👤 被动关注：成功 ' . $ok . '/' . ($times * 2) . ' 次';
            break;

        case 'passive_comment_like':
            if (!$second_cookie) {
                wp_send_json_error(['lines' => ['⚠️ 请先填写第二个账号 Cookie']]);
            }
            if (!$target_comment_id) {
                wp_send_json_error(['lines' => ['⚠️ 请先填写被点赞评论 ID']]);
            }
            for ($i = 0; $i < $times; $i++) {
                $ret = ppo_checkin_run_passive_comment_cycle($second_cookie, $url, $resolve, $target_comment_id);
                $ok += $ret['ok']; $fail += $ret['fail'];
            }
            $lines[] = '💬 被动评论点赞：成功 ' . $ok . '/' . ($times * 2) . ' 次';
            break;

        default:
            wp_send_json_error(['lines' => ['⚠️ 未知操作：' . $operation]]);
    }

    if ($fail > 0) {
        $lines[] = '⚠️ 有 ' . $fail . ' 个操作失败';
        wp_send_json_error(['lines' => $lines]);
    } else {
        $lines[] = '✅ 全部成功';
        wp_send_json_success(['lines' => $lines]);
    }
}
