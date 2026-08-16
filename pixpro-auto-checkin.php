<?php
/**
 * Plugin Name: PixPro 自动签到
 * Plugin URI: https://blog.aistu.cn
 * Description: 提供针对 pixpro 的每日自动签到功能。配置登录 Cookie 和目标站点后，插件会通过 WP Cron 定时自动执行签到，签到失败时自动发送邮件通知站长。
 * Version: 1.2.1
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: 摸鱼大王
 * Text Domain: pixpro-checkin
 */

defined('ABSPATH') || exit;

define('PPO_CHECKIN_VERSION', '1.2.1');
define('PPO_CHECKIN_OPTION', 'ppo_checkin_settings');
define('PPO_CHECKIN_FILE', __FILE__);
define('PPO_CHECKIN_DIR', plugin_dir_path(__FILE__));

// =============================================================
//  初始化
// =============================================================

add_action('plugins_loaded', function () {
    load_plugin_textdomain('pixpro-checkin', false, dirname(plugin_basename(PPO_CHECKIN_FILE)) . '/languages');
});

// =============================================================
//  模块加载（按依赖顺序）
// =============================================================

require_once PPO_CHECKIN_DIR . 'includes/settings.php';
require_once PPO_CHECKIN_DIR . 'includes/http.php';
require_once PPO_CHECKIN_DIR . 'includes/tasks.php';
require_once PPO_CHECKIN_DIR . 'includes/checkin.php';
require_once PPO_CHECKIN_DIR . 'includes/admin.php';
require_once PPO_CHECKIN_DIR . 'includes/ajax.php';
require_once PPO_CHECKIN_DIR . 'includes/rest.php';
