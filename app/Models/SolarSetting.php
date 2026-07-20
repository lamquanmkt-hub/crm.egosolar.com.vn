<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolarSetting extends Model
{
    protected $fillable = [
        'key',
        'label',
        'group',
        'value',
        'type',
        'description',
    ];

    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value ?? $default;
    }

    public static function setValue(string $key, $value): void
    {
        static::where('key', $key)->update([
            'value' => $value,
        ]);
    }
}
