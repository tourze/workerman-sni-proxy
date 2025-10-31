# Workerman SNI Proxy

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/workerman-sni-proxy.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-sni-proxy)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/workerman-sni-proxy.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-sni-proxy)
[![License](https://img.shields.io/github/license/tourze/workerman-sni-proxy.svg?style=flat-square)](https://github.com/tourze/workerman-sni-proxy/blob/master/LICENSE)

[![PHP Version](https://img.shields.io/packagist/php-v/tourze/workerman-sni-proxy.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-sni-proxy)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/workerman-sni-proxy/ci.yml?style=flat-square)](https://github.com/tourze/workerman-sni-proxy/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/workerman-sni-proxy.svg?style=flat-square)](https://codecov.io/gh/tourze/workerman-sni-proxy)

A high-performance SNI (Server Name Indication) proxy server based on 
[Workerman](https://github.com/walkor/workerman).

## Features

- Parses SNI extension from client TLS handshakes to identify target hosts
- Routes connections to appropriate backend servers based on SNI hostname
- Supports whitelisting of allowed hostnames
- Provides flexible configuration options for binding address and port
- Handles connection pooling and management efficiently
- Integrates with PSR-compatible loggers (including Monolog)
- Minimal dependencies, lightweight design for optimal performance

## Requirements

- PHP 8.1 or higher
- Workerman 5.1 or higher
- PSR-compatible logger (optional, Monolog recommended)

## Installation

```bash
composer require tourze/workerman-sni-proxy
```

## Quick Start

### Basic Example

```php
<?php

use Tourze\Workerman\SNIProxy\SniProxyWorker;
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

// Create an SNI proxy worker listening on port 8443
$worker = new SniProxyWorker('0.0.0.0', 8443);

// Set worker process count
$worker->count = 4;

// Run worker
Worker::runAll();
```

### With Host Whitelist and Logging

```php
<?php

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Tourze\Workerman\SNIProxy\SniProxyWorker;
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

// Initialize logger
$logger = new Logger('sni-proxy');
$logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));

// Whitelist of allowed hosts (format: "hostname:port")
$allowedHosts = [
    "www.example.com:443",
    "api.example.com:443"
];

// Create an SNI proxy worker with whitelist and logger
$worker = new SniProxyWorker('0.0.0.0', 8443, $allowedHosts, $logger);

// Set worker process count
$worker->count = 4;

// Run worker
Worker::runAll();
```

## Configuration

### SniProxyWorker Constructor

```php
/**
 * @param string $bindHost Binding address
 * @param int $bindPort Binding port
 * @param array $remoteHosts Allowed hosts list (format: ["hostname:port", ...])
 * @param LoggerInterface|null $logger PSR-compatible logger instance
 */
public function __construct(
    string $bindHost = '0.0.0.0',
    int $bindPort = 443,
    array $remoteHosts = [],
    ?LoggerInterface $logger = null
)
```

### Configuration Options

- **bindHost**: The IP address to bind the proxy server (default: '0.0.0.0')
- **bindPort**: The port to listen on (default: 443)
- **remoteHosts**: Array of allowed destination hosts in "hostname:port" format
- **logger**: PSR-3 compatible logger for debugging and monitoring

## Advanced Usage

### Custom Remote Target Resolution

```php
use Tourze\Workerman\SNIProxy\RemoteTarget;

// Custom target resolution logic
$worker = new SniProxyWorker('0.0.0.0', 8443);
$worker->onConnect = function($connection) {
    // Custom connection handling
};
```

### Production Deployment

```php
// daemon.php
<?php
use Workerman\Worker;
use Tourze\Workerman\SNIProxy\SniProxyWorker;

require_once __DIR__ . '/vendor/autoload.php';

// Run as daemon
Worker::$daemonize = true;
Worker::$pidFile = '/var/run/sni-proxy.pid';
Worker::$logFile = '/var/log/sni-proxy.log';

$worker = new SniProxyWorker('0.0.0.0', 443);
$worker->count = 8; // 8 processes

Worker::runAll();
```

### Monitoring and Metrics

```php
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

$logger = new Logger('sni-proxy');
$logger->pushHandler(new StreamHandler('/var/log/sni-proxy.log', Level::Info));

$worker = new SniProxyWorker('0.0.0.0', 8443, [], $logger);

// Monitor connections
$worker->onConnect = function($connection) use ($logger) {
    $logger->info('New connection from ' . $connection->getRemoteIp());
};

$worker->onClose = function($connection) use ($logger) {
    $logger->info('Connection closed from ' . $connection->getRemoteIp());
};
```

## Use Cases

- TLS traffic routing based on domain name
- Implementing virtual hosting for HTTPS services
- SSL/TLS offloading at edge servers
- Monitoring and debugging TLS traffic
- Access control for HTTPS services

## Notes

- Using port 443 requires root privileges
- For testing, use a non-privileged port like 8443
- In production, consider using systemd or supervisor to manage the process
- Performance scales with the number of worker processes

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
