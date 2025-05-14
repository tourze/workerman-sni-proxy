<?php

namespace Tourze\Workerman\SNIProxy\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\Workerman\SNIProxy\RemoteTarget;

class RemoteTargetTest extends TestCase
{
    /**
     * 测试基本构造函数和获取器
     */
    public function testConstructorAndGetters()
    {
        $target = new RemoteTarget('example.com', 8443);
        
        $this->assertEquals('example.com', $target->getHost());
        $this->assertEquals(8443, $target->getPort());
        $this->assertEquals('tcp://example.com:8443', $target->getAddress());
    }
    
    /**
     * 测试默认端口值
     */
    public function testDefaultPort()
    {
        $target = new RemoteTarget('example.com');
        
        $this->assertEquals('example.com', $target->getHost());
        $this->assertEquals(443, $target->getPort());
        $this->assertEquals('tcp://example.com:443', $target->getAddress());
    }
    
    /**
     * 测试从字符串创建实例-带端口号
     */
    public function testFromStringWithPort()
    {
        $target = RemoteTarget::fromString('example.com:8443');
        
        $this->assertEquals('example.com', $target->getHost());
        $this->assertEquals(8443, $target->getPort());
        $this->assertEquals('tcp://example.com:8443', $target->getAddress());
    }
    
    /**
     * 测试从字符串创建实例-不带端口号
     */
    public function testFromStringWithoutPort()
    {
        $target = RemoteTarget::fromString('example.com');
        
        $this->assertEquals('example.com', $target->getHost());
        $this->assertEquals(443, $target->getPort());
        $this->assertEquals('tcp://example.com:443', $target->getAddress());
    }
    
    /**
     * 测试从多段字符串创建实例
     */
    public function testFromStringWithMultipleParts()
    {
        $target = RemoteTarget::fromString('sub.example.com:8443');
        
        $this->assertEquals('sub.example.com', $target->getHost());
        $this->assertEquals(8443, $target->getPort());
        $this->assertEquals('tcp://sub.example.com:8443', $target->getAddress());
    }
    
    /**
     * 测试从带多个冒号的字符串创建实例（IPv6地址场景）
     */
    public function testFromStringWithIPv6()
    {
        // 当前实现对IPv6地址处理的限制
        $target = RemoteTarget::fromString('[2001:db8::1]:8443');
        
        // 当前实现会将整个字符串作为主机名，端口保持默认443
        $this->assertEquals('[2001:db8::1]:8443', $target->getHost());
        $this->assertEquals(443, $target->getPort());
    }
} 