# Workerman SNI 代理

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/workerman-sni-proxy.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-sni-proxy)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/workerman-sni-proxy.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-sni-proxy)
[![License](https://img.shields.io/github/license/tourze/workerman-sni-proxy.svg?style=flat-square)](https://github.com/tourze/workerman-sni-proxy/blob/master/LICENSE)

基于 [Workerman](https://github.com/walkor/workerman) 的高性能 SNI（服务器名称指示）代理服务器。

## 功能特点

- 解析客户端 TLS 握手中的 SNI 扩展以识别目标主机
- 根据 SNI 主机名将连接路由到适当的后端服务器
- 支持允许访问的主机名白名单
- 提供绑定地址和端口的灵活配置选项
- 高效处理连接池和管理
- 集成 PSR 兼容的日志记录器（包括 Monolog）
- 最小化依赖，轻量级设计以获得最佳性能

## 安装

```bash
composer require tourze/workerman-sni-proxy
```

## 系统要求

- PHP 8.1 或更高版本
- Workerman 5.1 或更高版本
- PSR 兼容的日志记录器（可选，推荐使用 Monolog）

## 快速开始

### 基本示例

```php
<?php

use Tourze\Workerman\SNIProxy\SniProxyWorker;
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

// 创建一个监听 8443 端口的 SNI 代理 worker
$worker = new SniProxyWorker('0.0.0.0', 8443);

// 设置 worker 进程数
$worker->count = 4;

// 运行 worker
Worker::runAll();
```

### 使用主机白名单和日志记录

```php
<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Tourze\Workerman\SNIProxy\SniProxyWorker;
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

// 初始化日志记录器
$logger = new Logger('sni-proxy');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// 允许的主机白名单（格式："主机名:端口"）
$allowedHosts = [
    "www.example.com:443",
    "api.example.com:443"
];

// 创建带有白名单和日志记录器的 SNI 代理 worker
$worker = new SniProxyWorker('0.0.0.0', 8443, $allowedHosts, $logger);

// 设置 worker 进程数
$worker->count = 4;

// 运行 worker
Worker::runAll();
```

## API 文档

### SniProxyWorker

```php
/**
 * @param string $bindHost 绑定地址
 * @param int $bindPort 绑定端口
 * @param array $remoteHosts 允许的主机列表（格式：["主机名:端口", ...]）
 * @param LoggerInterface|null $logger PSR 兼容的日志记录器实例
 */
public function __construct(
    string $bindHost = '0.0.0.0',
    int $bindPort = 443,
    array $remoteHosts = [],
    ?LoggerInterface $logger = null
)
```

## 使用场景

- 基于域名的 TLS 流量路由
- 为 HTTPS 服务实现虚拟主机
- 边缘服务器的 SSL/TLS 卸载
- 监控和调试 TLS 流量
- HTTPS 服务的访问控制

## 注意事项

- 使用 443 端口需要 root 权限
- 测试时，可以使用非特权端口如 8443
- 在生产环境中，建议使用 systemd 或 supervisor 来管理进程
- 性能随 worker 进程数量而扩展

## 许可证

本项目采用 MIT 许可证，详情请查看 [License 文件](LICENSE)。
