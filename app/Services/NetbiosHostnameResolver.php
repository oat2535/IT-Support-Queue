<?php

namespace App\Services;

use App\Contracts\HostnameResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class NetbiosHostnameResolver implements HostnameResolver
{
    private const CACHE_TTL_SECONDS = 300;

    private const NETBIOS_PORT = 137;

    private const NETBIOS_TIMEOUT_SECONDS = 2;

    public function resolveClientIp(Request $request): string
    {
        $ip = (string) ($request->ip() ?: '127.0.0.1');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    public function resolveHostname(string $ipAddress): string
    {
        if ($ipAddress === '') {
            return 'VPN/Unknown ()';
        }

        return Cache::remember("hostname_{$ipAddress}", self::CACHE_TTL_SECONDS, function () use ($ipAddress): string {
            $dnsHost = $this->lookupReverseDns($ipAddress);
            if ($dnsHost !== null) {
                return $dnsHost;
            }

            $netbiosHost = $this->lookupNetbiosName($ipAddress);
            if ($netbiosHost !== null) {
                return $netbiosHost;
            }

            return "VPN/Unknown ({$ipAddress})";
        });
    }

    protected function lookupReverseDns(string $ipAddress): ?string
    {
        if (! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return null;
        }

        try {
            $resolved = @gethostbyaddr($ipAddress);
            if (is_string($resolved) && $resolved !== '' && $resolved !== $ipAddress) {
                return $resolved;
            }
        } catch (Throwable) {
            // Fall through to NetBIOS query.
        }

        return null;
    }

    protected function lookupNetbiosName(string $ipAddress): ?string
    {
        if (! filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $ipAddress === '127.0.0.1') {
            return null;
        }

        try {
            $socket = @stream_socket_client(
                "udp://{$ipAddress}:".self::NETBIOS_PORT,
                $errno,
                $errstr,
                self::NETBIOS_TIMEOUT_SECONDS
            );

            if ($socket === false) {
                return null;
            }

            stream_set_timeout($socket, self::NETBIOS_TIMEOUT_SECONDS);

            $packet = "\xA2\x48"
                ."\x00\x00"
                ."\x00\x01"
                ."\x00\x00\x00\x00\x00\x00"
                ."\x20CKAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA\x00"
                ."\x00\x21"
                ."\x00\x01";

            @fwrite($socket, $packet);
            $response = @fread($socket, 1024);
            @fclose($socket);

            if (! is_string($response) || strlen($response) < 57) {
                return null;
            }

            return self::parseNetbiosNbstatResponse($response);
        } catch (Throwable) {
            return null;
        }
    }

    public static function parseNetbiosNbstatResponse(string $response): ?string
    {
        $markerPos = strpos($response, "\x00\x21\x00\x01", 12);
        if ($markerPos === false) {
            return null;
        }

        $numNamesOffset = $markerPos + 10;
        if (! isset($response[$numNamesOffset])) {
            return null;
        }

        $numNames = ord($response[$numNamesOffset]);
        $entryOffset = $numNamesOffset + 1;

        for ($i = 0; $i < $numNames; $i++) {
            $offset = $entryOffset + ($i * 18);
            if ($offset + 18 > strlen($response)) {
                break;
            }

            $rawName = substr($response, $offset, 15);
            $suffix = ord($response[$offset + 15]);
            $flags = unpack('n', substr($response, $offset + 16, 2))[1] ?? 0;
            $isGroup = ($flags & 0x8000) !== 0;

            if (! $isGroup && $suffix === 0x00) {
                $trimmed = trim($rawName);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return null;
    }
}
