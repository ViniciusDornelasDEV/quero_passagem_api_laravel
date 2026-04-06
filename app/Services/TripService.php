<?php

namespace App\Services;

use App\Integrations\QueroPassagemClient;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TripService
{
    public function __construct(
        private readonly QueroPassagemClient $client,
        private readonly StopService $stopService,
        private readonly CompanyService $companyService,
    ) {}

    public function search(array $data): array
    {
        $from = (string) ($data['from'] ?? '');
        $to = (string) ($data['to'] ?? '');

        if ($from === $to && $from !== '') {
            throw ValidationException::withMessages([
                'to' => 'Destination must be different from origin.',
            ]);
        }

        $this->stopService->validateStop($from);
        $this->stopService->validateStop($to);

        $fromIds = $this->stopService->expandStopIds($from);
        $toIds = $this->stopService->expandStopIds($to);

        $trips = $this->searchTripsForStopCombinations($data, $fromIds, $toIds);

        $trips = $this->sortTrips($trips);
        $companyMap = $this->loadCompaniesForTrips($trips);
        $trips = $this->enrichTripsWithCompany($trips, $companyMap);

        return $trips->values()->all();
    }

    private function searchTripsForStopCombinations(array $data, array $fromIds, array $toIds): Collection
    {
        $results = collect();

        foreach ($fromIds as $fromId) {
            foreach ($toIds as $toId) {
                $payload = $this->client->search([
                    ...$data,
                    'from' => $fromId,
                    'to' => $toId,
                ]);

                $results = $results->merge($this->extractTrips($payload));
            }
        }

        return $results;
    }

    private function extractTrips(array $payload): Collection
    {
        $rawTrips = $payload['trips'] ?? $payload;

        return collect($rawTrips)
            ->filter(fn (mixed $trip): bool => is_array($trip))
            ->map(fn (array $trip): array => $trip)
            ->values();
    }

    private function loadCompaniesForTrips(Collection $trips): Collection
    {
        $company = $this->companyService->getCompany(2);
        return $trips
            ->pluck('company.id')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->mapWithKeys(fn (string $id): array => [$id => $this->companyService->getCompany($id)]);
    }

    private function sortTrips(Collection $trips): Collection
    {
        return $trips->sortBy('departure.time');
    }

    private function enrichTripsWithCompany(Collection $trips, Collection $companyMap): Collection
    {
        return $trips->map(function (array $trip) use ($companyMap): array {
            $companyId = (string) data_get($trip, 'company.id', '');
            $fetched = $companyMap->get($companyId, []);
            $source = $fetched !== [] ? $fetched : (array) data_get($trip, 'company', []);

            data_set($trip, 'company.logo', $this->resolveCompanyLogoUrl($source));

            return $trip;
        });
    }

    private function resolveCompanyLogoUrl(array $company): ?string
    {
        $svg = data_get($company, 'logo.svg');

        if (filled($svg) && is_string($svg)) {
            return $svg;
        }

        $jpg = data_get($company, 'logo.jpg');

        if (filled($jpg) && is_string($jpg)) {
            return $jpg;
        }

        $logo = data_get($company, 'logo');

        return is_string($logo) ? $logo : null;
    }
}
