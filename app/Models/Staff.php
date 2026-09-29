<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasApiTokens;

    protected $primaryKey = 'staff_id'; // ADDED

    protected $fillable = ['name', 'role', 'login_info', 'password'];

    protected $hidden = ['password'];
}