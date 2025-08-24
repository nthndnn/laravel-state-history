<?php

namespace NathanDunn\StateHistory\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use NathanDunn\StateHistory\StateHistoryServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            StateHistoryServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'StateHistory' => \NathanDunn\StateHistory\Facades\StateHistory::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('state-history.use_current_columns', true);
        $app['config']->set('state-history.prefix', 'current_');
        $app['config']->set('state-history.log_fallback_warnings', false);
    }
}
