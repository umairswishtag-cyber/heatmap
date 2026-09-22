<?php

namespace App\GraphQL\Mutations;

use App\Services\AccountService;

class AccountMutation
{
    public function __construct(private AccountService $accounts) {}

    public function updateProfile($_, array $args)
    {
        return $this->accounts->update(request()->user(), $args['input']);
    }
}
