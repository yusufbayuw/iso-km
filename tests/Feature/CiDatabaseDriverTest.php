<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CiDatabaseDriverTest extends TestCase
{
    public function test_database_driver_matches_ci_job(): void
    {
        $connection = DB::getDefaultConnection();

        if (getenv('CI_MYSQL') === '1') {
            // Prevent a green "MySQL" job silently running SQLite because of phpunit.xml.
            $this->assertSame('mysql', $connection);
            $this->assertSame('mysql', DB::connection()->getDriverName());
            $this->assertNotNull(DB::selectOne('SELECT VERSION() AS version'));
        } else {
            $this->assertNotEmpty($connection);
        }
    }
}
