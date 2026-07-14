<?php
/**
 * Plugin Name: PixPro 自动签到
 * Plugin URI: https://blog.aistu.cn
 * Description: 提供针对pixpro的每日自动签到功能。配置登录 Cookie 和目标站点后，插件会通过 WP Cron 定时自动执行签到，签到失败时自动发送邮件通知站长。
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: 摸鱼大王
 * Text Domain: pixpro-checkin
 */

defined('ABSPATH') || exit;

define('PPO_CHECKIN_VERSION', '1.2.0');
define('PPO_CHECKIN_OPTION', 'ppo_checkin_settings');

// =============================================================
//  初始化
// =============================================================

add_action('plugins_loaded', function () {
    load_plugin_textdomain('pixpro-checkin', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

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

        <form method="post" action="options.php">
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

    // 构造 settings 传给辅助函数
    $settings = ['url' => $url, 'ip' => $ip];

    // 步骤一：站点连通性
    $head_url = $url;
    $head_headers = [];
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
        $parts = parse_url($url);
        $head_url = $parts['scheme'] . '://' . $ip . ($parts['path'] ?? '');
        $head_headers['Host'] = $parts['host'];
    }

    $head = wp_remote_head($head_url, ['timeout' => 15, 'sslverify' => false, 'headers' => $head_headers]);
    if (is_wp_error($head)) {
        $lines[] = '❌ 站点无法访问：' . $head->get_error_message();
        wp_send_json_error(['lines' => $lines]);
    }
    $code = wp_remote_retrieve_response_code($head);
    if ($code >= 200 && $code < 400) {
        $lines[] = '✅ 站点可达';
    } else {
        $lines[] = '❌ 站点返回 HTTP ' . $code;
        wp_send_json_error(['lines' => $lines]);
    }

    // 步骤二：admin-ajax.php + Cookie 验证
    $ajax_url = ppo_checkin_build_ajax_url($settings);
    $resolve  = ppo_checkin_get_resolve_ip($settings);

    $curl_result = ppo_checkin_curl_direct($ajax_url, $cookie, $resolve, 20);
    $http_code = $curl_result['code'];
    $body      = $curl_result['body'] ?? '';
    $curl_err  = $curl_result['error'] ?? '';

    if (empty($http_code) && !empty($curl_err)) {
        $lines[] = '❌ 请求失败：' . $curl_err;
        wp_send_json_error(['lines' => $lines]);
    }

    $data = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        $msg = $data['msg'] ?? '';
        if ($msg === '签到成功') {
            $lines[] = '✅ Cookie 有效，签到成功！';
            $lines[] = '📊 经验 +' . intval($data['xp'] ?? 0) . '，积分 +' . intval($data['point'] ?? 0);
            update_option('ppo_checkin_last_result', [
                'success' => true,
                'time'    => wp_date('Y-m-d H:i:s'),
                'message' => $msg,
                'xp'      => intval($data['xp'] ?? 0),
                'point'   => intval($data['point'] ?? 0),
            ]);
        } elseif ($msg === '今日已签到') {
            $lines[] = '✅ Cookie 有效（今日已签到）';
            update_option('ppo_checkin_last_result', [
                'success' => true,
                'time'    => wp_date('Y-m-d H:i:s'),
                'message' => $msg,
            ]);
        } else {
            $lines[] = '⚠️ 未知响应：' . $msg;
        }
    } elseif (stripos($body, 'login') !== false) {
        $lines[] = '❌ Cookie 已失效，请重新获取';
    } elseif (trim($body) === '0' || trim($body) === '-1') {
        $lines[] = '❌ 目标站点未安装 PixPro 主题或签到功能未开启';
    } elseif (trim($body) === '') {
        $lines[] = '❌ 服务器无响应（HTTP ' . $http_code . '）';
        $lines[] = '💡 提示：如果服务器修改了 hosts，请在"目标站点 IP"框里填写真实 IP';
    } else {
        $lines[] = '❌ 返回数据异常：' . mb_substr($body, 0, 100);
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

    wp_send_json_success(['lines' => $lines]);
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
        wp_send_json_error(['lines' => $lines]);
    }

    // 签到成功后执行 10 轮主任务 + 5 轮关注任务 + 5 次私信
    $moment_id  = max(1, intval($settings['moment_id'] ?? 0));
    $comment_id = max(1, intval($settings['comment_id'] ?? 0));
    $follow_id  = max(1, intval($settings['follow_id'] ?? 0));
    $msg_id     = max(1, intval($settings['msg_id'] ?? 0));
    $resolve    = ppo_checkin_get_resolve_ip($settings);
    $task_ret   = ['total_fail' => 0];
    $log['active_msg'] = '';
    if ($moment_id && $comment_id) {
        $task_ret = ppo_checkin_run_tasks($settings['cookie'], $url, $resolve, $moment_id, $comment_id, 10, $follow_id, 5, $msg_id);
        $lines[]  = $task_ret['message'];
        $log['active_msg'] = $task_ret['message'];
    }

    // 被动任务（第二个账号）
    $second_cookie     = $settings['second_cookie'] ?? '';
    $target_follow_id  = max(1, intval($settings['target_follow_id'] ?? 0));
    $target_comment_id = max(1, intval($settings['target_comment_id'] ?? 0));
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

// =============================================================
//  通用 curl 请求（支持 GET / POST / DELETE + 自定义 Header）
// =============================================================

function ppo_checkin_curl_request($url, $cookie_string, $method = 'GET', $post_fields = '', $extra_headers = [], $resolve = [], $timeout = 20) {
    if (!function_exists('curl_init')) {
        return ['code' => 0, 'body' => '', 'error' => 'PHP curl 扩展未安装'];
    }

    $ch = curl_init();
    $headers = array_merge(['Accept: application/json, */*'], $extra_headers);

    $curl_opts = [
        CURLOPT_URL              => $url,
        CURLOPT_RETURNTRANSFER   => true,
        CURLOPT_TIMEOUT          => $timeout,
        CURLOPT_CONNECTTIMEOUT   => 10,
        CURLOPT_SSL_VERIFYPEER   => false,
        CURLOPT_SSL_VERIFYHOST   => 0,
        CURLOPT_FOLLOWLOCATION   => false,
        CURLOPT_HTTP_VERSION     => CURL_HTTP_VERSION_NONE,
        CURLOPT_USERAGENT        => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_COOKIE           => $cookie_string,
        CURLOPT_HTTPHEADER       => $headers,
        CURLOPT_HEADER           => true,
    ];

    if ($method === 'POST') {
        $curl_opts[CURLOPT_POST] = true;
        $curl_opts[CURLOPT_POSTFIELDS] = $post_fields;
    } elseif ($method === 'DELETE') {
        $curl_opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        if ($post_fields !== '') {
            $curl_opts[CURLOPT_POSTFIELDS] = $post_fields;
        }
    }

    if (!empty($resolve)) {
        $curl_opts[CURLOPT_RESOLVE] = $resolve;
    }

    curl_setopt_array($ch, $curl_opts);

    $response    = curl_exec($ch);
    $http_code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error       = curl_error($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false || $response === null) {
        return ['code' => $http_code ?: 0, 'body' => '', 'error' => $error ?: 'curl 请求失败'];
    }

    $response_header = substr($response, 0, $header_size);
    $response_body   = substr($response, $header_size);

    $content_type = '';
    if (preg_match('/^content-type:\s*(.+)$/im', $response_header, $m)) {
        $content_type = trim($m[1]);
    }

    return [
        'code'         => $http_code,
        'body'         => $response_body,
        'content_type' => $content_type,
        'header_raw'   => trim($response_header),
    ];
}

// =============================================================
//  一键任务：单轮 6 操作（收藏/取消收藏/点赞/取消点赞/评论点赞/评论取消）
// =============================================================

function ppo_checkin_run_one_cycle($cookie, $url, $resolve, $moment_id, $comment_id, $rest_nonce, $user_nonce) {
    $ajax_url     = $url . '/wp-admin/admin-ajax.php';
    $like_url     = $url . '/wp-json/ppo/v1/moments/' . $moment_id . '/like';
    $content_form = ['Content-Type: application/x-www-form-urlencoded'];
    $collect_ok = 0; $collect_fail = 0;
    $like_ok = 0; $like_fail = 0;
    $comment_ok = 0; $comment_fail = 0;

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

    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=like_or_unlike_comment&comment_id=' . $comment_id . '&liked=0',
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['success']) && !empty($d['data']['liked'])) $comment_ok++; else $comment_fail++;

    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=like_or_unlike_comment&comment_id=' . $comment_id . '&liked=1',
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['success']) && empty($d['data']['liked'])) $comment_ok++; else $comment_fail++;

    return [
        'ok' => $collect_ok + $like_ok + $comment_ok,
        'fail' => $collect_fail + $like_fail + $comment_fail,
        'collect_ok' => $collect_ok, 'collect_fail' => $collect_fail,
        'like_ok' => $like_ok, 'like_fail' => $like_fail,
        'comment_ok' => $comment_ok, 'comment_fail' => $comment_fail,
    ];
}

