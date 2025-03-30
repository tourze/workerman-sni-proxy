<?php

namespace Tourze\Workerman\SNIProxy;

class RemoteTarget
{
    /**
     * 构造函数
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port = 443
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
     */
    public static function fromString(string $hostString): self
    {
        $parts = explode(':', $hostString);
        if (count($parts) === 2) {
            return new self($parts[0], (int)$parts[1]);
        }
        return new self($hostString, 443);
    }
}
