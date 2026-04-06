<?php

namespace App\Services;

use App\Integrations\QueroPassagemClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class StopService
{
    public function __construct(
        private readonly QueroPassagemClient $client,
    ) {}

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
                'stop' => 'Rodoviária não encontrada.',
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

        $normalized = [];

        foreach ($stops as $item) {
            if (! is_array($item)) {
                continue;
            }
            $normalized[] = $this->normalizeStop($item);
        }

        return $normalized;
    }

    private function normalizeStop(array $item): array
    {
        $rawSubstops = $item['substops'] ?? [];
        if (! is_array($rawSubstops)) {
            $rawSubstops = [];
        }

        $substops = [];

        foreach ($rawSubstops as $sub) {
            if (! is_array($sub)) {
                continue;
            }
            $substops[] = $this->normalizeSubstop($sub);
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'type' => (string) ($item['type'] ?? ''),
            'substops' => $substops,
        ];
    }

    private function normalizeSubstop(array $sub): array
    {
        return [
            'id' => (string) ($sub['id'] ?? ''),
            'name' => (string) ($sub['name'] ?? ''),
            'url' => (string) ($sub['url'] ?? ''),
            'type' => (string) ($sub['type'] ?? 'station'),
        ];
    }
}