// =============================================================
//  单轮关注/取关（无需 nonce）
// =============================================================

function ppo_checkin_run_follow_cycle($cookie, $url, $resolve, $follow_id) {
    $ajax_url = $url . '/wp-admin/admin-ajax.php';
    $content_form = ['Content-Type: application/x-www-form-urlencoded'];
    $ok = 0; $fail = 0;

    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=ppo_follow_user_ajax&following_id=' . $follow_id,
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['success'])) $ok++; else $fail++;

    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=ppo_unfollow_user_ajax&following_id=' . $follow_id,
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['success'])) $ok++; else $fail++;

    return ['ok' => $ok, 'fail' => $fail];
}

/**
 * 单轮发送私信
 */
function ppo_checkin_run_msg_cycle($cookie, $url, $resolve, $msg_id) {
    $ajax_url     = $url . '/wp-admin/admin-ajax.php';
    $content_form = ['Content-Type: application/x-www-form-urlencoded'];

    $r = ppo_checkin_curl_request($ajax_url, $cookie, 'POST',
        'action=send_private_msg&receive_id=' . $msg_id . '&msg=' . urlencode('经验+2'),
        $content_form, $resolve, 15);
    $d = json_decode($r['body'], true);
    if (!empty($d['status']) && $d['status'] == 1) return ['ok' => 1, 'fail' => 0];
    return ['ok' => 0, 'fail' => 1];
}

