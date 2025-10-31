<?php

declare(strict_types=1);

namespace Tourze\Workerman\SNIProxy\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\Workerman\SNIProxy\RemoteTarget;

/**
 * @internal
 */
#[CoversClass(RemoteTarget::class)]
final class RemoteTargetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * 测试基本构造函数和获取器
     */
    public function testConstructorAndGetters(): void
    {
        $target = new RemoteTarget('example.com', 8443);

        $this->assertEquals('example.com', $target->getHost());
        $this->assertEquals(8443, $target->getPort());
        $this->assertEquals('tcp://example.com:8443', $target->getAddress());
    }

    /**
     * 测试默认端口值
     */
    public function testDefaultPort(): void
    {
        $target = new RemoteTarget('example.com');

        $this->assertEquals('example.com', $target->getHost());
        $this->assertEquals(443, $target->getPort());
        $this->assertEquals('tcp://example.com:443', $target->getAddress());
    }

    /**
     * 测试从字符串创建实例-带端口号
     */
    public function testFromStringWithPort(): void
    {
        $target = RemoteTarget::fromString('example.com:8443');

        $this->assertEquals('example.com', $target->getHost());
        $this->assertEquals(8443, $target->getPort());
        $this->assertEquals('tcp://example.com:8443', $target->getAddress());
    }

    /**
     * 测试从字符串创建实例-不带端口号
     */
    public function testFromStringWithoutPort(): void
    {
        $target = RemoteTarget::fromString('example.com');

        $this->assertEquals('example.com', $target->getHost());
        $this->assertEquals(443, $target->getPort());
        $this->assertEquals('tcp://example.com:443', $target->getAddress());
    }

    /**
     * 测试从多段字符串创建实例
     */
    public function testFromStringWithMultipleParts(): void
    {
        $target = RemoteTarget::fromString('sub.example.com:8443');

        $this->assertEquals('sub.example.com', $target->getHost());
        $this->assertEquals(8443, $target->getPort());
        $this->assertEquals('tcp://sub.example.com:8443', $target->getAddress());
    }

    /**
     * 测试从带多个冒号的字符串创建实例（IPv6地址场景）
     */
    public function testFromStringWithIPv6(): void
    {
        // 测试标准IPv6格式 [host]:port
        $target1 = RemoteTarget::fromString('[2001:db8::1]:8443');
        $this->assertEquals('2001:db8::1', $target1->getHost());
        $this->assertEquals(8443, $target1->getPort());

        // 测试IPv6地址不带端口
        $target2 = RemoteTarget::fromString('[2001:db8::1]');
        $this->assertEquals('2001:db8::1', $target2->getHost());
        $this->assertEquals(443, $target2->getPort());

        // 测试无括号的IPv6地址
        $target3 = RemoteTarget::fromString('2001:db8::1');
        $this->assertEquals('2001:db8::1', $target3->getHost());
        $this->assertEquals(443, $target3->getPort());

        // 测试无效的IPv6格式（缺少闭合括号）
        $target4 = RemoteTarget::fromString('[2001:db8::1');
        $this->assertEquals('[2001:db8::1', $target4->getHost());
        $this->assertEquals(443, $target4->getPort());
    }
}
