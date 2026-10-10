<?php

namespace App\Modules\Access\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /** Role used for users that have none assigned. */
    public const DEFAULT_KEY = 'campaign_manager';

    protected $guarded = [];

    /** @var array<string,?int> */
    private static array $ids = [];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    /** @return array<int,string> */
    public function permissionNames(): array
    {
        return $this->permissions()->pluck('permission')->all();
    }

    public static function idForKey(string $key): ?int
    {
        if (! array_key_exists($key, self::$ids)) {
            $id = static::query()->where('key', $key)->value('id');
            self::$ids[$key] = $id ? (int) $id : null;
        }

        return self::$ids[$key];
    }

    public static function flushCache(): void
    {
        self::$ids = [];
    }
}
