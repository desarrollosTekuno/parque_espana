<?php

namespace Database\Seeders;

use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\AmenitySchedule;
use App\Models\Administrator\Club;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Run the database seeds
     */
    public function run(): void
    {
        //
        $amenitySeeder = [
            [
                'code_club' => 'PE1',
                'amenities' => [
                    [
                        'name' => 'Canchas de pádel',
                        'description' => 'Canchas de pádel',
                        'reservation_type' => 'hourly',
                        'resources' => [
                            [
                                'name' => 'Cancha 1',
                                'capacity' => 1,
                                'slot_duration_minutes' => 90,
                            ],
                            [
                                'name' => 'Cancha 2',
                                'capacity' => 1,
                                'slot_duration_minutes' => 90,
                            ],
                            [
                                'name' => 'Cancha 3',
                                'capacity' => 1,
                                'slot_duration_minutes' => 60,
                            ],
                            [
                                'name' => 'Cancha 4',
                                'capacity' => 1,
                                'slot_duration_minutes' => 60,
                            ],
                            [
                                'name' => 'Cancha 5',
                                'capacity' => 1,
                                'slot_duration_minutes' => 90,
                            ]
                        ],
                        'schedules' => [
                            [
                                'day_of_week' => 2,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 3,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 4,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 5,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 6,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 0,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ]
                        ]

                    ],
                    // jardines
                    [
                        'name' => 'Jardines',
                        'description' => 'Jardines para eventos sociales',
                        'reservation_type' => 'daily',
                        'resources' => [
                            [
                                'name' => 'Jardín 1',
                                'capacity' => null,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Jardín 2',
                                'capacity' => null,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Jardín 3',
                                'capacity' => null,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Jardín 4',
                                'capacity' => null,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Asador 1',
                                'capacity' => 1,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Asador 2',
                                'capacity' => 1,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Asador 3',
                                'capacity' => 1,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Asador 4',
                                'capacity' => 1,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Asador 5',
                                'capacity' => 1,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Asador 6',
                                'capacity' => 1,
                                'slot_duration_minutes' => null,
                            ],
                            [
                                'name' => 'Asador 7',
                                'capacity' => 1,
                                'slot_duration_minutes' => null,
                            ],
                        ],
                        'schedules' => [
                            [
                                'day_of_week' => 2,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 3,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 4,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 5,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 6,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 0,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ]
                        ]
                    ],
                    // canchas de tenis
                    [
                        'name' => 'Canchas de tenis',
                        'description' => 'Canchas de tenis',
                        'reservation_type' => 'hourly',
                        'resources' => [
                            [
                                'name' => 'Cancha 1',
                                'capacity' => 1,
                                'slot_duration_minutes' => 60,
                            ],
                            [
                                'name' => 'Cancha 2',
                                'capacity' => 1,
                                'slot_duration_minutes' => 60,
                            ],
                            [
                                'name' => 'Cancha 3',
                                'capacity' => 1,
                                'slot_duration_minutes' => 90,
                            ],
                            [
                                'name' => 'Cancha 4',
                                'capacity' => 1,
                                'slot_duration_minutes' => 90,
                            ],

                        ],
                        'schedules' => [
                            [
                                'day_of_week' => 2,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 3,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 4,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 5,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 6,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 0,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ]
                        ]
                    ],
                    // canchas de frontón
                    [
                        'name' => 'Canchas de frontón',
                        'description' => 'Canchas de frontón',
                        'reservation_type' => 'hourly',
                        'resources' => [
                            [
                                'name' => 'Cancha 1',
                                'capacity' => 1,
                                'slot_duration_minutes' => 60,
                            ],
                            [
                                'name' => 'Cancha 2',
                                'capacity' => 1,
                                'slot_duration_minutes' => 60,
                            ],
                        ],
                        'schedules' => [
                            [
                                'day_of_week' => 2,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 3,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 4,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 5,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 6,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 0,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ]
                        ]
                    ],
                    // alberca
                    [
                        'name' => 'Alberca',
                        'description' => 'Alberca para uso recreativo',
                        'reservation_type' => 'capacity',
                        'resources' => [
                            [
                                'name' => 'Alberca',
                                'capacity' => 5,
                                'slot_duration_minutes' => 60,
                            ],
                        ],
                        'schedules' => [
                            [
                                'day_of_week' => 2,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 3,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 4,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 5,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 6,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ],
                            [
                                'day_of_week' => 0,
                                'open_time' => '07:00:00',
                                'close_time' => '19:00:00',
                            ]
                        ]
                    ]

                ],
            ],
            [
                'code_club' => 'PE2',
                'amenities' => [
                    
                ]
            ]
        ];

        foreach ($amenitySeeder as $clubAmenities) {
            $club = Club::where('code', $clubAmenities['code_club'])->first();
            if ($club) {
                foreach ($clubAmenities['amenities'] as $amenityData) {
                    $amenity = Amenity::updateOrCreate([
                        'name' => $amenityData['name'],
                        'club_id' => $club->id,
                    ], [
                        'description' => $amenityData['description'],
                        'reservation_type' => $amenityData['reservation_type'],
                    ]);

                    // Create resources
                    foreach ($amenityData['resources'] as $resourceData) {
                        AmenityResource::updateOrCreate([
                            'name' => $resourceData['name'],
                            'amenity_id' => $amenity->id,
                        ], [
                            'capacity' => $resourceData['capacity'],
                            'slot_duration_minutes' => $resourceData['slot_duration_minutes'],
                        ]);
                    }

                    // Create schedules
                    foreach ($amenityData['schedules'] as $scheduleData) {
                        AmenitySchedule::updateOrCreate([
                            'amenity_id' => $amenity->id,
                            'day_of_week' => $scheduleData['day_of_week'],
                        ], [
                            'open_time' => $scheduleData['open_time'],
                            'close_time' => $scheduleData['close_time'],
                        ]);
                    }
                }
            }
        }
    }
}
