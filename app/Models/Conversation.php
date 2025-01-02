<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id','user_message', 'chatgpt_response'];



    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
