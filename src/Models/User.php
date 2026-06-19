<?php
namespace Bot\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $fillable = [
        'user_id', 'is_admin', 'username', 'first_name', 
        'last_name', 'status', 'blocked', 'blocked_until', 
        'block_reason', 'join_mandatory_channels', 'step', 'temp'
    ];
    protected $casts = [
        'is_admin' => 'boolean',
        'status' => 'boolean',
        'blocked' => 'boolean',
        'join_mandatory_channels' => 'boolean',
        'temp' => 'array',
    ];
}
