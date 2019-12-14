<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Mobile extends Model
{
    protected $fillable = [
      'userId', 'text', 'status'
    ];
}

