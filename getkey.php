<?php
/**
 * 获取OSS上传密钥接口
 */

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 3600');

// 处理 OPTIONS 请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 从.env文件读取环境变量
$env_file = __DIR__ . '/.env';
$cookie_string = '';
$course_url = '';

if (file_exists($env_file)) {
    $env_content = file_get_contents($env_file);
    $lines = explode(PHP_EOL, $env_content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            if ($key === 'COOKIE_STRING') {
                $cookie_string = $value;
            } elseif ($key === 'COURSE_URL') {
                $course_url = $value;
            }
        }
    }
}

// 检查必要的参数
if (empty($cookie_string) || empty($course_url)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required parameters',
        'cookie_present' => !empty($cookie_string),
        'course_url_present' => !empty($course_url)
    ]);
    exit;
}

/**
 * 获取session cookie (s=部分)
 */
function get_session_cookie($remember_cookie, $url) {
    $headers = [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36",
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "Accept-Language: zh-CN,zh;q=0.9",
        "Cache-Control: max-age=0"
    ];
    
    if ($remember_cookie) {
        $headers[] = "Cookie: $remember_cookie";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $cookie = '';
    
    if ($response !== false) {
        // 查找s=部分的cookie，不区分大小写
        if (preg_match('/set-cookie:.*?s=([^;]+);/i', $response, $matches)) {
            $cookie = 's=' . $matches[1];
        } elseif (preg_match('/s=([^;]+);/', $response, $matches)) {
            $cookie = 's=' . $matches[1];
        }
    }
    
    return $cookie;
}

/**
 * 获取OSS上传密钥
 */
function get_oss_key($cookie, $referer_url) {
    $url = "https://k8n.cn/student/oss-upload-key";
    
    $headers = [
        "accept: */*",
        "accept-encoding: gzip, deflate, br, zstd",
        "accept-language: zh-CN,zh;q=0.9",
        "priority: u=1, i",
        "referer: $referer_url",
        "sec-ch-ua: \"Chromium\";v=\"146\", \"Not-A.Brand\";v=\"24\", \"Google Chrome\";v=\"146\"",
        "sec-ch-ua-mobile: ?0",
        "sec-ch-ua-platform: Windows",
        "sec-fetch-dest: empty",
        "sec-fetch-mode: cors",
        "sec-fetch-site: same-origin",
        "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36",
        "x-requested-with: XMLHttpRequest"
    ];
    
    if ($cookie) {
        $headers[] = "Cookie: $cookie";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_ENCODING, "gzip, deflate, br, zstd"); // 处理压缩响应
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode >= 200 && $httpcode < 300 && $response !== false) {
        $oss_config = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $oss_config;
        }
    }
    
    return false;
}

// 主逻辑
$remember_cookie = trim($cookie_string);

// 获取session cookie
$session_cookie = get_session_cookie($remember_cookie, $course_url);

// 合并cookie
if (!empty($session_cookie)) {
    $full_cookie = $remember_cookie . '; ' . $session_cookie;
} else {
    $full_cookie = $remember_cookie;
}

// 获取OSS密钥
$oss_key = get_oss_key($full_cookie, $course_url);

// 返回结果
if ($oss_key !== false) {
    header('Content-Type: application/json');
    echo json_encode($oss_key);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get OSS key']);
}
