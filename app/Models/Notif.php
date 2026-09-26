<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notif extends Model
{
    protected $table = 'notifications_app';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['lu' => 'boolean'];
    }
}
