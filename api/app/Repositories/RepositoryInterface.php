<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    public function all(array $with = []): Collection;

    public function paginate(int $perPage = 20, array $with = []): LengthAwarePaginator;

    public function find(int $id, array $with = []): ?Model;

    public function findOrFail(int $id, array $with = []): Model;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}
