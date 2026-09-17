<?php

namespace JeffersonGoncalves\HelpDesk\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use JeffersonGoncalves\HelpDesk\Concerns\IsOperator;

/**
 * Authenticatable, because a real satellite's user model is — and the API
 * driver resolves the actor for a read from whoever is logged in.
 */
class TestUser extends Authenticatable
{
    use IsOperator, Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}
