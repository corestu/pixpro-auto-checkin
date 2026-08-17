<?php

if (!defined('ABSPATH')) exit;


// =============================================================
//  主动/被动任务执行
// =============================================================

/**
 * 单轮收藏/取消收藏 + 点赞/取消点赞片刻（不包含评论部分）。
 * 用于只配置片刻 ID、未配置评论 ID 的场景。
 */
function ppo_checkin_run_moment_cycle($cookie, $url, $resolve, $moment_id, $rest_nonce, $user_nonce) {
    $ajax_url     = $url . '/wp-admin/admin-ajax.php';
    $like_url     = $url . '/wp-json/ppo/v1/moments/' . $moment_id . '/like';
    $content_form = ['Content-Type: application/x-www-form-urlencoded'];
    $collect_ok = 0; $collect_fail = 0;
    $like_ok = 0; $like_fail = 0;

    if ($user_nonce) {
        $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
            'action=post_collect_action&nonce=' . urlencode($user_nonce) . '&post_id=' . $moment_id,
            $content_form, $resolve, 15);
        $d = json_decode($r['body'], true);
        if (!empty($d['collected'])) $collect_ok++; else $collect_fail++;

        $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
            'action=post_collect_action&nonce=' . urlencode($user_nonce) . '&post_id=' . $moment_id,
            $content_form, $resolve, 15);
        $d = json_decode($r['body'], true);
        if (isset($d['collected']) && !$d['collected']) $collect_ok++; else $collect_fail++;
    } else {
        $collect_fail += 2;
    }

    if ($rest_nonce) {
        $r = ppo_checkin_curl_request($like_url, $cookie, 'POST', '',
            ['X-WP-Nonce: ' . $rest_nonce, 'Content-Type: application/json'], $resolve, 15);
        $d = json_decode($r['body'], true);
        if (!empty($d['message']) && $d['message'] === '已点赞') $like_ok++; else $like_fail++;

        $r = ppo_checkin_curl_request($like_url, $cookie, 'DELETE', '',
            ['X-WP-Nonce: ' . $rest_nonce, 'Content-Type: application/json'], $resolve, 15);
        $d = json_decode($r['body'], true);
        if (!empty($d['message']) && $d['message'] === '已取消点赞') $like_ok++; else $like_fail++;
    } else {
        $like_fail += 2;
    }

    return [
        'ok'         => $collect_ok + $like_ok,
        'fail'       => $collect_fail + $like_fail,
        'collect_ok' => $collect_ok,
        'collect_fail' => $collect_fail,
        'like_ok'    => $like_ok,
        'like_fail'  => $like_fail,
    ];
}

// =============================================================
//  单轮关注（先取关再关注，结尾保持关注状态，无需 nonce）
// =============================================================

function ppo_checkin_run_follow_cycle($cookie, $url, $resolve, $follow_id) {
    $ajax_url = $url . '/wp-admin/admin-ajax.php';
    $content_form = ['Content-Type: application/x-www-form-urlencoded'];
    $ok = 0; $fail = 0;

    // 先取关再关注：
    // 1. 如果本来已关注，可以重新触发“关注”事件，继续拿每日关注/被关注奖励；
    // 2. 如果本来未关注，取关接口也会返回成功，随后关注成功；
    // 3. 最终一定保持“已关注”状态，方便后续私信任务正常执行。
    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=ppo_unfollow_user_ajax&following_id=' . $follow_id,
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['success'])) $ok++; else $fail++;

    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=ppo_follow_user_ajax&following_id=' . $follow_id,
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['success'])) $ok++; else $fail++;

    return ['ok' => $ok, 'fail' => $fail];
}

/**
 * 单轮发送私信
 * 私信接口需要 msg_nonce（ppo_msg_action 对应的 nonce），不能省略。
 */
function ppo_checkin_run_msg_cycle($cookie, $url, $resolve, $msg_id, $msg_nonce = '') {
    $ajax_url     = $url . '/wp-admin/admin-ajax.php';
    $content_form = ['Content-Type: application/x-www-form-urlencoded'];

    if (empty($msg_nonce)) {
        return ['ok' => 0, 'fail' => 1];
    }

    // 主题私信接口有重复内容限制（private_msg_duplicate_window）。
    // 每次生成唯一后缀，避免连续发送相同内容被“请不要重复发送相同内容”拦截。
    $msg = '经验+2 #' . wp_generate_password(6, false) . ' ' . current_time('H:i:s');

    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=send_private_msg&receive_id=' . $msg_id . '&msg=' . urlencode($msg) . '&nonce=' . urlencode($msg_nonce),
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['status']) && $d['status'] == 1) return ['ok' => 1, 'fail' => 0];
    return ['ok' => 0, 'fail' => 1];
}

