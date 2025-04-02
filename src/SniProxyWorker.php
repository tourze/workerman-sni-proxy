<?php

namespace Tourze\Workerman\SNIProxy;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tourze\Workerman\ConnectionPipe\Container as ConnectionPipeContainer;
use Tourze\Workerman\ConnectionPipe\PipeFactory;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

class SniProxyWorker extends Worker
{
    /**
     * 配置对象
     */
    private readonly ProxyConfiguration $config;

    /**
     * 连接管理器
     */
    private readonly ConnectionManager $connectionManager;

    /**
     * SNI解析器
     */
    private readonly SniParser $sniParser;

    /**
     * 日志记录器
     */
    private readonly LoggerInterface $logger;

    /**
     * 构造函数
     *
     * @param string $bindHost 绑定主机地址
     * @param int $bindPort 绑定端口
     * @param array $remoteHosts 远程主机列表，格式如 ["www.baidu.com:443", "ip.sb:443"]
     * @param LoggerInterface|null $logger 日志记录器
     */
    public function __construct(
        string $bindHost = '0.0.0.0',
        int $bindPort = 443,
        array $remoteHosts = [],
        ?LoggerInterface $logger = null
    ) {
        $this->config = new ProxyConfiguration($bindHost, $bindPort, $remoteHosts);
        $this->logger = $logger ?? new NullLogger();
        $this->sniParser = new SniParser();
        $this->connectionManager = new ConnectionManager($this->logger);

        parent::__construct($this->config->getBindAddress());

        // 设置回调函数
        $this->onMessage = $this->onMessage(...);
        $this->onClose = $this->onClose(...);
        $this->onConnect = $this->onConnect(...);
        $this->onWorkerStart = $this->onWorkerStart(...);
        $this->onWorkerStop = $this->onWorkerStop(...);
        $this->onError = $this->onError(...);
    }

    /**
     * Worker启动回调
     */
    public function onWorkerStart(Worker $worker): void
    {
        // 设置ConnectionPipe的日志记录器
        ConnectionPipeContainer::setLogger($this->logger);

        $this->logger->info(sprintf(
            'SNI proxy worker started, listening on %s',
            $worker->getSocketName()
        ));

        $remoteHosts = $this->config->getRemoteHosts();
        if (!empty($remoteHosts)) {
            $this->logger->info(sprintf(
                '配置的远程主机: %s',
                implode(', ', $remoteHosts)
            ));
        }
    }

    /**
     * Worker停止回调
     */
    public function onWorkerStop(Worker $worker): void
    {
        $this->logger->info('SNI proxy worker stopped');
    }

    /**
     * 新连接回调
     */
    public function onConnect(TcpConnection $connection): void
    {
        $remoteAddress = $connection->getRemoteAddress();
        $this->logger->debug(sprintf('新连接来自 %s', $remoteAddress));
    }

    /**
     * 连接错误回调
     */
    public function onError(TcpConnection $connection, $code, $msg): void
    {
        $remoteAddress = $connection->getRemoteAddress();
        $this->logger->error(sprintf(
            '连接错误 %s: [%d] %s',
            $remoteAddress,
            $code,
            $msg
        ));
    }

    /**
     * 消息处理回调
     */
    public function onMessage(TcpConnection $connection, string $buffer): void
    {
        $remoteAddress = $connection->getRemoteAddress();

        // 检查是否已建立目标连接
        if ($this->connectionManager->hasTargetConnection($connection)) {
            $targetConnection = $this->connectionManager->getTargetConnection($connection);
            $targetConnection->send($buffer);
            $this->logger->debug(sprintf(
                '从 %s 转发 %d 字节到 %s',
                $remoteAddress,
                strlen($buffer),
                $targetConnection->getRemoteAddress()
            ));
            return;
        }

        // 追加数据到缓冲区
        $data = $this->connectionManager->appendToBuffer($connection, $buffer);

        // 检查是否有足够的数据来解析SNI
        if (strlen($data) < 5) {
            $this->logger->debug(sprintf(
                '等待更多数据从 %s, 当前缓冲区大小: %d 字节',
                $remoteAddress,
                strlen($data)
            ));
            return;
        }

        // 获取TLS记录层的长度
        $recordLength = (ord($data[3]) << 8) + ord($data[4]) + 5;
        if (strlen($data) < $recordLength) {
            $this->logger->debug(sprintf(
                '不完整的TLS记录从 %s, 需要 %d 字节, 已获得 %d 字节',
                $remoteAddress,
                $recordLength,
                strlen($data)
            ));
            return;
        }

        // 解析SNI主机名
        $sniHost = $this->sniParser->parseSNI($data);
        if (!$sniHost) {
            $this->logger->warning(sprintf('无法从 %s 解析SNI主机名', $remoteAddress));
            $connection->close();
            return;
        }

        $this->logger->info(sprintf(
            '从 %s 解析到SNI主机名: %s',
            $remoteAddress,
            $sniHost
        ));

        // 检查主机是否在允许列表中
        $target = $this->config->getAllowedTarget($sniHost);
        if ($target === null) {
            $this->logger->warning(sprintf(
                '主机 %s 不在允许列表中，连接将被关闭',
                $sniHost
            ));
            $connection->close();
            return;
        }

        // 创建目标连接
        $targetConnection = $this->connectionManager->createTargetConnection(
            $connection,
            $target
        );

        // 使用ConnectionPipe创建双向管道

        // 创建从客户端到目标服务器的管道
        $clientToTarget = PipeFactory::createTcpToTcp($connection, $targetConnection);
        $clientToTarget->pipe();

        // 创建从目标服务器到客户端的管道
        $targetToClient = PipeFactory::createTcpToTcp($targetConnection, $connection);
        $targetToClient->pipe();

        // 发送已缓冲的数据
        $clientToTarget->forward($data);
        $this->logger->debug(sprintf(
            '发送缓冲数据 (%d 字节) 到 %s:%d',
            strlen($data),
            $target->getHost(),
            $target->getPort()
        ));

        // 连接目标服务器
        $targetConnection->connect();
    }

    /**
     * 连接关闭回调
     */
    public function onClose(TcpConnection $connection): void
    {
        $this->connectionManager->cleanupConnection($connection);
    }
}
