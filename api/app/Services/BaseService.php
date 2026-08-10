<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    /**
     * Run the given callback inside a database transaction.
     */
    protected function transaction(\Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
