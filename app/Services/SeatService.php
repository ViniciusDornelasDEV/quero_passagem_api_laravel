<?php

namespace App\Services;

use App\Integrations\QueroPassagemClient;

class SeatService
{
    public function __construct(
        private readonly QueroPassagemClient $client,
    ) {}

    public function getSeats(array $data): array
    {
        $payload = $this->client->getSeats($data);

        return $this->normalizeSeatPayload($payload);
    }

    private function normalizeSeatPayload(array $payload): array
    {
        if (! isset($payload[0]['seats']) || ! is_array($payload[0]['seats'])) {
            return [];
        }

        $seats = $payload[0]['seats'];

        $result = [];

        foreach ($seats as $seat) {
            if (! is_array($seat)) {
                continue;
            }

            foreach ($seat as $cell) {
                if (! is_array($cell)) {
                    continue;
                }

                $result[] = [
                    'seat_number' => data_get($cell, 'seat'),
                    'occupied' => (bool) data_get($cell, 'occupied', false),
                    'type' => data_get($cell, 'type'),
                    'x' => (int) data_get($cell, 'position.x', 0),
                    'y' => (int) data_get($cell, 'position.y', 0),
                ];
            }
        }

        return $this->sortSeats($result);
    }

    private function sortSeats(array $seats): array
    {
        return collect($seats)
            ->sortBy([
                ['x', 'asc'],
                ['y', 'asc'],
            ])
            ->values()
            ->all();
    }
}
