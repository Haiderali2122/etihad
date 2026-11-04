<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

/**
 * The "Other…" choice on a dropdown: the person types a name, which is saved to that list so it shows
 * next time. A name already in the list (ignoring case and extra spaces) is reused, so no duplicates appear.
 */
class OtherOption
{
    /** The value the "Other…" choice submits. */
    public const Value = '__other__';

    /**
     * Swap "Other…" for the matching or newly created list entry before the request is validated.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $attributes  extra columns for a new entry
     * @param  bool  $byId  true when the field holds the entry's id, false when it holds its name
     */
    public static function resolve(FormRequest $request, string $field, string $model, array $attributes = [], bool $byId = true): void
    {
        if ($request->input($field) !== self::Value) {
            return;
        }

        $name = self::clean($request->input("{$field}_other"));
        if ($name === '') {
            // Left empty: let the field's own "required" rule explain what is missing.
            $request->merge([$field => null]);

            return;
        }

        $entry = self::findOrCreate($model, $name, $attributes);
        $request->merge([$field => $byId ? $entry->getKey() : $entry->getAttribute('name')]);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $attributes
     */
    public static function findOrCreate(string $model, string $name, array $attributes = []): Model
    {
        $name = self::clean($name);
        $entry = $model::query()->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first();

        if (! $entry) {
            return $model::query()->create(['name' => $name] + $attributes);
        }

        // A name that was switched off comes back into the list when someone picks it again.
        if (Schema::hasColumn($entry->getTable(), 'is_active') && ! $entry->getAttribute('is_active')) {
            $entry->forceFill(['is_active' => true])->save();
        }

        return $entry;
    }

    private static function clean(mixed $value): string
    {
        return is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', $value)) : '';
    }
}
