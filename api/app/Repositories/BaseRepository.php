<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected Model $model) {}

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function all(array $with = []): Collection
    {
        return $this->query()->with($with)->get();
    }

    public function paginate(int $perPage = 20, array $with = []): LengthAwarePaginator
    {
        return $this->query()->with($with)->latest()->paginate($perPage);
    }

    public function find(int $id, array $with = []): ?Model
    {
        return $this->query()->with($with)->find($id);
    }

    public function findOrFail(int $id, array $with = []): Model
    {
        return $this->query()->with($with)->findOrFail($id);
    }

    public function create(array $attributes): Model
    {
        // refresh() so DB-level column defaults (e.g. `statut` on Personnel)
        // are reflected on the returned instance, not just in the database.
        return $this->model->newInstance()->create($attributes)->refresh();
    }

    public function createWithId(array $attributes, int $id): Model
    {
        if ($id < 1) {
            abort(422, 'Identifiant local invalide.');
        }

        if ($this->query()->whereKey($id)->exists()) {
            abort(409, "L'identifiant local {$id} est déjà utilisé sur le serveur.");
        }

        $model = $this->model->newInstance($attributes);
        $model->setAttribute($model->getKeyName(), $id);
        $model->save();

        return $model->refresh();
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
