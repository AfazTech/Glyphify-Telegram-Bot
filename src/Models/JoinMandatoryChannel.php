<?php
namespace Bot\Models;

use Illuminate\Database\Eloquent\Model;

class JoinMandatoryChannel extends Model
{
    protected $table = 'join_mandatory_channels';
    protected $fillable = ['chat_id', 'link', 'title', 'active'];
    protected $casts = [
        'active' => 'boolean',
    ];
}
