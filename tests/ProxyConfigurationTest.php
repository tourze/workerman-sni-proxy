<?php

namespace Tourze\Workerman\SNIProxy\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\Workerman\SNIProxy\ProxyConfiguration;
use Tourze\Workerman\SNIProxy\RemoteTarget;

class ProxyConfigurationTest extends TestCase
{
    /**
     * 测试默认构造函数
     */
    public function testDefaultConstructor()
    {
        $config = new ProxyConfiguration();
        
        $this->assertEquals('0.0.0.0', $config->getBindHost());
        $this->assertEquals(443, $config->getBindPort());
        $this->assertEquals('tcp://0.0.0.0:443', $config->getBindAddress());
        $this->assertEmpty($config->getRemoteHosts());
    }
    
    /**
     * 测试带自定义参数的构造函数
     */
    public function testCustomConstructor()
    {
        $config = new ProxyConfiguration('127.0.0.1', 8443, [
            'example.com:443',
            'test.com:8080'
        ]);
        
        $this->assertEquals('127.0.0.1', $config->getBindHost());
        $this->assertEquals(8443, $config->getBindPort());
        $this->assertEquals('tcp://127.0.0.1:8443', $config->getBindAddress());
        
        $remoteHosts = $config->getRemoteHosts();
        $this->assertCount(2, $remoteHosts);
        $this->assertContains('example.com:443', $remoteHosts);
        $this->assertContains('test.com:8080', $remoteHosts);
    }
    
    /**
     * 测试远程主机列表为空时，允许所有主机
     */
    public function testGetAllowedTarget_withEmptyRemoteHosts()
    {
        $config = new ProxyConfiguration();
        
        $target = $config->getAllowedTarget('any-host.com');
        $this->assertInstanceOf(RemoteTarget::class, $target);
        $this->assertEquals('any-host.com', $target->getHost());
        $this->assertEquals(443, $target->getPort());
    }
    
    /**
     * 测试获取允许的远程主机-主机在允许列表中
     */
    public function testGetAllowedTarget_withHostInAllowedList()
    {
        $config = new ProxyConfiguration('0.0.0.0', 443, [
            'example.com:443',
            'test.com:8080'
        ]);
        
        $target = $config->getAllowedTarget('example.com');
        $this->assertInstanceOf(RemoteTarget::class, $target);
        $this->assertEquals('example.com', $target->getHost());
        $this->assertEquals(443, $target->getPort());
        
        $target = $config->getAllowedTarget('test.com');
        $this->assertInstanceOf(RemoteTarget::class, $target);
        $this->assertEquals('test.com', $target->getHost());
        $this->assertEquals(8080, $target->getPort());
    }
    
    /**
     * 测试获取允许的远程主机-主机不在允许列表中
     */
    public function testGetAllowedTarget_withHostNotInAllowedList()
    {
        $config = new ProxyConfiguration('0.0.0.0', 443, [
            'example.com:443',
            'test.com:8080'
        ]);
        
        $target = $config->getAllowedTarget('unknown-host.com');
        $this->assertNull($target);
    }
    
    /**
     * 测试获取远程主机列表
     */
    public function testGetRemoteHosts()
    {
        $config = new ProxyConfiguration('0.0.0.0', 443, [
            'example.com:443',
            'test.com:8080',
            'another.com'  // 未指定端口，应使用默认端口443
        ]);
        
        $remoteHosts = $config->getRemoteHosts();
        $this->assertCount(3, $remoteHosts);
        $this->assertContains('example.com:443', $remoteHosts);
        $this->assertContains('test.com:8080', $remoteHosts);
        $this->assertContains('another.com:443', $remoteHosts);
    }
} 