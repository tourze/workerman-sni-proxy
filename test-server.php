<?php
/**
 * 注意：使用此文件需要先安装Monolog
 * composer require monolog/monolog
 */

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Tourze\Workerman\SNIProxy\SniProxyWorker;
use Workerman\Worker;

require_once __DIR__ . '/../../vendor/autoload.php';

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 初始化日志
$logger = new Logger('sni-proxy');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// 允许的远程主机列表，如果为空则允许所有
$allowedHosts = [
    // 格式: "主机名:端口"
    "www.baidu.com:443",
    "www.163.com:443",
    "www.qq.com:443",
    // 可以根据需要添加或删除
];

// 是否允许所有主机（测试用）
$allowAll = true;
if ($allowAll) {
    $allowedHosts = [];
}

// 创建一个SNI代理worker，使用非特权端口8443（避免需要root权限）
$worker = new SniProxyWorker('0.0.0.0', 8443, $allowedHosts, $logger);

// 设置进程数
$worker->count = 4;

// 启用调试模式
Worker::$logFile = './workerman.log';
Worker::$pidFile = './workerman.pid';
Worker::$stdoutFile = './stdout.log';

// 运行worker
Worker::runAll();
