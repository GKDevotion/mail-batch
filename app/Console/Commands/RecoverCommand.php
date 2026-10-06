<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Console\Command;

/** Optional safety net (only if you run the scheduler): release stuck claims and finish campaigns whose batch ended. */
class RecoverCommand extends Command
{
    protected $signature = 'mailbatch:recover';

    protected $description = 'Release stuck queue claims and finish campaigns whose batch ended without a final status';

    public function handle(CampaignService $campaigns): int
    {
        $ids = Campaign::query()->where('status', 'processing')->pluck('id');

        foreach ($ids as $id) {
            $campaigns->finishBatchIfIdle((int) $id);
        }

        $this->info('Checked '.$ids->count().' processing campaign(s).');

        return self::SUCCESS;
    }
}
