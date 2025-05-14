<?php

namespace Tourze\Workerman\SNIProxy\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\Workerman\SNIProxy\SniProxyWorker;

class SniProxyWorkerTest extends TestCase
{
    private LoggerInterface $logger;
    
    protected function setUp(): void
    {
        // 创建日志记录器的模拟对象
        $this->logger = $this->createMock(LoggerInterface::class);
        
        // 因为SniProxyWorker继承自Worker，我们可能需要准备一些额外的环境
        if (!defined('WORKERMAN_VERSION')) {
            define('WORKERMAN_VERSION', '5.1.0');
        }
    }
    
    /**
     * 测试SniProxyWorker的基本构造函数
     */
    public function testConstructor()
    {
        // 使用默认参数创建SniProxyWorker
        $worker = new SniProxyWorker();
        
        // 验证默认参数
        $this->assertEquals('tcp://0.0.0.0:443', $worker->getSocketName());
        
        // 使用自定义参数创建SniProxyWorker
        $worker = new SniProxyWorker('127.0.0.1', 8443, [], $this->logger);
        
        // 验证自定义参数
        $this->assertEquals('tcp://127.0.0.1:8443', $worker->getSocketName());
    }
    
    /**
     * 测试Worker启动回调
     */
    public function testOnWorkerStart()
    {
        // 预期记录启动日志
        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('SNI proxy worker started'));
        
        // 创建SniProxyWorker实例
        $worker = new SniProxyWorker('127.0.0.1', 8443, [], $this->logger);
        
        // 手动调用onWorkerStart回调
        $worker->onWorkerStart($worker);
    }
    
    /**
     * 测试Worker停止回调
     */
    public function testOnWorkerStop()
    {
        // 预期记录停止日志
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('SNI proxy worker stopped'));
        
        // 创建SniProxyWorker实例
        $worker = new SniProxyWorker('127.0.0.1', 8443, [], $this->logger);
        
        // 手动调用onWorkerStop回调
        $worker->onWorkerStop($worker);
    }
    
    /**
     * 测试远程主机配置
     */
    public function testRemoteHostsConfiguration()
    {
        $remoteHosts = [
            'example.com:443',
            'test.com:8080'
        ];
        
        // 使用 callback 来验证日志调用，这样可以检查多个调用
        $logMessages = [];
        $this->logger->method('info')
            ->will($this->returnCallback(function ($message) use (&$logMessages) {
                $logMessages[] = $message;
                return null;
            }));
        
        // 创建SniProxyWorker实例
        $worker = new SniProxyWorker('127.0.0.1', 8443, $remoteHosts, $this->logger);
        
        // 手动调用onWorkerStart回调
        $worker->onWorkerStart($worker);
        
        // 验证日志消息
        $this->assertGreaterThanOrEqual(1, count($logMessages), '至少应该有一条日志消息');
        
        // 检查是否有关于远程主机配置的日志
        $remoteHostsLogFound = false;
        foreach ($logMessages as $message) {
            if (strpos($message, '配置的远程主机') !== false) {
                $remoteHostsLogFound = true;
                break;
            }
        }
        
        $this->assertTrue($remoteHostsLogFound, '应该记录远程主机配置');
    }
    
    /**
     * 测试连接错误回调
     */
    public function testOnError()
    {
        // 创建连接模拟对象
        $connection = $this->getMockBuilder('Workerman\Connection\TcpConnection')
            ->disableOriginalConstructor()
            ->getMock();
        $connection->method('getRemoteAddress')->willReturn('192.168.1.1:12345');
        
        // 预期记录错误信息
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('连接错误'));
        
        // 创建SniProxyWorker实例
        $worker = new SniProxyWorker('127.0.0.1', 8443, [], $this->logger);
        
        // 手动调用onError回调
        $worker->onError($connection, 1001, 'Test error message');
    }
} 