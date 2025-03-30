# Workerman SNI Proxy

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/workerman-sni-proxy.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-sni-proxy)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/workerman-sni-proxy.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-sni-proxy)
[![License](https://img.shields.io/github/license/tourze/workerman-sni-proxy.svg?style=flat-square)](https://github.com/tourze/workerman-sni-proxy/blob/master/LICENSE)

A high-performance SNI (Server Name Indication) proxy server based on [Workerman](https://github.com/walkor/workerman).

## Features

- Parses SNI extension from client TLS handshakes to identify target hosts
- Routes connections to appropriate backend servers based on SNI hostname
- Supports whitelisting of allowed hostnames
- Provides flexible configuration options for binding address and port
- Handles connection pooling and management efficiently
- Integrates with PSR-compatible loggers (including Monolog)
- Minimal dependencies, lightweight design for optimal performance

## Installation

```bash
composer require tourze/workerman-sni-proxy
```

## Requirements

- PHP 8.1 or higher
- Workerman 5.1 or higher
- PSR-compatible logger (optional, Monolog recommended)

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
use Monolog\Logger;
use Tourze\Workerman\SNIProxy\SniProxyWorker;
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

// Initialize logger
$logger = new Logger('sni-proxy');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

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

## API Documentation

### SniProxyWorker

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
