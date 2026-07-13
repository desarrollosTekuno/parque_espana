<?php

namespace App\Services\Users;

use App\Models\User;

class UserService
{
    public function generateCode(string $name): string
    {
        $initials = $this->initialsFrom($name);

        $code = $initials;
        $suffix = 1;

        while (User::where('code', $code)->exists()) {
            $suffix++;
            $code = $initials . $suffix;
        }

        return $code;
    }

    private function initialsFrom(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));

        $initials = array_map(
            fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)),
            $words
        );

        return implode('', $initials);
    }
}
