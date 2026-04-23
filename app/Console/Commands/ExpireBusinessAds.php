<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdminClub\BusinessAd;

class ExpireBusinessAds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-business-ads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        BusinessAd::where('status_id', 5) // publicados
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'status_id' => 6 // expirado
            ]);

        return 0;
    }
}
