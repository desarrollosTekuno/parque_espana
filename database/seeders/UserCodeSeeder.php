<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserCodeSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereNull('code')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $words = preg_split('/\s+/', trim(Str::ascii($user->name)));
            $baseCode = '';

            foreach ($words as $word) {
                if ($word !== '') {
                    $baseCode .= strtoupper(substr($word, 0, 1));
                }
            }

            if ($baseCode === '') {
                $baseCode = 'USR';
            }

            $baseCode = substr($baseCode, 0, 15);
            $code = $baseCode;
            $suffix = 2;

            while (User::where('code', $code)->exists()) {
                $code = substr($baseCode, 0, 15) . $suffix;
                $suffix++;
            }

            $user->code = $code;
            $user->save();
        }
    }
}
