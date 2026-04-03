<?php

namespace App\Services;

use App\Integrations\QueroPassagemClient;
use Illuminate\Support\Facades\Cache;

class CompanyService
{
    public function __construct(
        private readonly QueroPassagemClient $client,
    ) {
    }

    public function getCompany(string $id): array
    {
        return Cache::remember("company_{$id}", now()->addDay(), function () use ($id): array {
            return $this->client->getCompany($id);
        });
    }

    public function getCompanies(): array
    {
        $payload = $this->client->getCompanies();
        $companies = $payload['companies'] ?? $payload;

        return collect($companies)
            ->filter(fn (mixed $company): bool => is_array($company))
            ->map(fn (array $company): array => $company)
            ->values()
            ->all();
    }
}
