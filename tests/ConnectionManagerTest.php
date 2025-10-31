<?php

declare(strict_types=1);

namespace Tourze\Workerman\SNIProxy\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\Workerman\SNIProxy\ConnectionManager;
use Tourze\Workerman\SNIProxy\RemoteTarget;
use Workerman\Connection\TcpConnection;

/**
 * @internal
 */
#[CoversClass(ConnectionManager::class)]
final class ConnectionManagerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    private ConnectionManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建日志记录器的模拟对象
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->manager = new ConnectionManager($this->logger);
    }

    /**
     * 测试初始化数据缓冲区
     */
    public function testGetDataBufferInitializes(): void
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);
        $connection->method('getRemoteAddress')->willReturn('127.0.0.1:12345');

        // 日志应该记录初始化操作
        $this->logger->expects($this->once())
            ->method('debug')
            ->with(self::callback(function ($message): bool {
                self::assertIsString($message);
                self::assertStringContainsString('初始化缓冲区', $message, '日志消息应包含"初始化缓冲区"');

                return true;
            }))
        ;

        // 首次获取应返回空字符串
        $buffer = $this->manager->getDataBuffer($connection);
        $this->assertEquals('', $buffer);
    }

    /**
     * 测试重复获取数据缓冲区
     */
    public function testGetDataBufferReturnsSameInstance(): void
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);
        $connection->method('getRemoteAddress')->willReturn('127.0.0.1:12345');

        // 日志应该只记录一次初始化操作
        $this->logger->expects($this->once())
            ->method('debug')
            ->with(self::callback(function ($message): bool {
                self::assertIsString($message);
                self::assertStringContainsString('初始化缓冲区', $message, '日志消息应包含"初始化缓冲区"');

                return true;
            }))
        ;

        // 首次获取应返回空字符串
        $buffer1 = $this->manager->getDataBuffer($connection);
        $this->assertEquals('', $buffer1);

        // 设置一些数据
        $this->manager->setDataBuffer($connection, 'test-data');

        // 再次获取应返回之前设置的数据
        $buffer2 = $this->manager->getDataBuffer($connection);
        $this->assertEquals('test-data', $buffer2);
    }

    /**
     * 测试设置数据缓冲区
     */
    public function testSetDataBuffer(): void
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);
        $connection->method('getRemoteAddress')->willReturn('127.0.0.1:12345');

        // 设置数据
        $this->manager->setDataBuffer($connection, 'test-data');

        // 获取应返回设置的数据
        $buffer = $this->manager->getDataBuffer($connection);
        $this->assertEquals('test-data', $buffer);
    }

    /**
     * 测试追加数据到缓冲区
     */
    public function testAppendToBuffer(): void
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);
        $connection->method('getRemoteAddress')->willReturn('127.0.0.1:12345');

        // 设置初始数据
        $this->manager->setDataBuffer($connection, 'initial-');

        // 追加数据
        $result = $this->manager->appendToBuffer($connection, 'appended');

        // 结果应该是合并后的数据
        $this->assertEquals('initial-appended', $result);

        // 获取缓冲区应返回合并后的数据
        $buffer = $this->manager->getDataBuffer($connection);
        $this->assertEquals('initial-appended', $buffer);
    }

    /**
     * 测试检查是否有目标连接
     */
    public function testHasTargetConnection(): void
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);

        // 初始应该返回false
        $this->assertFalse($this->manager->hasTargetConnection($connection));

        // 设置目标连接
        $target = new RemoteTarget('example.com', 443);
        $this->manager->createTargetConnection($connection, $target);

        // 现在应该返回true
        $this->assertTrue($this->manager->hasTargetConnection($connection));
    }

    /**
     * 测试获取目标连接
     */
    public function testGetTargetConnection(): void
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);

        // 初始应该返回null
        $this->assertNull($this->manager->getTargetConnection($connection));

        // 设置目标连接
        $target = new RemoteTarget('example.com', 443);
        $targetConnection = $this->manager->createTargetConnection($connection, $target);

        // 获取的应该是同一个目标连接
        $this->assertSame($targetConnection, $this->manager->getTargetConnection($connection));
    }

    /**
     * 测试创建目标连接
     */
    public function testCreateTargetConnection(): void
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);
        $connection->method('getRemoteAddress')->willReturn('127.0.0.1:12345');

        // 预期记录连接建立日志
        $this->logger->expects($this->once())
            ->method('info')
            ->with(self::callback(function ($message): bool {
                self::assertIsString($message);
                self::assertStringContainsString('目标连接已建立', $message, '日志消息应包含"目标连接已建立"');

                return true;
            }))
        ;

        // 创建目标
        $target = new RemoteTarget('example.com', 443);

        // 创建目标连接
        $targetConnection = $this->manager->createTargetConnection($connection, $target);

        // 验证目标连接已被正确创建和存储
        $this->assertInstanceOf('Workerman\Connection\AsyncTcpConnection', $targetConnection);
        $this->assertTrue($this->manager->hasTargetConnection($connection));
        $this->assertSame($targetConnection, $this->manager->getTargetConnection($connection));

        // 模拟连接成功回调以触发日志记录
        $onConnect = $targetConnection->onConnect;
        if (is_callable($onConnect)) {
            $onConnect($targetConnection);
        }
    }

    /**
     * 测试清理连接数据
     */
    public function testCleanupConnection(): void
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);
        $connection->method('getRemoteAddress')->willReturn('127.0.0.1:12345');

        // 设置一些数据和目标连接
        $this->manager->setDataBuffer($connection, 'test-data');
        $target = new RemoteTarget('example.com', 443);
        $targetConnection = $this->manager->createTargetConnection($connection, $target);

        // 预期记录清理日志（因为有两次日志调用：一次是createTargetConnection，一次是cleanupConnection）
        $this->logger->expects($this->atLeastOnce())
            ->method('info')
        ;

        // 验证数据和连接都存在
        $this->assertEquals('test-data', $this->manager->getDataBuffer($connection));
        $this->assertTrue($this->manager->hasTargetConnection($connection));

        // 清理连接
        $this->manager->cleanupConnection($connection);

        // 验证数据已被清理
        $this->assertEquals('', $this->manager->getDataBuffer($connection));
        $this->assertFalse($this->manager->hasTargetConnection($connection));
        $this->assertNull($this->manager->getTargetConnection($connection));
    }
}
