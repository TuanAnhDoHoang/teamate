<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceTypes extends Model
{
    protected $table = 'resource_types';

    public $timestamps = false;

    protected $fillable = [
        'code',
    ];
}
