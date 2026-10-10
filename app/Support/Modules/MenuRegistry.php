<?php

namespace App\Support\Modules;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/** Sidebar entries contributed by modules. */
class MenuRegistry
{
    /** @var array<int,array<string,mixed>> */
    private array $items = [];

    /** @param array<int,array<string,mixed>> $items */
    public function add(string $moduleKey, array $items): void
    {
        foreach ($items as $item) {
            $group = $item['group'] ?? 'main';

            $this->items[] = $item + [
                'module' => $moduleKey,
                'group' => $group,
                'section' => $group === 'admin' ? 'Administration' : 'Modules',
                'icon' => 'circle',
                'permission' => null,
                'order' => 50,
                'match' => $item['route'],
            ] + ['group' => $group];
        }
    }

    /**
     * Allowed entries of one group as a flat list, each with "url" and "active".
     *
     * @return array<int,array<string,mixed>>
     */
    public function items(?User $user, string $group = 'main'): array
    {
        $list = [];

        foreach ($this->items as $item) {
            if ($item['group'] !== $group || ! Route::has($item['route'])) {
                continue;
            }

            if ($item['permission'] && (! $user || ! Gate::forUser($user)->allows($item['permission']))) {
                continue;
            }

            $list[] = $item + [
                'url' => route($item['route']),
                'active' => request()->routeIs($item['match']),
            ];
        }

        usort($list, fn ($a, $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

        return $list;
    }

    /** Same as items() but grouped by sidebar heading. */
    public function sections(?User $user, string $group = 'main'): array
    {
        $sections = [];

        foreach ($this->items($user, $group) as $item) {
            $sections[$item['section']][] = $item;
        }

        return $sections;
    }
}
