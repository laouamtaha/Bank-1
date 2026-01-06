<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Traits\CanChat;

class Team extends Model
{
    use CanChat;

    protected $fillable = ['name'];
}
