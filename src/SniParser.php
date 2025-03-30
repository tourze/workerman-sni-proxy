<?php

namespace Tourze\Workerman\SNIProxy;

class SniParser
{
    /**
     * 解析SNI主机名
     */
    public function parseSNI(string $buffer): ?string
    {
        if (strlen($buffer) < 42) {
            return null;
        }

        $type = ord($buffer[0]);
        if ($type != 0x16) { // 不是SSL/TLS握手
            return null;
        }

        $pos = 43;
        $sessionIdLength = ord($buffer[43]);
        $pos += $sessionIdLength + 1;

        if ($pos + 2 > strlen($buffer)) {
            return null;
        }

        $cipherSuitesLength = (ord($buffer[$pos]) << 8) + ord($buffer[$pos + 1]);
        $pos += $cipherSuitesLength + 2;

        if ($pos + 1 > strlen($buffer)) {
            return null;
        }

        $compressionMethodsLength = ord($buffer[$pos]);
        $pos += $compressionMethodsLength + 1;

        if ($pos + 2 > strlen($buffer)) {
            return null;
        }

        $extensionsLength = (ord($buffer[$pos]) << 8) + ord($buffer[$pos + 1]);
        $pos += 2;
        $endPos = $pos + $extensionsLength;

        while ($pos + 4 <= strlen($buffer)) {
            $extensionType = (ord($buffer[$pos]) << 8) + ord($buffer[$pos + 1]);
            $extensionLength = (ord($buffer[$pos + 2]) << 8) + ord($buffer[$pos + 3]);
            $pos += 4;

            if ($extensionType === 0) { // SNI扩展
                if ($pos + 2 > strlen($buffer)) {
                    return null;
                }

                $sniListLength = (ord($buffer[$pos]) << 8) + ord($buffer[$pos + 1]);
                $pos += 2;

                if ($pos + 3 > strlen($buffer)) {
                    return null;
                }

                $sniType = ord($buffer[$pos]);
                $sniLength = (ord($buffer[$pos + 1]) << 8) + ord($buffer[$pos + 2]);
                $pos += 3;

                if ($pos + $sniLength > strlen($buffer)) {
                    return null;
                }

                return substr($buffer, $pos, $sniLength);
            }

            $pos += $extensionLength;
        }

        return null;
    }
}
