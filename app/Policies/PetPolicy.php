<?php

namespace App\Policies;

use App\Models\Pet;
use App\Models\User;

class PetPolicy
{
    public function archive(User $user, Pet $pet): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Pet $pet): bool
    {
        return $user->isAdmin();
    }
}
