<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Team extends Model {

    protected $table = 'Team';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'TeamName', 'created_at', 'updated_at'
    ];

}
