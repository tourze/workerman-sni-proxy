<?php

namespace Tourze\Workerman\SNIProxy\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\Workerman\SNIProxy\SniParser;

class SniParserTest extends TestCase
{
    private SniParser $parser;
    
    protected function setUp(): void
    {
        $this->parser = new SniParser();
    }
    
    /**
     * 测试缓冲区太小的情况
     */
    public function testParseSNI_withTooSmallBuffer()
    {
        $buffer = str_repeat('x', 41); // 小于所需的最小长度42字节
        $result = $this->parser->parseSNI($buffer);
        
        $this->assertNull($result);
    }
    
    /**
     * 测试非SSL/TLS握手类型的情况
     */
    public function testParseSNI_withNonTLSHandshake()
    {
        $buffer = chr(0x17) . str_repeat('x', 100); // 0x17 不是TLS握手类型
        $result = $this->parser->parseSNI($buffer);
        
        $this->assertNull($result);
    }
    
    /**
     * 测试会话ID长度导致溢出的情况
     */
    public function testParseSNI_withSessionIdOverflow()
    {
        // 创建一个缓冲区，使会话ID长度使指针超出缓冲区范围
        $buffer = chr(0x16) . str_repeat('x', 42);
        $buffer[43] = chr(255); // 设置会话ID长度为255，将导致指针超出缓冲区
        
        $result = $this->parser->parseSNI($buffer);
        
        $this->assertNull($result);
    }
    
    /**
     * 测试密码套件长度导致溢出的情况
     */
    public function testParseSNI_withCipherSuitesOverflow()
    {
        // 创建一个基本的TLS握手缓冲区，会话ID长度为0
        $buffer = chr(0x16) . str_repeat('x', 42);
        $buffer[43] = chr(0); // 会话ID长度为0
        
        // 设置密码套件长度使指针超出缓冲区
        $buffer[44] = chr(0xFF); // 密码套件长度高字节
        $buffer[45] = chr(0xFF); // 密码套件长度低字节
        
        $result = $this->parser->parseSNI($buffer);
        
        $this->assertNull($result);
    }
    
    /**
     * 测试压缩方法长度导致溢出的情况
     */
    public function testParseSNI_withCompressionMethodsOverflow()
    {
        // 创建一个基本的TLS握手缓冲区，会话ID长度为0，密码套件长度为0
        $buffer = chr(0x16) . str_repeat('x', 42);
        $buffer[43] = chr(0); // 会话ID长度为0
        $buffer[44] = chr(0); // 密码套件长度高字节
        $buffer[45] = chr(0); // 密码套件长度低字节
        
        // 设置一个过大的压缩方法长度
        $buffer[46] = chr(255);
        
        $result = $this->parser->parseSNI($buffer);
        
        $this->assertNull($result);
    }
    
    /**
     * 测试扩展长度导致溢出的情况
     */
    public function testParseSNI_withExtensionsOverflow()
    {
        // 创建一个基本的TLS握手缓冲区，会话ID、密码套件、压缩方法长度都为0
        $buffer = chr(0x16) . str_repeat('x', 42);
        $buffer[43] = chr(0); // 会话ID长度为0
        $buffer[44] = chr(0); // 密码套件长度高字节
        $buffer[45] = chr(0); // 密码套件长度低字节
        $buffer[46] = chr(0); // 压缩方法长度为0
        
        // 扩展部分
        $buffer[47] = chr(0xFF); // 扩展总长度高字节
        $buffer[48] = chr(0xFF); // 扩展总长度低字节
        
        $result = $this->parser->parseSNI($buffer);
        
        $this->assertNull($result);
    }
    
    /**
     * 测试包含SNI扩展但不完整的情况
     */
    public function testParseSNI_withIncompleteSNIExtension()
    {
        // 创建基本的TLS握手缓冲区直到扩展部分
        $buffer = chr(0x16) . str_repeat('x', 42);
        $buffer[43] = chr(0); // 会话ID长度为0
        $buffer[44] = chr(0); // 密码套件长度高字节
        $buffer[45] = chr(0); // 密码套件长度低字节
        $buffer[46] = chr(0); // 压缩方法长度为0
        
        // 添加扩展头
        $buffer[47] = chr(0); // 扩展总长度高字节
        $buffer[48] = chr(10); // 扩展总长度低字节（仅为示例）
        
        // 添加SNI扩展类型(0)，但不添加完整数据
        $buffer[49] = chr(0); // 扩展类型高字节
        $buffer[50] = chr(0); // 扩展类型低字节
        $buffer[51] = chr(0); // 扩展长度高字节
        $buffer[52] = chr(5); // 扩展长度低字节
        
        $result = $this->parser->parseSNI($buffer);
        
        $this->assertNull($result);
    }
} 