<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gates para controle de acesso no menu sidebar
        // access-monitoring: supervisor + admin (monitor, supervisão, instâncias, usuários, empresas)
        Gate::define('access-monitoring', function ($user) {
            return in_array($user->role, ['admin', 'supervisor']);
        });

        // access-system: somente admin (logs, saúde do sistema)
        Gate::define('access-system', function ($user) {
            return $user->role === 'admin';
        });
    }
}
