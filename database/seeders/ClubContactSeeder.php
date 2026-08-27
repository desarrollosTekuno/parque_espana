<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClubContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $clubContact= array(
            'PE1' => array(
                "email" => null,
                "website"=> "https://pagina-parqueespana.atxiontech.com/parque-espana-1",
                "social_whatsapp" => "https://www.whatsapp.com/channel/0029VbAqplVBqbrB1fzv4r3P",
                // instagram
                "social_instagram" => "https://www.instagram.com/parqueespana_puebla",
                // facebook
                "social_facebook" => "https://www.facebook.com/parqueespanapuebla",
                // youtube
                "social_youtube" => "https://www.youtube.com/@parqueespana/videos",
                // phone
                "phone" => "2222431070",
                // twitter
                "social_twitter" => null,
                // threads
                "social_threads" => "https://www.threads.com/@parqueespana_puebla?xmt=AQG0M0EUKgaI8QxIa5KJ6BXAhitC13KFnlZrsTEBG_-cwkY",
            ),
            'PE2' => array(
                "email" => "fatane@parqueespana2.com.mx",
                "website"=> "https://pagina-parqueespana.atxiontech.com/parque-espana-2",
                "social_whatsapp" => null,
                // instagram
                "social_instagram" => "https://www.instagram.com/parqueespana2",
                // facebook
                "social_facebook" => "https://www.facebook.com/parque2",
                // youtube
                "social_youtube" => null,
                // phone
                "phone" => "2222841091",
                // twitter
                "social_twitter" => "https://x.com/parque2",
                // threads
                "social_threads" => "https://www.threads.com/@parqueespana_puebla?xmt=AQG0M0EUKgaI8QxIa5KJ6BXAhitC13KFnlZrsTEBG_-cwkY",
            )
        );

        // update database with club contact information
        foreach ($clubContact as $club => $contact) {
            Club::where('code', $club)->update($contact);
        }
    }
}
