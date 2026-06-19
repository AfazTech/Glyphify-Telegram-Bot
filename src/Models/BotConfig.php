<?php
namespace Bot\Models;

use Illuminate\Database\Eloquent\Model;

class BotConfig extends Model
{
    protected $table = 'bot_config';
    protected $fillable = ['key', 'value'];
}
