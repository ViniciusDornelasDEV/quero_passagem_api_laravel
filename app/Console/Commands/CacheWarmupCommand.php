<?php

namespace App\Console\Commands;

use App\Services\CompanyService;
use App\Services\StopService;
use Illuminate\Console\Command;

class CacheWarmupCommand extends Command
{
    protected $signature = 'cache:warmup';

    protected $description = 'Warm up external integration cache (stops and companies)';

    public function __construct(
        private readonly StopService $stopService,
        private readonly CompanyService $companyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Warming up full stops cache (with details)...');
        $stops = $this->stopService->warmupStopsCache($this);
        $this->info('Stops cached: '.count($stops));

        $this->info('Fetching all companies...');
        $companies = $this->companyService->getCompanies();

        $companyIds = collect($companies)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($companyIds === []) {
            $this->warn('No companies found to warm up.');
            $this->info('Cache warmup finished.');

            return self::SUCCESS;
        }

        $total = count($companyIds);
        $this->info("Warming up companies cache ({$total} total)...");

        foreach ($companyIds as $id) {
            try {
                $this->companyService->getCompany($id);
                $this->line("Company cached: {$id}");
            } catch (\Throwable) {
                $this->warn("Failed to cache company [{$id}]");
            }
        }

        $this->info('Cache warmup finished.');

        return self::SUCCESS;
    }
}
