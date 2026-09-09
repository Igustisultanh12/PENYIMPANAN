<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting value by key with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting_{$key}", 300, function () use ($key, $default) {
            $record = static::where('key', $key)->first();
            return $record ? $record->value : $default;
        });
    }

    /**
     * Set/update a setting value and clear cache.
     */
    public static function set(string $key, mixed $value): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => is_null($value) ? null : (string) $value]
        );

        Cache::forget("setting_{$key}");
        Cache::forget('settings_all');

        return $setting;
    }

    /**
     * Get all settings as key-value array.
     */
    public static function getAll(): array
    {
        return Cache::remember('settings_all', 300, function () {
            return static::pluck('value', 'key')->toArray();
        });
    }
}
