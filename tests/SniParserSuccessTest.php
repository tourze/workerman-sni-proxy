<?php

namespace Tourze\Workerman\SNIProxy\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\Workerman\SNIProxy\SniParser;

class SniParserSuccessTest extends TestCase
{
    private SniParser $parser;
    
    protected function setUp(): void
    {
        $this->parser = new SniParser();
    }
    
    /**
     * 测试成功解析SNI主机名
     */
    public function testParseSNI_success()
    {
        // 创建模拟的TLS ClientHello消息
        $buffer = $this->createTlsClientHelloWithSNI('example.com');
        
        $result = $this->parser->parseSNI($buffer);
        
        $this->assertEquals('example.com', $result);
    }
    
    /**
     * 测试解析不同长度的主机名
     */
    public function testParseSNI_withDifferentHostLengths()
    {
        // 测试短主机名
        $buffer = $this->createTlsClientHelloWithSNI('a.com');
        $result = $this->parser->parseSNI($buffer);
        $this->assertEquals('a.com', $result);
        
        // 测试中等长度主机名
        $buffer = $this->createTlsClientHelloWithSNI('subdomain.example.com');
        $result = $this->parser->parseSNI($buffer);
        $this->assertEquals('subdomain.example.com', $result);
        
        // 测试长主机名
        $longHost = 'very-long-subdomain-name.with-multiple.parts.example.com';
        $buffer = $this->createTlsClientHelloWithSNI($longHost);
        $result = $this->parser->parseSNI($buffer);
        $this->assertEquals($longHost, $result);
    }
    
    /**
     * 测试解析包含特殊字符的主机名
     */
    public function testParseSNI_withSpecialChars()
    {
        // 包含连字符和数字的主机名
        $host = 'test-123.example-host.com';
        $buffer = $this->createTlsClientHelloWithSNI($host);
        $result = $this->parser->parseSNI($buffer);
        $this->assertEquals($host, $result);
    }
    
    /**
     * 创建包含SNI扩展的TLS ClientHello消息
     *
     * 这个方法创建的缓冲区符合SniParser期望的格式，确保可以正确解析出SNI主机名
     *
     * @param string $hostname 要嵌入的主机名
     * @return string 构造的ClientHello消息
     */
    private function createTlsClientHelloWithSNI(string $hostname): string
    {
        // 构建一个基本的TLS握手消息, 确保它符合SniParser::parseSNI的结构要求
        $buffer = '';
        
        // 1. TLS记录层头部
        $buffer .= chr(0x16);  // 类型：Handshake (22)
        $buffer .= chr(0x03) . chr(0x01);  // 版本：TLS 1.0
        
        // 先用0填充长度，后面再填写
        $buffer .= chr(0x00) . chr(0x00);
        
        // 2. TLS握手消息头
        $buffer .= chr(0x01);  // 类型：ClientHello
        
        // 握手消息长度，先用0填充，后面再计算
        $buffer .= chr(0x00) . chr(0x00) . chr(0x00);
        
        // TLS版本
        $buffer .= chr(0x03) . chr(0x03);  // TLS 1.2
        
        // 随机数（32字节）
        $buffer .= str_repeat(chr(0), 32);
        
        // 会话ID长度
        $buffer .= chr(0x00);  // 长度为0
        
        // 加密套件长度和数据
        $buffer .= chr(0x00) . chr(0x02);  // 长度为2字节
        $buffer .= chr(0xc0) . chr(0x2f);  // 一个示例加密套件(TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256)
        
        // 压缩方法长度和数据
        $buffer .= chr(0x01);  // 长度为1字节
        $buffer .= chr(0x00);  // null压缩
        
        // 扩展长度
        // 计算扩展总长度：SNI扩展长度 = 主机名长度 + 5(SNI类型和长度) + 4(扩展类型和长度)
        $sniExtensionLength = strlen($hostname) + 9;
        $buffer .= chr(($sniExtensionLength >> 8) & 0xFF) . chr($sniExtensionLength & 0xFF);
        
        // SNI扩展
        $buffer .= chr(0x00) . chr(0x00);  // 扩展类型：SNI(0)
        $buffer .= chr(0x00) . chr(strlen($hostname) + 5);  // 扩展长度
        
        // SNI列表长度
        $buffer .= chr(0x00) . chr(strlen($hostname) + 3);  // SNI列表长度
        
        // SNI项类型
        $buffer .= chr(0x00);  // 主机名类型
        
        // 主机名长度
        $buffer .= chr(0x00) . chr(strlen($hostname));
        
        // 主机名
        $buffer .= $hostname;
        
        // 填充TLS记录层的长度
        $recordLength = strlen($buffer) - 5;
        $buffer[3] = chr(($recordLength >> 8) & 0xFF);
        $buffer[4] = chr($recordLength & 0xFF);
        
        // 填充握手消息的长度
        $handshakeLength = strlen($buffer) - 9;
        $buffer[6] = chr(($handshakeLength >> 16) & 0xFF);
        $buffer[7] = chr(($handshakeLength >> 8) & 0xFF);
        $buffer[8] = chr($handshakeLength & 0xFF);
        
        return $buffer;
    }
} 