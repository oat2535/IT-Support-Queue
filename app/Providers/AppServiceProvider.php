<?php

namespace App\Providers;

use App\Contracts\BmsJobSource;
use App\Contracts\HostnameResolver;
use App\Services\NetbiosHostnameResolver;
use App\Services\SqlsrvBmsJobSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HostnameResolver::class, NetbiosHostnameResolver::class);
        $this->app->bind(BmsJobSource::class, SqlsrvBmsJobSource::class);
    }

    public function boot(): void
    {
        //
    }
}
