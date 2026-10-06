<?php

namespace App\Policies;

use App\Models\Permit;
use App\Models\User;

/**
 * Download-only policy. There is deliberately NO update/edit ability for anyone,
 * which is the guard against permit tampering.
 */
class PermitPolicy
{
    public function download(User $user, Permit $permit): bool
    {
        if ($user->isBplo() || $user->isAdmin()) {
            return true;
        }

        return $user->getKey() === $permit->application?->user_id;
    }
}
