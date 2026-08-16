<?php

if (!defined('ABSPATH')) exit;


// =============================================================
//  后台菜单
// =============================================================

add_action('admin_menu', 'ppo_checkin_admin_menu');
function ppo_checkin_admin_menu() {
    add_options_page(
        __('PixPro 自动签到', 'pixpro-checkin'),
        __('PixPro 签到', 'pixpro-checkin'),
        'manage_options',
        'ppo-checkin',
        'ppo_checkin_admin_page'
    );
}

// =============================================================
//  后台页面
// =============================================================

function ppo_checkin_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('权限不足'));
    }

    $settings    = ppo_checkin_get_settings();
    $cron_hour   = (int) get_option('ppo_checkin_cron_hour', 8);
    $cron_minute = (int) get_option('ppo_checkin_cron_minute', 0);
    ?>
    <div class="wrap">
        <h1><?php _e('PixPro 自动签到', 'pixpro-checkin'); ?></h1>

        <form method="post" action="options.php" novalidate>
            <?php settings_fields('ppo_checkin_group'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_cookie"><?php _e('登录 Cookie', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <textarea id="ppo_checkin_cookie" name="ppo_checkin_settings[cookie]" rows="4" class="large-text code"><?php echo esc_textarea($settings['cookie'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php _e('确保登录目标网站后，刷新，从请求标头中复制 Cookie 整段贴过来', 'pixpro-checkin'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_url"><?php _e('目标站点地址', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="ppo_checkin_url" name="ppo_checkin_settings[url]" value="<?php echo esc_url($settings['url'] ?? ''); ?>" class="regular-text" placeholder="https://pix.plus">
                        <p class="description"><?php _e('需要签到的网站首页地址', 'pixpro-checkin'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_ip"><?php _e('网站 IP（选填）', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ppo_checkin_ip" name="ppo_checkin_settings[ip]" value="<?php echo esc_attr($settings['ip'] ?? ''); ?>" class="regular-text" placeholder="38.76.176.47">
                        <p class="description"><?php _e('在这填写目标站点的ip直连', 'pixpro-checkin'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_moment_id"><?php _e('片刻 ID', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="ppo_checkin_moment_id" name="ppo_checkin_settings[moment_id]" value="<?php echo intval($settings['moment_id'] ?? 0); ?>" class="small-text" min="1" step="1">
                        <p class="description"><?php _e('一键任务会用此 ID 对片刻进行点赞/收藏操作', 'pixpro-checkin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_comment_id"><?php _e('评论 ID', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="ppo_checkin_comment_id" name="ppo_checkin_settings[comment_id]" value="<?php echo intval($settings['comment_id'] ?? 0); ?>" class="small-text" min="1" step="1">
                        <p class="description"><?php _e('一键任务会用此 ID 对评论进行点赞/取消操作', 'pixpro-checkin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_follow_id"><?php _e('关注用户 ID', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="ppo_checkin_follow_id" name="ppo_checkin_settings[follow_id]" value="<?php echo intval($settings['follow_id'] ?? 0); ?>" class="small-text" min="1" step="1">
                        <p class="description"><?php _e('一键任务会用此 ID 进行关注/取消关注操作', 'pixpro-checkin'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_msg_id"><?php _e('私信用户 ID', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="ppo_checkin_msg_id" name="ppo_checkin_settings[msg_id]" value="<?php echo intval($settings['msg_id'] ?? 0); ?>" class="small-text" min="1" step="1">
                        <p class="description"><?php _e('主动任务会用此 ID 发送私信（经验+2）', 'pixpro-checkin'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_second_cookie"><?php _e('第二个账号 Cookie', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <textarea id="ppo_checkin_second_cookie" name="ppo_checkin_settings[second_cookie]" rows="4" class="large-text code"><?php echo esc_textarea($settings['second_cookie'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php _e('被动任务需要第二个账号来关注你的主账号、点赞你的评论。同样从请求标头中复制 Cookie', 'pixpro-checkin'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_target_follow_id"><?php _e('被关注用户 ID', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="ppo_checkin_target_follow_id" name="ppo_checkin_settings[target_follow_id]" value="<?php echo intval($settings['target_follow_id'] ?? 0); ?>" class="small-text" min="1" step="1">
                        <p class="description"><?php _e('第二个账号要关注的用户 ID（填写你主账号的用户 ID），让主账号获得被关注经验', 'pixpro-checkin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_target_comment_id"><?php _e('被点赞评论 ID', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="ppo_checkin_target_comment_id" name="ppo_checkin_settings[target_comment_id]" value="<?php echo intval($settings['target_comment_id'] ?? 0); ?>" class="small-text" min="1" step="1">
                        <p class="description"><?php _e('第二个账号要点赞的评论 ID（填写你主账号的评论 ID），让主账号评论获得被点赞经验', 'pixpro-checkin'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php _e('执行时间', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <label style="margin-right:4px"><?php _e('时：', 'pixpro-checkin'); ?></label>
                        <select id="ppo_checkin_hour" name="ppo_checkin_settings[cron_hour]" style="width:72px">
                            <?php for ($h = 0; $h <= 23; $h++): ?>
                                <option value="<?php echo $h; ?>" <?php selected($cron_hour, $h); ?>><?php echo sprintf('%02d', $h); ?></option>
                            <?php endfor; ?>
                        </select>
                        <label style="margin:0 4px 0 8px"><?php _e('分：', 'pixpro-checkin'); ?></label>
                        <select id="ppo_checkin_minute" name="ppo_checkin_settings[cron_minute]" style="width:72px">
                            <?php for ($m = 0; $m <= 59; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php selected($cron_minute, $m); ?>><?php echo sprintf('%02d', $m); ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="margin-left:8px;color:#999"><?php _e('每天一次，秒数自动随机', 'pixpro-checkin'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ppo_checkin_notify"><?php _e('失败邮件通知', 'pixpro-checkin'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="ppo_checkin_notify" name="ppo_checkin_settings[notify]" value="1" <?php checked(!empty($settings['notify'])); ?>>
                            <?php _e('签到失败时发送邮件到站长邮箱', 'pixpro-checkin'); ?>
                        </label>
                        <p class="description">
                            <?php _e('当前站点邮箱：', 'pixpro-checkin'); ?>
                            <code><?php echo esc_html(get_option('admin_email')); ?></code>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('签到记录', 'pixpro-checkin'); ?></th>
                    <td>
                        <?php
                        $last = get_option('ppo_checkin_last_result', []);
                        if (!empty($last)) {
                            $color = !empty($last['success']) ? '#46b450' : '#dc3232';
                            echo '<div style="background:#f0f6fc;padding:10px 14px;border-left:4px solid ' . $color . ';white-space:pre-wrap;font-family:Consolas,monospace;font-size:13px;line-height:1.7">';
                            echo '<p><strong>' . __('上次执行：', 'pixpro-checkin') . '</strong>' . esc_html($last['time'] ?? '') . '</p>';
                            if (!empty($last['checkin_msg'])) {
                                echo '<p>' . esc_html($last['checkin_msg']) . '</p>';
                            }
                            if (!empty($last['active_msg'])) {
                                echo '<p>' . esc_html($last['active_msg']) . '</p>';
                            } elseif (isset($last['active_msg'])) {
                                echo '<p>⏭️ 主动任务：未配置，已跳过</p>';
                            }
                            if (!empty($last['passive_msg'])) {
                                echo '<p>' . esc_html($last['passive_msg']) . '</p>';
                            } elseif (isset($last['passive_msg'])) {
                                echo '<p>⏭️ 被动任务：未配置，已跳过</p>';
                            }
                            echo '</div>';
                        } else {
                            echo '<p class="description">' . __('暂无记录。点击下方"签到调试"立即验证配置。', 'pixpro-checkin') . '</p>';
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('保存设置', 'pixpro-checkin')); ?>
        </form>

        <hr>

        <h2><?php _e('✅ 立即签到', 'pixpro-checkin'); ?></h2>
        <p><?php _e('点击执行全流程：签到 → 主动任务 → 被动任务', 'pixpro-checkin'); ?></p>
        <p>
            <button type="button" id="ppo_checkin_do_btn" class="button button-primary"><?php _e('✅ 立即签到', 'pixpro-checkin'); ?></button>
        </p>
        <div id="ppo_checkin_do_result" class="ppo-result-box"></div>

        <hr>

        <h2><?php _e('🔄 补挂区', 'pixpro-checkin'); ?></h2>
        <p><?php _e('单独执行某个操作，可指定执行次数。不会影响签到记录。', 'pixpro-checkin'); ?></p>

        <div class="rerow-wrap">
            <div class="rerow-row">
                <span class="rerow-label">签到</span>
                <input type="number" class="rerow-times" value="1" min="1">
                <button type="button" class="button rerow-btn" data-op="checkin">执行</button>
                <div class="ppo-result-box rerow-result" id="rerow_checkin"></div>
            </div>
            <div class="rerow-row">
                <span class="rerow-label">收藏片刻</span>
                <input type="number" class="rerow-times" value="1" min="1">
                <button type="button" class="button rerow-btn" data-op="collect">执行</button>
                <div class="ppo-result-box rerow-result" id="rerow_collect"></div>
            </div>
            <div class="rerow-row">
                <span class="rerow-label">点赞片刻</span>
                <input type="number" class="rerow-times" value="1" min="1">
                <button type="button" class="button rerow-btn" data-op="like_moment">执行</button>
                <div class="ppo-result-box rerow-result" id="rerow_like_moment"></div>
            </div>
            <div class="rerow-row">
                <span class="rerow-label">评论点赞</span>
                <input type="number" class="rerow-times" value="1" min="1">
                <button type="button" class="button rerow-btn" data-op="comment_like">执行</button>
                <div class="ppo-result-box rerow-result" id="rerow_comment_like"></div>
            </div>
            <div class="rerow-row">
                <span class="rerow-label">关注用户</span>
                <input type="number" class="rerow-times" value="1" min="1">
                <button type="button" class="button rerow-btn" data-op="follow">执行</button>
                <div class="ppo-result-box rerow-result" id="rerow_follow"></div>
            </div>
            <div class="rerow-row">
                <span class="rerow-label">发送私信</span>
                <input type="number" class="rerow-times" value="1" min="1">
                <button type="button" class="button rerow-btn" data-op="msg">执行</button>
                <div class="ppo-result-box rerow-result" id="rerow_msg"></div>
            </div>
            <div class="rerow-row">
                <span class="rerow-label">被动关注</span>
                <input type="number" class="rerow-times" value="1" min="1">
                <button type="button" class="button rerow-btn" data-op="passive_follow">执行</button>
                <div class="ppo-result-box rerow-result" id="rerow_passive_follow"></div>
            </div>
            <div class="rerow-row">
                <span class="rerow-label">被动评论点赞</span>
                <input type="number" class="rerow-times" value="1" min="1">
                <button type="button" class="button rerow-btn" data-op="passive_comment_like">执行</button>
                <div class="ppo-result-box rerow-result" id="rerow_passive_comment_like"></div>
            </div>
        </div>

        <style>
        .rerow-wrap { margin:12px 0 }
        .rerow-row { margin:6px 0; display:flex; align-items:flex-start; gap:8px; flex-wrap:wrap }
        .rerow-label { display:inline-block; min-width:110px; font-weight:600; line-height:30px; font-size:13px }
        .rerow-times { width:60px }
        .rerow-result { width:100%; margin-top:2px }
        </style>

        <hr>

        <h2><?php _e('🧪 功能测试', 'pixpro-checkin'); ?></h2>
        <p><?php _e('各功能可单独测试，独立查看结果。', 'pixpro-checkin'); ?></p>

        <div style="margin:16px 0">
            <h3 style="margin:0 0 6px"><?php _e('🔍 签到测试', 'pixpro-checkin'); ?></h3>
            <button type="button" id="ppo_checkin_test_btn" class="button"><?php _e('🔍 签到测试', 'pixpro-checkin'); ?></button>
            <div id="ppo_checkin_test_result" class="ppo-result-box"></div>
        </div>

        <div style="margin:16px 0">
            <h3 style="margin:0 0 6px"><?php _e('🧪 主动任务测试', 'pixpro-checkin'); ?></h3>
            <button type="button" id="ppo_checkin_task_btn" class="button"><?php _e('🧪 主动任务测试', 'pixpro-checkin'); ?></button>
            <div id="ppo_checkin_task_result" class="ppo-result-box"></div>
        </div>

        <div style="margin:16px 0">
            <h3 style="margin:0 0 6px"><?php _e('🌀 被动任务测试', 'pixpro-checkin'); ?></h3>
            <button type="button" id="ppo_checkin_passive_btn" class="button"><?php _e('🌀 被动任务测试', 'pixpro-checkin'); ?></button>
            <div id="ppo_checkin_passive_result" class="ppo-result-box"></div>
        </div>

        <div style="margin:16px 0">
            <h3 style="margin:0 0 6px"><?php _e('📋 其他', 'pixpro-checkin'); ?></h3>
            <button type="button" id="ppo_checkin_test_mail_btn" class="button"><?php _e('📧 测试邮件', 'pixpro-checkin'); ?></button>
            <button type="button" id="ppo_checkin_clear_btn" class="button"><?php _e('🗑️ 清除记录', 'pixpro-checkin'); ?></button>
            <div id="ppo_checkin_misc_result" class="ppo-result-box"></div>
        </div>

        <style>
        .ppo-result-box { display:none;margin-top:8px;padding:10px 14px;background:#f6f7f7;border-left:4px solid #72aee6;white-space:pre-wrap;font-family:Consolas,monospace;font-size:13px;line-height:1.7 }
        .ppo-result-box.is-error { border-left-color:#dc3232 }
        .ppo-result-box.is-success { border-left-color:#46b450 }
        </style>

        <?php
        $next_cron = wp_next_scheduled('ppo_checkin_cron_hook');
        if (!$next_cron) {
            echo '<div class="notice notice-warning inline" style="margin-top:12px"><p>' . __('⚠️ 定时任务尚未注册，请保存设置以激活自动签到。', 'pixpro-checkin') . '</p></div>';
        } else {
            echo '<div class="notice notice-info inline" style="margin-top:12px"><p>' . sprintf(__('🕐 下次自动签到时间：%s（北京时间）', 'pixpro-checkin'), wp_date('Y-m-d H:i:s', $next_cron)) . '</p></div>';
        }

        // Cookie 过期预估
        $cookie_str = $settings['cookie'] ?? '';
        if ($cookie_str && preg_match('/wordpress_(?:logged_in|sec)_[^=]+=([^;]+)/', $cookie_str, $m)) {
            $parts = explode('|', $m[1]);
            if (isset($parts[1]) && is_numeric($parts[1])) {
                $expiry    = (int) $parts[1];
                $remaining = $expiry - time();
                if ($remaining > 0) {
                    $days = floor($remaining / 86400);
                    echo '<div class="notice notice-info inline" style="margin-top:6px"><p>' . sprintf(__('🍪 Cookie 有效期预估：约 %d 天后过期（%s）', 'pixpro-checkin'), $days, wp_date('Y-m-d H:i:s', $expiry)) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error inline" style="margin-top:6px"><p>' . __('🍪 Cookie 已过期！请重新获取。', 'pixpro-checkin') . '</p></div>';
                }
            }
        }
        ?>

        <hr>

        <h2><?php _e('📦 关于插件', 'pixpro-checkin'); ?></h2>
        <div class="ppo-about-card">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php _e('插件名称', 'pixpro-checkin'); ?></th>
                    <td><strong>PixPro 自动签到</strong></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('当前版本', 'pixpro-checkin'); ?></th>
                    <td><code>v<?php echo PPO_CHECKIN_VERSION; ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('作者', 'pixpro-checkin'); ?></th>
                    <td>摸鱼大王</td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('插件主页', 'pixpro-checkin'); ?></th>
                    <td><a href="https://blog.aistu.cn" target="_blank" rel="noopener noreferrer">https://blog.aistu.cn</a></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('功能简介', 'pixpro-checkin'); ?></th>
                    <td>
                        <?php _e('针对 PixPro 站点的每日自动签到插件。配置登录 Cookie 和目标站点后，通过 WP Cron 定时执行签到，并支持主动任务（收藏 / 点赞 / 评论 / 关注 / 私信）与被动任务（被关注 / 被点赞），签到失败时自动发送邮件通知站长。', 'pixpro-checkin'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('环境要求', 'pixpro-checkin'); ?></th>
                    <td>WordPress 5.0+ &nbsp;·&nbsp; PHP 7.2+ &nbsp;·&nbsp; cURL 扩展</td>
                </tr>
            </table>
        </div>

        <style>
        .ppo-about-card {
            max-width: 640px;
            background: #fff;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #72aee6;
            padding: 6px 18px;
            margin-top: 8px;
            border-radius: 4px;
        }
        .ppo-about-card .form-table th { width: 110px; padding: 12px 10px 12px 0; }
        .ppo-about-card .form-table td { padding: 12px 0; }
        </style>
    </div>

    <script>
    jQuery(function($) {
        function showIn($box, lines, type) {
            $box.show().removeClass('is-error is-success');
            if (type === 'success') $box.addClass('is-success');
            else if (type === 'error') $box.addClass('is-error');
            $box.html(lines.map(function(l) { return '<div>' + $('<span>').text(l).html() + '</div>'; }).join(''));
        }

        function getSettings() {
            return {
                cookie: $('#ppo_checkin_cookie').val(),
                url:    $('#ppo_checkin_url').val(),
                ip:     $('#ppo_checkin_ip').val(),
                second_cookie: $('#ppo_checkin_second_cookie').val(),
                target_follow_id: $('#ppo_checkin_target_follow_id').val(),
                target_comment_id: $('#ppo_checkin_target_comment_id').val(),
            };
        }

        // 签到测试（不消耗签到）
        $('#ppo_checkin_test_btn').on('click', function() {
            var $box = $('#ppo_checkin_test_result');
            var s = getSettings();
            if (!s.cookie || !s.url) {
                showIn($box, ['⚠️ 请先填写 Cookie 和目标站点 URL'], 'error');
                return;
            }
            var btn = $(this).prop('disabled', true).text('⏳ 测试中...');
            $.post(ajaxurl, {
                action: 'ppo_checkin_conn_test',
                cookie: s.cookie,
                url:    s.url,
                ip:     s.ip,
                _ajax_nonce: '<?php echo wp_create_nonce('ppo_checkin_conn_test'); ?>'
            }, function(resp) {
                if (resp.success) showIn($box, resp.data.lines, 'success');
                else showIn($box, resp.data.lines, 'error');
            }).fail(function() {
                showIn($box, ['❌ AJAX 请求失败，请检查网络'], 'error');
            }).always(function() {
                btn.prop('disabled', false).text('🔍 签到测试');
            });
        });

        // 立即签到
        $('#ppo_checkin_do_btn').on('click', function() {
            var $box = $('#ppo_checkin_do_result');
            var s = getSettings();
            if (!s.cookie || !s.url) {
                showIn($box, ['⚠️ 请先填写 Cookie 和目标站点 URL'], 'error');
                return;
            }
            var btn = $(this).prop('disabled', true).text('⏳ 签到中...');
            $.post(ajaxurl, {
                action: 'ppo_checkin_manual_ajax',
                cookie: s.cookie,
                url:    s.url,
                ip:     s.ip,
                _ajax_nonce: '<?php echo wp_create_nonce('ppo_checkin_manual_ajax'); ?>'
            }, function(resp) {
                if (resp.success) showIn($box, resp.data.lines, 'success');
                else showIn($box, resp.data.lines, 'error');
            }).fail(function() {
                showIn($box, ['❌ AJAX 请求失败'], 'error');
            }).always(function() {
                btn.prop('disabled', false).text('✅ 立即签到');
            });
        });

        // 清除记录
        $('#ppo_checkin_clear_btn').on('click', function() {
            var $box = $('#ppo_checkin_misc_result');
            var btn = $(this).prop('disabled', true).text('⏳ ...');
            $.post(ajaxurl, {
                action: 'ppo_checkin_clear_log_ajax',
                _ajax_nonce: '<?php echo wp_create_nonce('ppo_checkin_clear_log_ajax'); ?>'
            }, function() {
                showIn($box, ['🗑️ 记录已清除'], 'info');
            }).always(function() {
                btn.prop('disabled', false).text('🗑️ 清除记录');
            });
        });

        // 测试邮件
        $('#ppo_checkin_test_mail_btn').on('click', function() {
            var $box = $('#ppo_checkin_misc_result');
            var btn = $(this).prop('disabled', true).text('⏳ 发送中...');
            $.post(ajaxurl, {
                action: 'ppo_checkin_test_mail_ajax',
                _ajax_nonce: '<?php echo wp_create_nonce('ppo_checkin_test_mail_ajax'); ?>'
            }, function(resp) {
                if (resp.success) showIn($box, resp.data.lines, 'success');
                else showIn($box, resp.data.lines, 'error');
            }).fail(function() {
                showIn($box, ['❌ 邮件发送请求失败'], 'error');
            }).always(function() {
                btn.prop('disabled', false).text('📧 测试邮件');
            });
        });

        // 主动任务测试
        $('#ppo_checkin_task_btn').on('click', function() {
            var $box = $('#ppo_checkin_task_result');
            var s = getSettings();
            if (!s.cookie || !s.url) {
                showIn($box, ['⚠️ 请先填写 Cookie 和目标站点 URL'], 'error');
                return;
            }
            var moment_id = $('#ppo_checkin_moment_id').val();
            var comment_id = $('#ppo_checkin_comment_id').val();
            if (!moment_id || moment_id == '0') {
                showIn($box, ['⚠️ 请先填写片刻 ID'], 'error');
                return;
            }
            if (!comment_id || comment_id == '0') {
                showIn($box, ['⚠️ 请先填写评论 ID'], 'error');
                return;
            }
            var btn = $(this).prop('disabled', true).text('⏳ 任务执行中...');
            $.post(ajaxurl, {
                action: 'ppo_checkin_oneclick_task',
                cookie: s.cookie,
                url:    s.url,
                ip:     s.ip,
                moment_id: moment_id,
                comment_id: comment_id,
                _ajax_nonce: '<?php echo wp_create_nonce('ppo_checkin_oneclick_task'); ?>'
            }, function(resp) {
                if (resp.success) showIn($box, resp.data.lines, 'success');
                else showIn($box, resp.data.lines, 'error');
            }).fail(function() {
                showIn($box, ['❌ AJAX 请求失败'], 'error');
            }).always(function() {
                btn.prop('disabled', false).text('🧪 主动任务测试');
            });
        });

        // 被动任务测试
        $('#ppo_checkin_passive_btn').on('click', function() {
            var $box = $('#ppo_checkin_passive_result');
            var s = getSettings();
            if (!s.second_cookie || !s.url) {
                showIn($box, ['⚠️ 请先填写第二个账号 Cookie 和目标站点 URL'], 'error');
                return;
            }
            if ((!s.target_follow_id || s.target_follow_id == '0') && (!s.target_comment_id || s.target_comment_id == '0')) {
                showIn($box, ['⚠️ 请至少填写被关注用户 ID 或被点赞评论 ID 中的一个'], 'error');
                return;
            }
            var btn = $(this).prop('disabled', true).text('⏳ 被动任务测试中...');
            $.post(ajaxurl, {
                action: 'ppo_checkin_passive_task',
                second_cookie: s.second_cookie,
                url:    s.url,
                ip:     s.ip,
                target_follow_id: s.target_follow_id,
                target_comment_id: s.target_comment_id,
                _ajax_nonce: '<?php echo wp_create_nonce('ppo_checkin_passive_task'); ?>'
            }, function(resp) {
                if (resp.success) showIn($box, resp.data.lines, 'success');
                else showIn($box, resp.data.lines, 'error');
            }).fail(function() {
                showIn($box, ['❌ AJAX 请求失败'], 'error');
            }).always(function() {
                btn.prop('disabled', false).text('🌀 被动任务测试');
            });
        });

        // 补挂区 - 单独执行某个操作
        $('.rerow-btn').on('click', function() {
            var btn = $(this);
            var $row = btn.closest('.rerow-row');
            var op = btn.data('op');
            var times = $row.find('.rerow-times').val() || 1;
            var $box = $row.find('.rerow-result');
            var s = getSettings();

            btn.prop('disabled', true).text('⏳...');
            $.post(ajaxurl, {
                action: 'ppo_checkin_rerun_task',
                operation: op,
                times: times,
                cookie: s.cookie,
                url: s.url,
                ip: s.ip,
                second_cookie: s.second_cookie,
                _ajax_nonce: '<?php echo wp_create_nonce('ppo_checkin_rerun_task'); ?>'
            }, function(resp) {
                if (resp.success) showIn($box, resp.data.lines, 'success');
                else showIn($box, resp.data.lines, 'error');
            }).fail(function() {
                showIn($box, ['❌ 请求失败'], 'error');
            }).always(function() {
                btn.prop('disabled', false).text('执行');
            });
        });
    });
    </script>
    <?php
}
