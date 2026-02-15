<?php

namespace Yfsns\SmsTencent\Providers;

use Yfsns\SmsTencent\Channels\TencentChannel;
use App\Modules\Sms\Channels\Registry\SmsChannelRegistryInterface;
use Illuminate\Support\ServiceProvider;

class SmsTencentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend(SmsChannelRegistryInterface::class, function ($registry) {
            $registry->registerChannel('tencent', TencentChannel::class);
            return $registry;
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/sms.php',
            'sms'
        );

        $this->registerPublishing();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../Config/sms.php' => config_path('sms.php'),
            ], 'yfsns-sms-tencent-config');
        }
    }
}
