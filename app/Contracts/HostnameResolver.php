<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface HostnameResolver
{
    public function resolveClientIp(Request $request): string;

    public function resolveHostname(string $ipAddress): string;
}
