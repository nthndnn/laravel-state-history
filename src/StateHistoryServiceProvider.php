<?php

namespace NathanDunn\StateHistory;

use Illuminate\Support\ServiceProvider;

class StateHistoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/state-history.php', 'state-history'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/state-history.php' => config_path('state-history.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../database/migrations/create_model_states_table.php.stub' => database_path('migrations/' . date('Y_m_d_His') . '_create_model_states_table.php'),
        ], 'migrations');
    }
}
