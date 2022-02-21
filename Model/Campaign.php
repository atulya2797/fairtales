<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model {

    protected $table = 'Campaign';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'CharityCodeId', 'CityId', 'Campaign', 'created_at', 'updated_at'
    ];

}
