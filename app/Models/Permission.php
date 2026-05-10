<?php

namespace App\Models;

use Laratrust\Models\Permission as LaratrustPermission;

class Permission extends LaratrustPermission
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