/**
 * 循环执行多轮任务（片刻/评论/关注/私信），返回汇总信息。
 *
 * 支持部分配置，不需要全部填完：
 * - 只填片刻 ID：执行收藏/取消收藏 + 点赞/取消点赞片刻；
 * - 只填评论 ID：执行评论点赞/取消点赞；
 * - 同时填片刻 ID 和评论 ID：执行完整主任务；
 * - 关注任务只需要关注用户 ID；
 * - 私信任务只需要私信用户 ID（并会自动获取 msg_nonce）。
 *
 * @param int $times 片刻/评论主任务循环次数
 * @param int $follow_times 关注任务循环次数
 * @param int $msg_times 私信发送次数
 * @return array ['total_ok'=>int, 'total_fail'=>int, 'message'=>string]
 */
function ppo_checkin_run_tasks($cookie, $url, $resolve, $moment_id, $comment_id, $times = 10, $follow_id = 0, $follow_times = 5, $msg_id = 0, $msg_times = 5) {
    $has_moment = (bool) $moment_id;
    $has_comment = (bool) $comment_id;
    $has_follow = (bool) $follow_id;
    $has_msg    = (bool) $msg_id;

    if (!$has_moment && !$has_comment && !$has_follow && !$has_msg) {
        return ['total_ok' => 0, 'total_fail' => 0, 'message' => '⏭️ 主动任务：未配置，已跳过'];
    }

    // 获取所需 nonce：片刻相关需要 rest_nonce + user_nonce，私信需要 msg_nonce。
    $rest_nonce = '';
    $user_nonce = '';
    $msg_nonce  = '';

    for ($try = 0; $try < 3; $try++) {
        if ($has_moment) {
            if (!$rest_nonce) {
                $rest_nonce = ppo_checkin_fetch_rest_nonce($url, $cookie, $resolve);
            }
            if (!$user_nonce) {
                $page      = ppo_checkin_fetch_page_nonces($url, $cookie, $resolve);
                $user_nonce = $page['user_nonce'] ?? '';
            }
        }

        if ($has_msg && !$msg_nonce) {
            $page      = ppo_checkin_fetch_page_nonces($url, $cookie, $resolve);
            $msg_nonce = $page['msg_nonce'] ?? '';
        }

        $moment_ready = !$has_moment || ($rest_nonce && $user_nonce);
        $msg_ready    = !$has_msg || $msg_nonce;
        if ($moment_ready && $msg_ready) {
            break;
        }
    }

    $total_ok = 0; $total_fail = 0;
    $collect_ok = 0; $collect_fail = 0;
    $like_ok = 0; $like_fail = 0;
    $comment_ok = 0; $comment_fail = 0;

    // 片刻任务：收藏 + 点赞
    $moment_blocked = false;
    if ($has_moment) {
        if (!$rest_nonce || !$user_nonce) {
            $moment_blocked = true;
            $missing = [];
            if (!$rest_nonce) $missing[] = 'rest_nonce';
            if (!$user_nonce) $missing[] = 'user_nonce';
            $total_fail += $times * 4;
        } else {
            for ($i = 0; $i < $times; $i++) {
                $ret = ppo_checkin_run_moment_cycle($cookie, $url, $resolve, $moment_id, $rest_nonce, $user_nonce);
                $total_ok    += $ret['ok'];
                $total_fail  += $ret['fail'];
                $collect_ok  += $ret['collect_ok'];
                $collect_fail += $ret['collect_fail'];
                $like_ok     += $ret['like_ok'];
                $like_fail   += $ret['like_fail'];
            }
        }
    }

    // 评论任务
    if ($has_comment) {
        for ($i = 0; $i < $times; $i++) {
            $ret = ppo_checkin_run_passive_comment_cycle($cookie, $url, $resolve, $comment_id);
            $comment_ok  += $ret['ok'];
            $comment_fail += $ret['fail'];
        }
        $total_ok   += $comment_ok;
        $total_fail += $comment_fail;
    }

    // 关注任务
    $follow_ok = 0; $follow_fail = 0;
    if ($has_follow) {
        for ($i = 0; $i < $follow_times; $i++) {
            $ret = ppo_checkin_run_follow_cycle($cookie, $url, $resolve, $follow_id);
            $follow_ok   += $ret['ok'];
            $follow_fail += $ret['fail'];
        }
        $total_ok   += $follow_ok;
        $total_fail += $follow_fail;
    }

    // 私信任务
    $msg_ok = 0; $msg_fail = 0;
    if ($has_msg && $msg_nonce) {
        for ($i = 0; $i < $msg_times; $i++) {
            $ret = ppo_checkin_run_msg_cycle($cookie, $url, $resolve, $msg_id, $msg_nonce);
            $msg_ok   += $ret['ok'];
            $msg_fail += $ret['fail'];
        }
        $total_ok   += $msg_ok;
        $total_fail += $msg_fail;
    } elseif ($has_msg && !$msg_nonce) {
        $total_fail += $msg_times;
    }

    // 构建详细消息
    $detail = [];
    if ($has_moment) {
        if ($moment_blocked) {
            $detail[] = '    片刻操作（收藏/点赞）未执行：无法获取 ' . implode('、', $missing);
        } else {
            $collect_total = $collect_ok + $collect_fail;
            $detail[] = '    收藏操作...（' . $collect_ok . '/' . $collect_total . '）' . ($collect_fail === 0 ? '✅' : '❌');
            $like_total = $like_ok + $like_fail;
            $detail[] = '    点赞操作...（' . $like_ok . '/' . $like_total . '）' . ($like_fail === 0 ? '✅' : '❌');
        }
    }
    if ($has_comment) {
        $comment_total = $comment_ok + $comment_fail;
        $detail[] = '    评论操作...（' . $comment_ok . '/' . $comment_total . '）' . ($comment_fail === 0 ? '✅' : '❌');
    }
    if ($has_follow) {
        $follow_total = $follow_ok + $follow_fail;
        $detail[] = '    关注操作...（' . $follow_ok . '/' . $follow_total . '）' . ($follow_fail === 0 ? '✅' : '❌');
    }
    if ($has_msg) {
        if ($msg_nonce) {
            $msg_total = $msg_ok + $msg_fail;
            $detail[] = '    私信操作...（' . $msg_ok . '/' . $msg_total . '）' . ($msg_fail === 0 ? '✅' : '❌');
        } else {
            $detail[] = '    私信操作未执行：无法获取 msg_nonce';
        }
    }

    $message = "🗒️ 主动任务：\n" . implode("\n", $detail);

    return ['total_ok' => $total_ok, 'total_fail' => $total_fail, 'message' => $message];
}

