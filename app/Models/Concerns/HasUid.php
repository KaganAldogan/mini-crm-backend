<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Domain models use UUID primary key column `uid`.
 * `$model->id` is an accessor alias for `uid` (API contract uses `id`).
 *
 * Never select/where/orderBy/pluck the column name `id` — it does not exist.
 * Use `uid`, omit the column list, or read `$model->id` / `$model->getKey()`.
 * For Rule::unique()->ignore(), pass the model (or getKey() + 'uid'), never ignore($model->id) alone.
 */
trait HasUid
{
    use HasUuids;

    public function initializeHasUid(): void
    {
        $this->primaryKey = 'uid';
        $this->keyType = 'string';
        $this->incrementing = false;
        $this->append('id');
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uid'];
    }

    /**
     * Keep $model->id working as an alias for the uid primary key.
     */
    public function getIdAttribute(): mixed
    {
        return $this->attributes['uid'] ?? null;
    }

    /**
     * Writes to the real PK column when code assigns $model->id = ...
     */
    public function setIdAttribute(mixed $value): void
    {
        $this->attributes['uid'] = $value;
    }

    /**
     * FK columns stay `{model}_id` even though the PK column is `uid`.
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)).'_id';
    }

    /**
     * @param  class-string  $related
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null): BelongsTo
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation).'_id';
        }

        return parent::belongsTo($related, $foreignKey, $ownerKey, $relation);
    }
}
