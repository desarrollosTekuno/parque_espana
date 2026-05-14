<?php

namespace Database\Seeders;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $startDate = Carbon::now()->subDays(10);
        $endDate = Carbon::now()->addDays(10);

        for ($i = 0; $i < 200; $i++) {

            $date = Carbon::createFromTimestamp(
                rand($startDate->timestamp, $endDate->timestamp)
            );

            // horarios tipo: 6:00 a 22:00
            $hour = rand(6, 21);

            $start = $date->copy()->setTime($hour, 0);
            $end = $start->copy()->addHour();

            DB::table('reservations.reservations')->insert([
                'start_datetime' => $start,
                'end_datetime' => $end,
                'reservation_date' => $start->toDateString(),

                'club_id' => 1,
                'amenity_id' => 3,

                'amenity_resource_id' => rand(0, 1) ? 10 : 11,

                'user_id' => rand(0, 1) ? 3 : 4,

                'reservation_status_id' => rand(1, 4),

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
