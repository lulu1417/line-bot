<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Mobile extends Model
{
    protected $fillable = [
      'user_id', 'text', 'status'
    ];
    public function payment()
    {
        return $this->hasMany(Record::class, 'user_id', 'user_id');
    }
}

