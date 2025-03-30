<?php
/**
 * SNI代理测试客户端
 *
 * 此脚本用于测试SNI代理服务器是否正常工作
 * 使用方法：php test-client.php example.com [端口]
 */

if (!isset($argv[1])) {
    echo "使用方法: php test-client.php example.com [端口]\n";
    exit(1);
}

$host = $argv[1];
$port = isset($argv[2]) ? (int)$argv[2] : 8443;

echo "测试连接到 {$host}:{$port}...\n";

// 创建上下文选项，设置SNI主机名
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'SNI_enabled' => true,
        'peer_name' => $host,
    ]
]);

// 尝试连接
$socket = @stream_socket_client(
    "ssl://127.0.0.1:{$port}",
    $errno,
    $errstr,
    30,
    STREAM_CLIENT_CONNECT,
    $context
);

// 处理连接结果
if (!$socket) {
    echo "错误: 无法连接到代理服务器 ({$errno}: {$errstr})\n";
    exit(1);
}

echo "成功连接到代理服务器！\n";
echo "SNI主机名: {$host}\n";

// 获取证书信息
$cert = stream_context_get_options($context)['ssl']['peer_certificate'] ?? null;
if ($cert) {
    $certInfo = openssl_x509_parse($cert);
    echo "服务器证书信息:\n";
    echo "  - 颁发给: " . ($certInfo['subject']['CN'] ?? '未知') . "\n";
    echo "  - 颁发者: " . ($certInfo['issuer']['CN'] ?? '未知') . "\n";
    echo "  - 有效期至: " . date('Y-m-d H:i:s', $certInfo['validTo_time_t']) . "\n";
}

// 发送简单的HTTP请求
$request = "GET / HTTP/1.1\r\n";
$request .= "Host: {$host}\r\n";
$request .= "Connection: close\r\n";
$request .= "\r\n";

fwrite($socket, $request);

echo "\n接收到的响应头:\n";
$responseHeader = '';
$headerComplete = false;
$lineCount = 0;

// 只读取响应头
while (!feof($socket) && !$headerComplete && $lineCount < 20) {
    $line = fgets($socket);
    if ($line === "\r\n" || $line === "") {
        $headerComplete = true;
    } else {
        $responseHeader .= $line;
        $lineCount++;
    }
}

echo $responseHeader . "\n";

// 关闭连接
fclose($socket);
echo "测试完成.\n";
