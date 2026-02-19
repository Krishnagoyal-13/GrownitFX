<?php

declare(strict_types=1);

namespace App\MT5\API\AUTH;

final class Hash
{
    public static function passHashRaw(string $password): string
    {
        $passwordUtf16le = mb_convert_encoding($password, 'UTF-16LE', 'UTF-8');
        $md5PasswordRaw = md5($passwordUtf16le, true);

        return md5($md5PasswordRaw . 'WebAPI', true);
    }

    public static function managerAnswer(string $passHashRaw, string $srvRandHex): string
    {
        $srvRandBin = hex2bin($srvRandHex);
        if ($srvRandBin === false) {
            throw new \InvalidArgumentException('Invalid srv_rand hex string.');
        }

        return md5($passHashRaw . $srvRandBin);
    }
}
