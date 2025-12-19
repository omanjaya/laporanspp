<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'address',
        'phone',
        'email',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function rekonData()
    {
        return $this->hasMany(RekonData::class, 'sekolah', 'name');
    }

    /**
     * Get active schools
     */
    public static function getActive()
    {
        return static::where('is_active', true)->orderBy('display_name')->get();
    }

    /**
     * Get school by name
     */
    public static function getByName($name)
    {
        return static::where('name', $name)->where('is_active', true)->first();
    }
}
