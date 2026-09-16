<?php

namespace App\Policies;

use App\Models\OrganizationRequest;
use App\Models\User;

class OrganizationRequestPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function review(User $user, OrganizationRequest $request): bool
    {
        return $user->can('request.review');
    }
}
