<?php

namespace Tests\Unit;

use App\Services\NetbiosHostnameResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class NetbiosHostnameResolverTest extends TestCase
{
    public function test_parses_workstation_name_from_nbstat_packet(): void
    {
        $header = str_repeat("\x00", 12);
        $questionEcho = str_repeat("\x00", 38);
        $answerPrefix = "\x00\x21\x00\x01".str_repeat("\x00", 6);
        $numNames = "\x02";
        // Entry 1: Group name (0x8000 flag) -> skipped
        $entry1 = str_pad('WORKGROUP', 15, ' ')."\x00"."\x80\x00";
        // Entry 2: Unique workstation name (0x00 suffix, 0x0400 flag) -> selected
        $entry2 = str_pad('IT-SUPPORT-01', 15, ' ')."\x00"."\x04\x00";

        $packet = $header.$questionEcho.$answerPrefix.$numNames.$entry1.$entry2;

        $this->assertSame('IT-SUPPORT-01', NetbiosHostnameResolver::parseNetbiosNbstatResponse($packet));
    }

    public function test_resolves_client_ip_from_request_and_falls_back_on_invalid_ip(): void
    {
        $resolver = new NetbiosHostnameResolver;
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.10.20.30',
        ]);

        $this->assertSame('10.10.20.30', $resolver->resolveClientIp($request));

        $spoofed = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => 'not-an-ip<script>',
        ]);

        $this->assertSame('127.0.0.1', $resolver->resolveClientIp($spoofed));
    }

    public function test_caches_fallback_hostname_when_dns_and_netbios_fail(): void
    {
        $resolver = new class extends NetbiosHostnameResolver
        {
            public int $attempts = 0;

            protected function lookupReverseDns(string $ipAddress): ?string
            {
                $this->attempts++;

                return null;
            }

            protected function lookupNetbiosName(string $ipAddress): ?string
            {
                return null;
            }
        };

        $first = $resolver->resolveHostname('10.99.99.99');
        $second = $resolver->resolveHostname('10.99.99.99');

        $this->assertSame('VPN/Unknown (10.99.99.99)', $first);
        $this->assertSame('VPN/Unknown (10.99.99.99)', $second);
        $this->assertSame(1, $resolver->attempts);
    }
}
