<?php

declare(strict_types=1);

namespace Tourze\Workerman\SNIProxy;

class RemoteTarget
{
    /**
     * 构造函数
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port = 443,
    ) {
    }

    /**
     * 获取主机名
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * 获取端口
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * 获取连接地址
     */
    public function getAddress(): string
    {
        return "tcp://{$this->host}:{$this->port}";
    }

    /**
     * 从主机字符串创建实例
     * 支持IPv4、IPv6和域名格式
     *
     * 支持的格式：
     * - example.com
     * - example.com:443
     * - 192.168.1.1:443
     * - [2001:db8::1]:443
     * - 2001:db8::1 (默认端口443)
     */
    public static function fromString(string $hostString): self
    {
        // 处理IPv6格式 [host]:port
        if (str_starts_with($hostString, '[')) {
            return self::parseIpv6WithBrackets($hostString);
        }

        // 检查是否为无括号的IPv6地址或host:port格式
        if (str_contains($hostString, ':')) {
            return self::parseHostWithColon($hostString);
        }

        // 无冒号，作为纯主机名处理
        return new self($hostString, 443);
    }

    /**
     * 解析带括号的IPv6格式 [host]:port
     */
    private static function parseIpv6WithBrackets(string $hostString): self
    {
        $closeBracket = strpos($hostString, ']');
        if (false === $closeBracket) {
            // 无效的IPv6格式，作为普通主机名处理
            return new self($hostString, 443);
        }

        $host = substr($hostString, 1, $closeBracket - 1);
        $remaining = substr($hostString, $closeBracket + 1);

        if (str_starts_with($remaining, ':')) {
            $port = (int) substr($remaining, 1);

            return new self($host, $port > 0 ? $port : 443);
        }

        return new self($host, 443);
    }

    /**
     * 解析包含冒号的主机字符串
     */
    private static function parseHostWithColon(string $hostString): self
    {
        // 统计冒号数量来判断是否为IPv6
        $colonCount = substr_count($hostString, ':');

        if ($colonCount > 1) {
            // 多个冒号，可能是IPv6地址，不分割
            return new self($hostString, 443);
        }

        // 只有一个冒号，按标准 host:port 格式处理
        $parts = explode(':', $hostString, 2);
        if (2 === count($parts) && '' !== $parts[0] && '' !== $parts[1]) {
            $port = (int) $parts[1];

            return new self($parts[0], $port > 0 ? $port : 443);
        }

        return new self($hostString, 443);
    }
}
