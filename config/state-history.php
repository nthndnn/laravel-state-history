<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Current State Columns Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration controls whether the package should use current_{field}
    | columns for better indexing and querying of state values.
    |
    */

    'use_current_columns' => env('STATE_HISTORY_USE_CURRENT_COLUMNS', true),

    /*
    |--------------------------------------------------------------------------
    | Current Column Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used for current state columns. For example, if you have a
    | 'state' field, the current column will be 'current_state'.
    |
    */

    'prefix' => env('STATE_HISTORY_CURRENT_PREFIX', 'current_'),

    /*
    |--------------------------------------------------------------------------
    | Log Fallback Warnings
    |--------------------------------------------------------------------------
    |
    | Whether to log warnings when current columns are not found and the
    | package falls back to the base state columns.
    |
    */

    'log_fallback_warnings' => env('STATE_HISTORY_LOG_FALLBACK_WARNINGS', true),

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | The model class to use for state history records. This should be a class
    | that extends the base ModelState model.
    |
    */

    'model' => env('STATE_HISTORY_MODEL', \NathanDunn\StateHistory\Models\ModelState::class),
];