/**
 * 获取 user_nonce，自动跟随重定向。
 */
function ppo_checkin_fetch_user_nonce($url, $cookie, $resolve) {
    $target = $url . '/';
    $r = ppo_checkin_curl_request($target, $cookie, 'GET', '', [], $resolve, 20);
    // 如果返回重定向，手动跟随 Location
    if (in_array($r['code'], [301, 302, 307, 308]) && !empty($r['header_raw'])) {
        if (preg_match('/^location:\s*(.+)$/im', $r['header_raw'], $lm)) {
            $redirect_url = trim($lm[1]);
            // 相对路径补全
            if (strpos($redirect_url, 'http') !== 0) {
                $parsed = parse_url($url);
                $redirect_url = $parsed['scheme'] . '://' . $parsed['host'] . $redirect_url;
            }
            $r = ppo_checkin_curl_request($redirect_url, $cookie, 'GET', '', [], $resolve, 20);
        }
    }
    if (!empty($r['body']) && preg_match('/"user_nonce":"([^"]+)"/', $r['body'], $m)) {
        return $m[1];
    }
    return '';
}

/**
 * 循环执行多轮任务（主任务 + 关注任务 + 私信），返回汇总信息。
 * @param int $times 主任务循环次数
 * @param int $follow_times 关注任务循环次数
 * @param int $msg_times 私信发送次数
 * @return array ['total_ok'=>int, 'total_fail'=>int, 'message'=>string]
 */
