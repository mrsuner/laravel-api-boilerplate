<?php

namespace App\Models;

use Laratrust\Models\Role as LaratrustRole;

class Role extends LaratrustRole
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];
}
