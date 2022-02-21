<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class CharityCode extends Model {

    protected $table = 'CharityCode';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'CharityCode', 'created_at', 'updated_at'
    ];

}
