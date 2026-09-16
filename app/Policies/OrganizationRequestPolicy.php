<?php

namespace App\Policies;

use App\Models\OrganizationRequest;
use App\Models\User;

class OrganizationRequestPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function review(User $user, OrganizationRequest $request): bool
    {
        return $user->can('request.review');
    }
}
