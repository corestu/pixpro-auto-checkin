<?php

if (!defined('ABSPATH')) exit;


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
//  Nonce / 登录态获取辅助
// =============================================================

/**
 * 获取 REST nonce。
 * 通过 WP 自带 admin-ajax.php?action=rest-nonce 获取，不会消耗签到。
 */
function ppo_checkin_fetch_rest_nonce($url, $cookie, $resolve = []) {
    $url = rtrim($url, '/');
    for ($try = 0; $try < 3; $try++) {
        $r = ppo_checkin_curl_request($url . '/wp-admin/admin-ajax.php?action=rest-nonce', $cookie, 'GET', '', [], $resolve, 20);
        if ($r['code'] === 200 && !empty($r['body'])) {
            $nonce = trim($r['body']);
            if ($nonce !== '' && $nonce !== '0' && $nonce !== '-1') {
                return $nonce;
            }
        }
    }
    return '';
}

/**
 * 获取页面中的 uid / user_nonce / msg_nonce。
 * 自动跟随一次重定向；不会触发签到。
 */
function ppo_checkin_fetch_page_nonces($url, $cookie, $resolve = []) {
    $url = rtrim($url, '/');
    $target = $url . '/';
    $r = ppo_checkin_curl_request($target, $cookie, 'GET', '', [], $resolve, 20);

    if (in_array($r['code'], [301, 302, 307, 308], true) && !empty($r['header_raw'])) {
        if (preg_match('/^location:\s*(.+)$/im', $r['header_raw'], $lm)) {
            $redirect_url = trim($lm[1]);
            if (strpos($redirect_url, 'http') !== 0) {
                $parsed = parse_url($url);
                $base_path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';
                $prefix = isset($redirect_url[0]) && $redirect_url[0] === '/' ? '' : $base_path . '/';
                $redirect_url = $parsed['scheme'] . '://' . $parsed['host'] . $prefix . ltrim($redirect_url, '/');
            }
            $r = ppo_checkin_curl_request($redirect_url, $cookie, 'GET', '', [], $resolve, 20);
        }
    }

    $body = $r['body'] ?? '';

    $uid = 0;
    if (preg_match('/"uid":\s*(\d+)/', $body, $m)) {
        $uid = (int) $m[1];
    }

    $extract = function ($key) use ($body) {
        if (preg_match('/"' . preg_quote($key, '/') . '":"([^"]+)"/', $body, $m)) {
            return $m[1];
        }
        return '';
    };

    return [
        'uid'        => $uid,
        'user_nonce' => $extract('user_nonce'),
        'msg_nonce'  => $extract('msg_nonce'),
        'body'       => $body,
    ];
}

/**
 * 兼容旧调用：仅获取 user_nonce。
 */
function ppo_checkin_fetch_user_nonce($url, $cookie, $resolve) {
    $page = ppo_checkin_fetch_page_nonces($url, $cookie, $resolve);
    return $page['user_nonce'] ?? '';
}
