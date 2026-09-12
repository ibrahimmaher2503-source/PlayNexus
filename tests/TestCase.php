<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(UserContract $user, $guard = null)
    {
        $this->withSession(['auth_version' => (int) ($user->auth_version ?? 1)]);

        return parent::actingAs($user, $guard);
    }
}
