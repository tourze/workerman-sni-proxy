<?php

namespace Tourze\Workerman\SNIProxy;

class ProxyConfiguration
{
    /**
     * 处理后的远程主机配置
     *
     * @var RemoteTarget[]
     */
    private array $remoteTargets;

    /**
     * 主机名到目标对象的映射
     *
     * @var array<string, RemoteTarget>
     */
    private array $hostTargetMap;

    /**
     * 绑定主机
     */
    private readonly string $bindHost;

    /**
     * 绑定端口
     */
    private readonly int $bindPort;

    /**
     * 构造函数
     */
    public function __construct(
        string $bindHost = '0.0.0.0',
        int $bindPort = 443,
        array $remoteHosts = []
    ) {
        $this->bindHost = $bindHost;
        $this->bindPort = $bindPort;

        // 处理远程主机配置
        $this->remoteTargets = [];
        $this->hostTargetMap = [];

        foreach ($remoteHosts as $hostString) {
            $target = RemoteTarget::fromString($hostString);
            $this->remoteTargets[] = $target;
            $this->hostTargetMap[$target->getHost()] = $target;
        }
    }

    /**
     * 获取绑定地址
     */
    public function getBindAddress(): string
    {
        return "tcp://{$this->bindHost}:{$this->bindPort}";
    }

    /**
     * 获取绑定主机
     */
    public function getBindHost(): string
    {
        return $this->bindHost;
    }

    /**
     * 获取绑定端口
     */
    public function getBindPort(): int
    {
        return $this->bindPort;
    }

    /**
     * 获取远程主机列表
     *
     * @return string[] 格式化后的主机列表，用于日志显示
     */
    public function getRemoteHosts(): array
    {
        $result = [];
        foreach ($this->remoteTargets as $target) {
            $result[] = "{$target->getHost()}:{$target->getPort()}";
        }
        return $result;
    }

    /**
     * 检查主机是否在允许列表中
     *
     * @param string $host 主机名
     * @return RemoteTarget|null 如果允许则返回目标对象，否则返回null
     */
    public function getAllowedTarget(string $host): ?RemoteTarget
    {
        if (empty($this->remoteTargets)) {
            // 如果未设置远程主机，默认允许所有
            return new RemoteTarget($host);
        }

        return $this->hostTargetMap[$host] ?? null;
    }
}
