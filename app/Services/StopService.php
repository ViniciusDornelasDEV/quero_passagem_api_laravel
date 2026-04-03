<?php

namespace App\Services;

use App\Integrations\QueroPassagemClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class StopService
{
    public function __construct(
        private readonly QueroPassagemClient $client,
    ) {
    }

    public function getStops(): array
    {
        return Cache::remember('stops', now()->addDay(), function (): array {
            return $this->normalizeStops($this->client->getStops());
        });
    }

    public function getAllowedStops(): array
    {
        return collect($this->getStops())->filter(function (array $stop): bool {
            $name = (string) ($stop['name'] ?? '');
            return str_contains($name, ', SP') || str_contains($name, ', PR');
        })->values()->all();
    }

    public function validateStop(string $id): array
    {
        $stop = collect($this->getStops())
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $id);

        if ($stop === null) {
            throw ValidationException::withMessages([
                'stop' => "Rodoviária não encontrada.",
            ]);
        }

        return $stop;
    }

    private function normalizeStops(array $payload): array
    {
        $stops = $payload;

        if (isset($payload['stops']) && is_array($payload['stops'])) {
            $stops = $payload['stops'];
        }

        return collect($stops)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => $item)
            ->values()
            ->all();
    }
}