/**
 * 单轮评论点赞/取消点赞（用于第二个账号的被动任务）
 */
function ppo_checkin_run_passive_comment_cycle($cookie, $url, $resolve, $comment_id) {
    $ajax_url     = $url . '/wp-admin/admin-ajax.php';
    $content_form = ['Content-Type: application/x-www-form-urlencoded'];
    $ok = 0; $fail = 0;

    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=like_or_unlike_comment&comment_id=' . $comment_id . '&liked=0',
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['success']) && !empty($d['data']['liked'])) $ok++; else $fail++;

    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=like_or_unlike_comment&comment_id=' . $comment_id . '&liked=1',
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['success']) && empty($d['data']['liked'])) $ok++; else $fail++;

    return ['ok' => $ok, 'fail' => $fail];
}

/**
 * 循环执行多轮被动任务（第二个账号负责关注主账号、点赞主账号评论）
 * @param int $follow_times  取关后重新关注循环次数（最终保持关注状态）
 * @param int $comment_times 评论点赞/取消点赞循环次数
 * @return array ['total_ok'=>int, 'total_fail'=>int, 'message'=>string]
 */
function ppo_checkin_run_passive_tasks($cookie, $url, $resolve, $target_follow_id, $target_comment_id, $follow_times = 5, $comment_times = 10) {
    $total_ok = 0; $total_fail = 0;
    $follow_ok = 0; $follow_fail = 0;
    $comment_ok = 0; $comment_fail = 0;

    // 被动关注任务
    if ($target_follow_id) {
        for ($i = 0; $i < $follow_times; $i++) {
            $ret = ppo_checkin_run_follow_cycle($cookie, $url, $resolve, $target_follow_id);
            $follow_ok  += $ret['ok'];
            $follow_fail += $ret['fail'];
        }
        $total_ok  += $follow_ok;
        $total_fail += $follow_fail;
    }

    // 被动评论点赞任务
    if ($target_comment_id) {
        for ($i = 0; $i < $comment_times; $i++) {
            $ret = ppo_checkin_run_passive_comment_cycle($cookie, $url, $resolve, $target_comment_id);
            $comment_ok  += $ret['ok'];
            $comment_fail += $ret['fail'];
        }
        $total_ok  += $comment_ok;
        $total_fail += $comment_fail;
    }

    // 构建详细消息
    $detail = [];
    if ($target_follow_id) {
        $follow_total = $follow_ok + $follow_fail;
        $detail[] = '    关注操作...（' . $follow_ok . '/' . $follow_total . '）' . ($follow_fail === 0 ? '✅' : '❌');
    }
    if ($target_comment_id) {
        $comment_total = $comment_ok + $comment_fail;
        $detail[] = '    评论点赞操作...（' . $comment_ok . '/' . $comment_total . '）' . ($comment_fail === 0 ? '✅' : '❌');
    }

    $message = "🌀 被动任务：\n" . implode("\n", $detail);

    return ['total_ok' => $total_ok, 'total_fail' => $total_fail, 'message' => $message];
}