function ppo_checkin_run_tasks($cookie, $url, $resolve, $moment_id, $comment_id, $times = 10, $follow_id = 0, $follow_times = 5, $msg_id = 0, $msg_times = 5) {
    $rest_nonce = '';
    $user_nonce = '';

    for ($try = 0; $try < 3; $try++) {
        if (!$rest_nonce) {
            $r = ppo_checkin_curl_request($url . '/wp-admin/admin-ajax.php?action=rest-nonce', $cookie, 'GET', '', [], $resolve, 20);
            if ($r['code'] === 200 && !empty($r['body'])) {
                $rest_nonce = trim($r['body']);
            }
        }

        if (!$user_nonce) {
            $user_nonce = ppo_checkin_fetch_user_nonce($url, $cookie, $resolve);
        }

        if ($rest_nonce && $user_nonce) break;
    }

    if (!$rest_nonce || !$user_nonce) {
        $fail_msg = [];
        if (!$rest_nonce) $fail_msg[] = 'rest_nonce';
        if (!$user_nonce) $fail_msg[] = 'user_nonce';
        return ['total_ok' => 0, 'total_fail' => ($times * 6 + $follow_times * 2 + ($msg_id ? $msg_times : 0)), 'message' => '⚠️ 重试3次后仍无法获取 ' . implode('、', $fail_msg) . '，任务已跳过'];
    }

    $total_ok = 0; $total_fail = 0;
    $collect_ok = 0; $collect_fail = 0;
    $like_ok = 0; $like_fail = 0;
    $comment_ok = 0; $comment_fail = 0;

    // 主任务（收藏/点赞/评论）
    for ($i = 0; $i < $times; $i++) {
        $ret = ppo_checkin_run_one_cycle($cookie, $url, $resolve, $moment_id, $comment_id, $rest_nonce, $user_nonce);
        $total_ok  += $ret['ok'];
        $total_fail += $ret['fail'];
        $collect_ok  += $ret['collect_ok'];  $collect_fail  += $ret['collect_fail'];
        $like_ok     += $ret['like_ok'];     $like_fail     += $ret['like_fail'];
        $comment_ok  += $ret['comment_ok'];  $comment_fail  += $ret['comment_fail'];
    }

    // 关注任务
    $follow_ok = 0; $follow_fail = 0;
    if ($follow_id) {
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
    if ($msg_id) {
        for ($i = 0; $i < $msg_times; $i++) {
            $ret = ppo_checkin_run_msg_cycle($cookie, $url, $resolve, $msg_id);
            $msg_ok   += $ret['ok'];
            $msg_fail += $ret['fail'];
        }
        $total_ok   += $msg_ok;
        $total_fail += $msg_fail;
    }

    // 构建详细消息
    $detail = [];
    $collect_total = $collect_ok + $collect_fail;
    $detail[] = '    收藏操作...（' . $collect_ok . '/' . $collect_total . '）' . ($collect_fail === 0 ? '✅' : '❌');
    $like_total = $like_ok + $like_fail;
    $detail[] = '    点赞操作...（' . $like_ok . '/' . $like_total . '）' . ($like_fail === 0 ? '✅' : '❌');
    $comment_total = $comment_ok + $comment_fail;
    $detail[] = '    评论操作...（' . $comment_ok . '/' . $comment_total . '）' . ($comment_fail === 0 ? '✅' : '❌');
    if ($follow_id) {
        $follow_total = $follow_ok + $follow_fail;
        $detail[] = '    关注操作...（' . $follow_ok . '/' . $follow_total . '）' . ($follow_fail === 0 ? '✅' : '❌');
    }
    if ($msg_id) {
        $msg_total = $msg_ok + $msg_fail;
        $detail[] = '    私信操作...（' . $msg_ok . '/' . $msg_total . '）' . ($msg_fail === 0 ? '✅' : '❌');
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
 * @param int $follow_times  关注/取消关注循环次数
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
    $moment_id  = max(1, intval($_POST['moment_id'] ?? 0));
    $comment_id = max(1, intval($_POST['comment_id'] ?? 0));

    if (!$cookie || !$url) {
        wp_send_json_error(['lines' => ['⚠️ 请先填写 Cookie 和目标站点 URL']]);
    }
    if (!$moment_id)  wp_send_json_error(['lines' => ['⚠️ 请先填写片刻 ID']]);
    if (!$comment_id) wp_send_json_error(['lines' => ['⚠️ 请先填写评论 ID']]);

    $resolve = ppo_checkin_get_resolve_ip(['ip' => $ip, 'url' => $url]);

    $lines = [];

    // 用 run_tasks 跑 1 轮（nonce 获取和执行方式与 cron 完全一致）
    $settings = ppo_checkin_get_settings();
    $follow_id = max(1, intval($settings['follow_id'] ?? 0));
    $msg_id    = max(1, intval($settings['msg_id'] ?? 0));
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
    $target_follow_id  = max(1, intval($_POST['target_follow_id'] ?? 0));
    $target_comment_id = max(1, intval($_POST['target_comment_id'] ?? 0));

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
    $lines = [];
    $ok = 0; $fail = 0;

    $moment_id  = max(1, intval($settings['moment_id'] ?? 0));
    $comment_id = max(1, intval($settings['comment_id'] ?? 0));
    $follow_id  = max(1, intval($settings['follow_id'] ?? 0));
    $msg_id     = max(1, intval($settings['msg_id'] ?? 0));
    $target_follow_id  = max(1, intval($settings['target_follow_id'] ?? 0));
    $target_comment_id = max(1, intval($settings['target_comment_id'] ?? 0));

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
            for ($i = 0; $i < $times; $i++) {
                $ret = ppo_checkin_run_msg_cycle($cookie, $url, $resolve, $msg_id);
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

    $settings['cookie']   = wp_unslash($input['cookie'] ?? '');
    $settings['url']      = esc_url_raw($input['url'] ?? '', ['http', 'https']);
    $settings['notify']   = !empty($input['notify']) ? 1 : 0;

    // IP 验证
    $raw_ip = trim($input['ip'] ?? '');
    $settings['ip'] = ($raw_ip !== '' && filter_var($raw_ip, FILTER_VALIDATE_IP)) ? $raw_ip : '';

    $settings['moment_id']  = max(0, intval($input['moment_id'] ?? 0));
    $settings['comment_id'] = max(0, intval($input['comment_id'] ?? 0));
    $settings['follow_id']  = max(0, intval($input['follow_id'] ?? 0));

    $settings['second_cookie']     = wp_unslash($input['second_cookie'] ?? '');
    $settings['target_follow_id']  = max(0, intval($input['target_follow_id'] ?? 0));
    $settings['target_comment_id'] = max(0, intval($input['target_comment_id'] ?? 0));
    $settings['msg_id']            = max(0, intval($input['msg_id'] ?? 0));

    // 保存自定义执行时/分
    $hour   = min(23, max(0, intval($input['cron_hour'] ?? 8)));
    $minute = min(59, max(0, intval($input['cron_minute'] ?? 0)));
    update_option('ppo_checkin_cron_hour', $hour);
    update_option('ppo_checkin_cron_minute', $minute);

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

add_action('ppo_checkin_cron_hook', 'ppo_checkin_do_checkin');

// =============================================================
//  URL / DNS 构建辅助
// =============================================================

/**
 * 构建 admin-ajax.php 完整 URL。
 * 保留域名，IP 直连后续由 CURLOPT_RESOLVE 接管。
 */
function ppo_checkin_build_ajax_url($settings) {
    if (empty($settings['url'])) {
        return '';
    }
    $base_url = trailingslashit($settings['url']);
    return add_query_arg(['action' => 'ppo_checkin'], $base_url . 'wp-admin/admin-ajax.php');
}

/**
 * 生成 CURLOPT_RESOLVE 数组，等价于 curl --connect-to "::IP"。
 * 不设 IP 时返回空数组。
 */
function ppo_checkin_get_resolve_ip($settings) {
    $ip = !empty($settings['ip']) ? trim($settings['ip']) : '';
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return [];
    }
    $parts = parse_url(trailingslashit($settings['url']));
    if (empty($parts['host'])) {
        return [];
    }
    $port = (!empty($parts['scheme']) && $parts['scheme'] === 'https') ? 443 : 80;
    return ["{$parts['host']}:{$port}:{$ip}"];
}

// =============================================================
//  PHP curl 直连（绕过 WP HTTP API）
// =============================================================

/**
 * 直接用 PHP curl_* 发送请求。
 * 关键：CURLOPT_HTTP_VERSION = CURL_HTTP_VERSION_NONE 让 curl 协商 HTTP/2。
 *
 * @param string $url           完整 URL（已含 action=ppo_checkin）
 * @param string $cookie_string 原始 Cookie 字符串
 * @param array  $resolve       CURLOPT_RESOLVE 数组（IP 直连时传）
 * @param int    $timeout       超时秒数
 * @return array ['code'=>int,'body'=>string,'content_type'=>string,...]
 */
function ppo_checkin_curl_direct($url, $cookie_string, $resolve = [], $timeout = 20) {
    if (!function_exists('curl_init')) {
        return ['code' => 0, 'body' => '', 'error' => 'PHP curl 扩展未安装'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL              => $url,
        CURLOPT_RETURNTRANSFER   => true,
        CURLOPT_TIMEOUT          => $timeout,
        CURLOPT_CONNECTTIMEOUT   => 10,
        CURLOPT_SSL_VERIFYPEER   => false,
        CURLOPT_SSL_VERIFYHOST   => 0,
        CURLOPT_FOLLOWLOCATION   => false,
        CURLOPT_HTTP_VERSION     => CURL_HTTP_VERSION_NONE,
        CURLOPT_USERAGENT        => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_COOKIE           => $cookie_string,
        CURLOPT_HTTPHEADER       => ['Accept: application/json, */*'],
        CURLOPT_HEADER           => true,
    ]);

    if (!empty($resolve)) {
        curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
    }

    $response    = curl_exec($ch);
    $http_code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error       = curl_error($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curl_ver    = curl_version()['version'] ?? '未知';
    $ssl_ver     = curl_version()['ssl_version'] ?? '未知';
    curl_close($ch);

    if ($response === false || $response === null) {
        return [
            'code'       => $http_code ?: 0,
            'body'       => '',
            'error'      => $error ?: 'curl 请求失败',
            'curl_ver'   => $curl_ver,
            'ssl_ver'    => $ssl_ver,
        ];
    }

    $response_header = substr($response, 0, $header_size);
    $response_body   = substr($response, $header_size);

    // 提取 Content-Type
    $content_type = '';
    if (preg_match('/^content-type:\s*(.+)$/im', $response_header, $m)) {
        $content_type = trim($m[1]);
    }

    return [
        'code'         => $http_code,
        'body'         => $response_body,
        'content_type' => $content_type,
        'curl_ver'     => $curl_ver,
        'ssl_ver'      => $ssl_ver,
        'header_raw'   => trim($response_header),
    ];
}

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

    $task_fail_message = '';

    if (!empty($result['success'])) {
        $log['checkin_msg'] = '✅ 签到成功';

        // 主动任务
        $moment_id  = max(1, intval($settings['moment_id'] ?? 0));
        $comment_id = max(1, intval($settings['comment_id'] ?? 0));
        $follow_id  = max(1, intval($settings['follow_id'] ?? 0));
        $msg_id     = max(1, intval($settings['msg_id'] ?? 0));
        $resolve    = ppo_checkin_get_resolve_ip($settings);
        $log['active_msg'] = '';
        if ($moment_id && $comment_id) {
            try {
                $task_ret = ppo_checkin_run_tasks($settings['cookie'], $settings['url'], $resolve, $moment_id, $comment_id, 10, $follow_id, 5, $msg_id);
                $log['active_msg'] = $task_ret['message'];
                if ($task_ret['total_fail'] > 0) {
                    $task_fail_message .= '主动任务失败：' . $task_ret['message'] . "\n";
                }
            } catch (\Throwable $e) {
                $log['active_msg'] = '❌ 主动任务异常：' . $e->getMessage();
                $task_fail_message .= '主动任务异常：' . $e->getMessage() . "\n";
            }
        }

        // 被动任务（第二个账号）
        $second_cookie     = $settings['second_cookie'] ?? '';
        $target_follow_id  = max(1, intval($settings['target_follow_id'] ?? 0));
        $target_comment_id = max(1, intval($settings['target_comment_id'] ?? 0));
        $log['passive_msg'] = '';
        if ($second_cookie && ($target_follow_id || $target_comment_id)) {
            try {
                $passive_ret = ppo_checkin_run_passive_tasks(
                    $second_cookie, $settings['url'], $resolve,
                    $target_follow_id, $target_comment_id, 5, 10
                );
                $log['passive_msg'] = $passive_ret['message'];
                if ($passive_ret['total_fail'] > 0) {
                    $task_fail_message .= '被动任务失败：' . $passive_ret['message'] . "\n";
                }
            } catch (\Throwable $e) {
                $log['passive_msg'] = '❌ 被动任务异常：' . $e->getMessage();
                $task_fail_message .= '被动任务异常：' . $e->getMessage() . "\n";
            }
        }
    }

    update_option('ppo_checkin_last_result', $log);

    if (empty($result['success'])) {
        ppo_checkin_send_notify($result['message'] ?? __('未知错误', 'pixpro-checkin'));
    } elseif (!empty($task_fail_message)) {
        ppo_checkin_send_notify($task_fail_message);
    }
}

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

register_activation_hook(__FILE__, 'ppo_checkin_activate');
function ppo_checkin_activate() {
    ppo_checkin_schedule_cron();
}

register_deactivation_hook(__FILE__, 'ppo_checkin_deactivate');
function ppo_checkin_deactivate() {
    $timestamp = wp_next_scheduled('ppo_checkin_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'ppo_checkin_cron_hook');
    }
}

// 卸载时清理所有数据
register_uninstall_hook(__FILE__, 'ppo_checkin_uninstall');
function ppo_checkin_uninstall() {
    delete_option(PPO_CHECKIN_OPTION);
    delete_option('ppo_checkin_last_result');
    delete_option('ppo_checkin_cron_hour');
    delete_option('ppo_checkin_cron_minute');
}

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
