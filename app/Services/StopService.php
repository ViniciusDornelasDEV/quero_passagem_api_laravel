<?php

namespace App\Services;

use App\Integrations\QueroPassagemClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StopService
{
    public function __construct(
        private readonly QueroPassagemClient $client,
    ) {}

    public function getStops(): array
    {
        if (! Cache::has('stops_full')) {
            throw new RuntimeException('Stops cache not warmed.');
        }

        $stops = Cache::get('stops_full');

        return is_array($stops) ? $stops : [];
    }

    public function getStopDetail(string $id): array
    {
        return $this->client->getStop($id);
    }

    public function warmupStopsCache(?Command $command = null): array
    {
        $stops = $this->extractStops($this->client->getStops());
        $enrichedStops = $this->enrichStops($stops, $command);
        $normalizedStops = $this->normalizeStops($enrichedStops);

        Cache::put('stops_full', $normalizedStops, now()->addDay());

        return $normalizedStops;
    }

    public function validateStop(string $id): array
    {
        foreach ($this->getStops() as $stop) {
            if ((string) ($stop['id'] ?? '') === $id) {
                return $stop;
            }
            if (! empty($stop['substops']) && is_array($stop['substops'])) {
                foreach ($stop['substops'] as $sub) {
                    if ((string) ($sub['id'] ?? '') === $id) {
                        return $sub;
                    }
                }
            }
        }

        throw ValidationException::withMessages([
            'stop' => 'Rodoviária não encontrada.',
        ]);

    }

    public function expandStopIds(string $id): array
    {
        $stop = collect($this->getStops())
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $id);

        if (! is_array($stop)) {
            return [$id];
        }

        if (($stop['type'] ?? '') !== 'city') {
            return [$id];
        }

        $substops = $stop['substops'] ?? [];
        if (! is_array($substops) || $substops === []) {
            return [$id];
        }

        $ids = [];

        foreach ($substops as $substop) {
            if (! is_array($substop)) {
                continue;
            }

            $substopId = (string) ($substop['id'] ?? '');
            if ($substopId === '') {
                continue;
            }

            $ids[] = $substopId;
        }

        return $ids !== [] ? array_values(array_unique($ids)) : [$id];
    }

    private function normalizeStops(array $payload): array
    {
        $allowedStates = config('queropassagem.allowed_states', []);

        $stops = $this->extractStops($payload);

        $normalized = [];

        foreach ($stops as $item) {
            if (! is_array($item)) {
                continue;
            }
            $normalized[] = $this->normalizeStop($item, $allowedStates);
        }

        return $normalized;
    }

    private function normalizeStop(array $item, array $allowedStates): array
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
            $substops[] = $this->normalizeSubstop($sub, $allowedStates);
        }

        $name = (string) ($item['name'] ?? '');
        $state = $this->resolveState($item, $name);

        return [
            'id' => (string) ($item['id'] ?? ''),
            'name' => $name,
            'url' => (string) ($item['url'] ?? ''),
            'type' => (string) ($item['type'] ?? ''),
            'state' => $state,
            'allowed' => $state !== null && in_array($state, $allowedStates, true),
            'substops' => $substops,
        ];
    }

    private function normalizeSubstop(array $sub, array $allowedStates): array
    {
        $name = (string) ($sub['name'] ?? '');
        $state = $this->resolveState($sub, $name);

        return [
            'id' => (string) ($sub['id'] ?? ''),
            'name' => $name,
            'url' => (string) ($sub['url'] ?? ''),
            'type' => (string) ($sub['type'] ?? 'station'),
            'state' => $state,
            'allowed' => $state !== null && in_array($state, $allowedStates, true),
        ];
    }

    private function enrichStops(array $stops, ?Command $command = null): array
    {
        $enriched = [];
        $detailById = [];
        $total = count($stops);
        $bar = null;

        if ($command !== null && $total > 0) {
            $command->line("Enriching {$total} stops with detail data...");
            $bar = $command->getOutput()->createProgressBar($total);
            $bar->start();
        }

        foreach ($stops as $index => $stop) {
            if (! is_array($stop)) {
                if ($bar !== null) {
                    $bar->advance();
                }

                continue;
            }

            $stopId = (string) ($stop['id'] ?? '');
            if ($command !== null && $bar === null) {
                $current = $index + 1;
                $command->line("Processing stop {$current}/{$total}: {$stopId}");
            }
            $detail = $stopId !== '' ? $this->getDetailForId($stopId, $detailById) : [];
            $mergedStop = array_merge($stop, $detail);

            $rawSubstops = $mergedStop['substops'] ?? [];
            if (! is_array($rawSubstops)) {
                $rawSubstops = [];
            }

            $mergedSubstops = [];
            foreach ($rawSubstops as $substop) {
                if (! is_array($substop)) {
                    continue;
                }

                $substopId = (string) ($substop['id'] ?? '');
                if ($command !== null && $substopId !== '') {
                    $command->line("  -> Substop {$substopId}");
                }
                $substopDetail = $substopId !== '' ? $this->getDetailForId($substopId, $detailById) : [];
                $mergedSubstops[] = array_merge($substop, $substopDetail);
            }

            $mergedStop['substops'] = $mergedSubstops;
            $enriched[] = $mergedStop;

            if ($bar !== null) {
                $bar->advance();
            }
        }

        if ($bar !== null) {
            $bar->finish();
            $command?->newLine();
        }

        return $enriched;
    }

    private function getDetailForId(string $id, array &$detailById): array
    {
        if (array_key_exists($id, $detailById)) {
            return $detailById[$id];
        }

        try {
            $detailById[$id] = $this->extractStop($this->getStopDetail($id));
        } catch (\Throwable) {
            $detailById[$id] = [];
        }

        return $detailById[$id];
    }

    private function extractStops(array $payload): array
    {
        if (isset($payload['stops']) && is_array($payload['stops'])) {
            return $payload['stops'];
        }

        return $payload;
    }

    private function extractStop(array $payload): array
    {
        if (isset($payload['stop']) && is_array($payload['stop'])) {
            return $payload['stop'];
        }

        return $payload;
    }

    private function resolveState(array $item, string $name): ?string
    {
        $state = strtoupper(trim((string) ($item['state'] ?? '')));
        if ($state !== '' && preg_match('/^[A-Z]{2}$/', $state) === 1) {
            return $state;
        }

        return $this->extractState($name);
    }

    private function extractState(string $name): ?string
    {
        if (preg_match('/,\s([A-Z]{2})\b/', $name, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
