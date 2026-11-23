<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Las pruebas corren contra la base local de PostgreSQL (el esquema base sale
     * de bd_sql.txt, no solo de migraciones) y cada una se deshace con una
     * transacción. RefreshDatabase/DatabaseMigrations borrarían esa base: se
     * bloquean. Usa DatabaseTransactions o Tests\Concerns\UsaBaseLocal.
     */
    protected function beforeRefreshingDatabase(): void
    {
        throw new RuntimeException('RefreshDatabase está prohibido: borraría la base local. Usa DatabaseTransactions o UsaBaseLocal.');
    }
}
