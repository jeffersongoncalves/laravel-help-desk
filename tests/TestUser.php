<?php

namespace JeffersonGoncalves\HelpDesk\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use JeffersonGoncalves\HelpDesk\Concerns\IsOperator;

class TestUser extends Model
{
    use IsOperator, Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}
