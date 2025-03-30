<?php

namespace Tourze\Workerman\SNIProxy;

use Psr\Log\LoggerInterface;
use WeakMap;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;

class ConnectionManager
{
    /**
     * 数据缓冲区
     */
    private readonly WeakMap $dataBuffers;

    /**
     * 目标连接映射
     */
    private readonly WeakMap $targetConnections;

    /**
     * 日志记录器
     */
    private readonly LoggerInterface $logger;

    /**
     * 构造函数
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->dataBuffers = new WeakMap();
        $this->targetConnections = new WeakMap();
        $this->logger = $logger;
    }

    /**
     * 获取数据缓冲
     */
    public function getDataBuffer(TcpConnection $connection): string
    {
        if (!$this->dataBuffers->offsetExists($connection)) {
            $this->dataBuffers->offsetSet($connection, '');
            $remoteAddress = $connection->getRemoteAddress();
            $this->logger->debug(sprintf('为连接 %s 初始化缓冲区', $remoteAddress));
        }

        return $this->dataBuffers->offsetGet($connection);
    }

    /**
     * 设置数据缓冲
     */
    public function setDataBuffer(TcpConnection $connection, string $data): void
    {
        $this->dataBuffers->offsetSet($connection, $data);
    }

    /**
     * 追加数据到缓冲区
     */
    public function appendToBuffer(TcpConnection $connection, string $data): string
    {
        $buffer = $this->getDataBuffer($connection);
        $buffer .= $data;
        $this->dataBuffers->offsetSet($connection, $buffer);
        return $buffer;
    }

    /**
     * 检查是否有目标连接
     */
    public function hasTargetConnection(TcpConnection $connection): bool
    {
        return $this->targetConnections->offsetExists($connection);
    }

    /**
     * 获取目标连接
     */
    public function getTargetConnection(TcpConnection $connection): ?AsyncTcpConnection
    {
        return $this->targetConnections->offsetExists($connection) ? $this->targetConnections->offsetGet($connection) : null;
    }

    /**
     * 创建目标连接
     */
    public function createTargetConnection(
        TcpConnection $connection,
        RemoteTarget $target
    ): AsyncTcpConnection {
        $remoteAddress = $connection->getRemoteAddress();
        $targetHost = $target->getHost();
        $targetPort = $target->getPort();

        // 创建到目标服务器的连接
        $targetConnection = new AsyncTcpConnection($target->getAddress());
        $this->targetConnections->offsetSet($connection, $targetConnection);

        // 设置目标连接的回调
        $targetConnection->onConnect = function($targetConnection) use ($remoteAddress, $targetHost, $targetPort) {
            $this->logger->info(sprintf(
                '目标连接已建立: %s -> %s:%d',
                $remoteAddress,
                $targetHost,
                $targetPort
            ));
        };

        $targetConnection->onError = function($targetConnection, $code, $msg) use ($remoteAddress, $targetHost, $targetPort) {
            $this->logger->error(sprintf(
                '目标连接错误: %s -> %s:%d [%d] %s',
                $remoteAddress,
                $targetHost,
                $targetPort,
                $code,
                $msg
            ));
            $targetConnection->close();
        };

        $targetConnection->onClose = function($targetConnection) use ($remoteAddress, $targetHost, $targetPort) {
            $this->logger->info(sprintf(
                '目标连接关闭: %s -> %s:%d',
                $remoteAddress,
                $targetHost,
                $targetPort
            ));
        };

        return $targetConnection;
    }

    /**
     * 清理连接数据
     */
    public function cleanupConnection(TcpConnection $connection): void
    {
        $remoteAddress = $connection->getRemoteAddress();

        // 关闭对应的目标连接
        if ($this->targetConnections->offsetExists($connection)) {
            $targetConnection = $this->targetConnections->offsetGet($connection);
            $targetConnection->close();
            $this->logger->info(sprintf(
                '连接关闭: %s, 目标连接也已关闭',
                $remoteAddress
            ));
        }

        // 清理数据
        if ($this->dataBuffers->offsetExists($connection)) {
            $this->dataBuffers->offsetUnset($connection);
        }

        if ($this->targetConnections->offsetExists($connection)) {
            $this->targetConnections->offsetUnset($connection);
        }
    }
}
