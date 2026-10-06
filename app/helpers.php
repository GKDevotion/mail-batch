<?php

if (! function_exists('campaign_sum')) {
    /** Sum selected keys of a collection/array of counts. */
    function campaign_sum($counts, array $keys): int
    {
        $total = 0;
        foreach ($keys as $key) {
            $total += (int) ($counts[$key] ?? 0);
        }

        return $total;
    }
}
