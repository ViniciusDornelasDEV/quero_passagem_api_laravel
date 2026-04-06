<?php

namespace Tests\Unit;

use App\Integrations\QueroPassagemClient;
use App\Services\SeatService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SeatServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_normalizes_matrix_seats_and_preserves_positions(): void
    {
        $payload = [
            [
                'seats' => [
                    [
                        [
                            'type' => 'seat',
                            'seat' => '1',
                            'occupied' => true,
                            'position' => ['x' => 0, 'y' => 0],
                        ],
                        [
                            'type' => 'seat',
                            'seat' => '2',
                            'occupied' => false,
                            'position' => ['x' => 1, 'y' => 0],
                        ],
                    ],
                    [
                        [
                            'type' => 'seat',
                            'seat' => '3',
                            'occupied' => false,
                            'position' => ['x' => 0, 'y' => 1],
                        ],
                    ],
                ],
            ],
        ];

        $client = Mockery::mock(QueroPassagemClient::class);
        $client->shouldReceive('getSeats')
            ->once()
            ->with(['travelId' => 't1'])
            ->andReturn($payload);

        $service = new SeatService($client);
        $result = $service->getSeats(['travelId' => 't1']);

        $this->assertCount(3, $result);

        $byNumber = collect($result)->keyBy('seat_number');

        $this->assertSame('1', $byNumber->get('1')['seat_number']);
        $this->assertTrue($byNumber->get('1')['occupied']);
        $this->assertSame('seat', $byNumber->get('1')['type']);
        $this->assertSame(0, $byNumber->get('1')['x']);
        $this->assertSame(0, $byNumber->get('1')['y']);

        $this->assertSame(1, $byNumber->get('2')['x']);
        $this->assertSame(0, $byNumber->get('2')['y']);

        $this->assertSame(0, $byNumber->get('3')['x']);
        $this->assertSame(1, $byNumber->get('3')['y']);
    }

    public function test_excludes_empty_or_reserved_space_cells(): void
    {
        $payload = [
            [
                'seats' => [
                    [
                        [
                            'type' => 'seat',
                            'seat' => 'A',
                            'occupied' => false,
                            'position' => ['x' => 0, 'y' => 0],
                        ],
                        [
                            'type' => 'emptyOrReservedSpace',
                            'seat' => null,
                            'occupied' => false,
                            'position' => ['x' => 1, 'y' => 0],
                        ],
                    ],
                ],
            ],
        ];

        $client = Mockery::mock(QueroPassagemClient::class);
        $client->shouldReceive('getSeats')->once()->andReturn($payload);

        $service = new SeatService($client);
        $result = $service->getSeats([]);

        $this->assertCount(1, $result);
        $this->assertSame('A', $result[0]['seat_number']);
        $this->assertSame('seat', $result[0]['type']);
    }

    public function test_sorts_seats_by_matrix_position_x_then_y(): void
    {
        $payload = [
            [
                'seats' => [
                    [
                        [
                            'type' => 'seat',
                            'seat' => '10',
                            'occupied' => false,
                            'position' => ['x' => 0, 'y' => 0],
                        ],
                    ],
                    [
                        [
                            'type' => 'seat',
                            'seat' => '02',
                            'occupied' => false,
                            'position' => ['x' => 0, 'y' => 1],
                        ],
                    ],
                    [
                        [
                            'type' => 'seat',
                            'seat' => '5',
                            'occupied' => false,
                            'position' => ['x' => 0, 'y' => 2],
                        ],
                    ],
                ],
            ],
        ];

        $client = Mockery::mock(QueroPassagemClient::class);
        $client->shouldReceive('getSeats')->once()->andReturn($payload);

        $service = new SeatService($client);
        $result = $service->getSeats([]);

        $this->assertSame(['10', '02', '5'], array_column($result, 'seat_number'));
    }
}
