<?php

namespace Tourze\Workerman\SNIProxy\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\Workerman\SNIProxy\ConnectionManager;
use Tourze\Workerman\SNIProxy\RemoteTarget;
use Workerman\Connection\TcpConnection;

class ConnectionManagerTest extends TestCase
{
    private LoggerInterface $logger;
    private ConnectionManager $manager;
    
    protected function setUp(): void
    {
        // 创建日志记录器的模拟对象
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->manager = new ConnectionManager($this->logger);
    }
    
    /**
     * 测试初始化数据缓冲区
     */
    public function testGetDataBuffer_initializes()
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);
        $connection->method('getRemoteAddress')->willReturn('127.0.0.1:12345');
        
        // 日志应该记录初始化操作
        $this->logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('初始化缓冲区'));
        
        // 首次获取应返回空字符串
        $buffer = $this->manager->getDataBuffer($connection);
        $this->assertEquals('', $buffer);
    }
    
    /**
     * 测试重复获取数据缓冲区
     */
    public function testGetDataBuffer_returnsSameInstance()
    {
        // 创建连接的模拟对象
        $connection = $this->createMock(TcpConnection::class);
        $connection->method('getRemoteAddress')->willReturn('127.0.0.1:12345');
        
        // 日志应该只记录一次初始化操作
        $this->logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('初始化缓冲区'));
        
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
    public function testSetDataBuffer()
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
    public function testAppendToBuffer()
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
    public function testHasTargetConnection()
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
    public function testGetTargetConnection()
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
} 