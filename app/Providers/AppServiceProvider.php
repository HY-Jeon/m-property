<?php

namespace App\Providers;

use App\Services\MolitApiService;
use Illuminate\Support\ServiceProvider;
use App\Services\SafeReportService;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MolitApiService::class, function () {
            return new MolitApiService((string) config('services.molit.service_key'));
        });

        $this->app->singleton(SafeReportService::class, function () {
            return new SafeReportService(
                dataGoKrKey: (string) config('services.safereport.data_go_kr_key'),
                jusoKey: (string) config('services.safereport.juso_key')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
