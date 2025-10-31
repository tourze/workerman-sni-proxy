<?php

declare(strict_types=1);

namespace Tourze\Workerman\SNIProxy;

class SniParser
{
    /**
     * 解析SNI主机名
     */
    public function parseSNI(string $buffer): ?string
    {
        if (!$this->isValidTlsHandshake($buffer)) {
            return null;
        }

        $pos = $this->skipHandshakeHeader($buffer);
        if (null === $pos) {
            return null;
        }

        return $this->extractSniFromExtensions($buffer, $pos);
    }

    private function isValidTlsHandshake(string $buffer): bool
    {
        if (strlen($buffer) < 42) {
            return false;
        }

        $type = ord($buffer[0]);
        $version = (ord($buffer[1]) << 8) + ord($buffer[2]);
        $length = (ord($buffer[3]) << 8) + ord($buffer[4]);

        // 检查TLS类型：握手(0x16)
        if (0x16 !== $type) {
            return false;
        }

        // 检查TLS版本：支持1.0 (0x0301) 到 1.3 (0x0304)
        if ($version < 0x0301 || $version > 0x0304) {
            return false;
        }

        // 检查记录长度是否合理
        if ($length < 37 || $length > 16384) {
            return false;
        }

        // 检查握手类型是否为ClientHello(1)
        if (0x01 !== ord($buffer[5])) {
            return false;
        }

        return true;
    }

    private function skipHandshakeHeader(string $buffer): ?int
    {
        $bufferLength = strlen($buffer);
        $pos = 43;

        // 检查SessionID长度字段是否存在
        if ($pos >= $bufferLength) {
            return null;
        }

        $sessionIdLength = ord($buffer[$pos]);
        $pos += $sessionIdLength + 1;

        // 检查SessionID数据和CipherSuites长度字段
        if ($pos + 2 > $bufferLength) {
            return null;
        }

        $cipherSuitesLength = (ord($buffer[$pos]) << 8) + ord($buffer[$pos + 1]);

        // 检查CipherSuites长度是否合理（至少2字节，最多65534字节）
        if ($cipherSuitesLength < 2 || $cipherSuitesLength > 65534 || ($cipherSuitesLength % 2) !== 0) {
            return null;
        }

        $pos += $cipherSuitesLength + 2;

        // 检查CompressionMethods长度字段
        if ($pos + 1 > $bufferLength) {
            return null;
        }

        $compressionMethodsLength = ord($buffer[$pos]);

        // CompressionMethods长度已经是0-255范围内，无需额外检查

        $pos += $compressionMethodsLength + 1;

        // 检查Extensions长度字段
        if ($pos + 2 > $bufferLength) {
            return null;
        }

        $extensionsLength = (ord($buffer[$pos]) << 8) + ord($buffer[$pos + 1]);

        // 检查Extensions总长度是否超出缓冲区
        if ($pos + 2 + $extensionsLength > $bufferLength) {
            return null;
        }

        $pos += 2;

        return $pos;
    }

    private function extractSniFromExtensions(string $buffer, int $pos): ?string
    {
        $bufferLength = strlen($buffer);

        while ($pos + 4 <= $bufferLength) {
            $extensionType = (ord($buffer[$pos]) << 8) + ord($buffer[$pos + 1]);
            $extensionLength = (ord($buffer[$pos + 2]) << 8) + ord($buffer[$pos + 3]);
            $pos += 4;

            // 检查扩展长度是否超出缓冲区
            if ($pos + $extensionLength > $bufferLength) {
                return null;
            }

            if (0 === $extensionType) { // SNI扩展
                return $this->parseSniExtension($buffer, $pos, $extensionLength);
            }

            $pos += $extensionLength;
        }

        return null;
    }

    private function parseSniExtension(string $buffer, int $pos, int $extensionLength): ?string
    {
        $bufferLength = strlen($buffer);
        $endPos = $pos + $extensionLength;

        // 检查SNI列表长度字段
        if ($pos + 2 > $bufferLength || $pos + 2 > $endPos) {
            return null;
        }

        $sniListLength = (ord($buffer[$pos]) << 8) + ord($buffer[$pos + 1]);
        $pos += 2;

        // 检查SNI列表长度是否与扩展长度匹配
        if ($sniListLength !== $extensionLength - 2) {
            return null;
        }

        // 检查SNI项的类型和长度字段
        if ($pos + 3 > $bufferLength || $pos + 3 > $endPos) {
            return null;
        }

        $sniType = ord($buffer[$pos]);
        $sniLength = (ord($buffer[$pos + 1]) << 8) + ord($buffer[$pos + 2]);
        $pos += 3;

        // 检查SNI类型（必须是0，表示host_name）
        if (0 !== $sniType) {
            return null;
        }

        // 检查SNI长度是否合理
        if (0 === $sniLength || $sniLength > 255 || $pos + $sniLength > $bufferLength || $pos + $sniLength > $endPos) {
            return null;
        }

        $hostname = substr($buffer, $pos, $sniLength);

        // 验证主机名格式（基本ASCII检查）
        if (!$this->isValidHostname($hostname)) {
            return null;
        }

        return $hostname;
    }

    /**
     * 验证主机名格式
     */
    private function isValidHostname(string $hostname): bool
    {
        // 检查长度
        if (0 === strlen($hostname) || strlen($hostname) > 255) {
            return false;
        }

        // 检查是否包含只有可打印ASCII字符
        if (!ctype_print($hostname)) {
            return false;
        }

        // 检查是否包含空格等无效字符
        if (str_contains($hostname, ' ') || str_contains($hostname, '\t') || str_contains($hostname, '\n')) {
            return false;
        }

        return true;
    }
}
