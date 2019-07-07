<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LocationHistory extends Model
{
    protected $fillable = [
        'user_id', 'lat', 'lng', 'timestamp', 
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function user(){
        return $this->belongsTo('App\User');
    }
}
