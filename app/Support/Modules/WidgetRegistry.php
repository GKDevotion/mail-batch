<?php

namespace App\Support\Modules;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Dashboard widgets contributed by modules. */
class WidgetRegistry
{
    /** @var array<int,array<string,mixed>> */
    private array $widgets = [];

    /** @param array<int,array<string,mixed>> $widgets */
    public function add(string $moduleKey, array $widgets): void
    {
        foreach ($widgets as $widget) {
            $this->widgets[] = $widget + ['module' => $moduleKey, 'permission' => null, 'col' => 'col-12', 'order' => 50];
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function for(?User $user): array
    {
        $list = array_filter($this->widgets, function (array $w) use ($user) {
            if (! view()->exists($w['view'])) {
                return false;
            }

            return ! $w['permission'] || ($user && Gate::forUser($user)->allows($w['permission']));
        });

        usort($list, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_values($list);
    }
}
