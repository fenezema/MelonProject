<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'content', 'time_zone', 'user_id',
    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    const MAJOR_DATA_NUTRITION = [
        'bakso' => 325,
        'soto' => 312,
        'telur' => 72,
        'telor' => 72,
    ];
}
