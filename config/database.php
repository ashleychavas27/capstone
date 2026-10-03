<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        /*
         * Supabase Postgres — the application's only database.
         *
         * Supabase has three ways in, and the right one for Laravel is the
         * Session pooler (Supavisor, port 5432). Do NOT point this at the
         * Transaction pooler (port 6543): it does not support prepared
         * statements, which Eloquent relies on. See
         * database/supabase/README.md for the connection strings.
         *
         * search_path keeps the application tables out of Supabase's `public`
         * schema, which the Data API publishes over REST. The schema named here
         * must exist (database/supabase/01_schema.sql creates it).
         */
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'postgres'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SCHEMA', 'dental'),
            // 'require' fails the connection instead of silently falling back
            // to plaintext, which is what 'prefer' would do.
            'sslmode' => env('DB_SSLMODE', 'require'),
            // Supabase runs in UTC; the clinic works in Asia/Manila. Setting the
            // session timezone keeps CURRENT_TIMESTAMP defaults (failed_jobs,
            // useCurrent columns) aligned with the application clock.
            // Read via env() rather than config(): config files are evaluated
            // before the config repository is populated.
            'timezone' => env('DB_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
        ],

        /*
         * Tests only. The suite runs against an in-memory SQLite database so it
         * needs no network or Supabase credentials (see phpunit.xml).
         *
         * The fallback below is ':memory:' rather than a file path on purpose:
         * this application has no SQLite file on disk (Supabase Postgres is the
         * only database), so pointing DB_CONNECTION=sqlite at a file would
         * silently create an empty one. Tests always pass DB_DATABASE=:memory:
         * from phpunit.xml, so they are unaffected.
         *
         * To run the suite against real Postgres instead, set
         * DB_CONNECTION=pgsql in phpunit.xml and point DB_* at a Supabase
         * branch or a disposable database — never at production.
         */
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            // Fixed to :memory: on purpose — it must NOT read env('DB_DATABASE'),
            // because DB_DATABASE holds the Supabase *Postgres* database name.
            // Reading it here would point this connection at a SQLite file
            // literally named "postgres". This connection is test-only.
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        /*
         * Laravel MERGES the framework's default `connections` with this app's
         * (LoadConfiguration treats database.connections as a "mergeable
         * option"). Because these three keys were absent from this file, the
         * framework's stock mysql / mariadb / sqlsrv definitions were being
         * silently re-added — and they read DB_HOST / DB_USERNAME from .env, so
         * the Supabase pooler credentials were showing up under a *MySQL*
         * connection.
         *
         * Declaring them null overrides the merged defaults so they are
         * genuinely unavailable: this application runs on Supabase PostgreSQL
         * only, and any attempt to use one now fails loudly instead of quietly
         * connecting somewhere unintended.
         */
        'mysql' => null,
        'mariadb' => null,
        'sqlsrv' => null,

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
