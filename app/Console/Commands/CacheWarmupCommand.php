<?php

namespace App\Console\Commands;

use App\Services\CompanyService;
use App\Services\StopService;
use Illuminate\Console\Command;

class CacheWarmupCommand extends Command
{
    protected $signature = 'cache:warmup';

    protected $description = 'Warm up external integration cache (stops and selected companies)';

    public function __construct(
        private readonly StopService $stopService,
        private readonly CompanyService $companyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Warming up stops cache...');
        $stops = $this->stopService->getStops();
        $this->info('Stops cached: '.count($stops));

        $companyIds = $this->companyIdsToWarmup();

        if ($companyIds === []) {
            $this->info('No --companies provided. Fetching all company IDs from API...');
            $companyIds = $this->companyService->getCompanies();
            $companyIds = collect($companyIds)
                ->pluck('id')
                ->map(fn (mixed $id): string => trim((string) $id))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if ($companyIds === []) {
            $this->warn('No companies found to warm up.');
            $this->info('Cache warmup finished.');

            return self::SUCCESS;
        }

        $this->info('Warming up companies cache for '.count($companyIds).' companies...');

        foreach ($companyIds as $id) {
            try {
                $this->companyService->getCompany($id);
                $this->line("Company cached: {$id}");
            } catch (\Throwable $exception) {
                $this->warn("Failed to cache company [{$id}]: {$exception->getMessage()}");
            }
        }

        $this->info('Cache warmup finished.');

        return self::SUCCESS;
    }

    private function companyIdsToWarmup(): array
    {
        return collect($this->option('companies'))
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values();
            ->all();
    }
}
