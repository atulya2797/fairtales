<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class PoList extends Model {

    protected $table = 'PoList';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'pincode', 'city', 'state', 'created_at', 'updated_at'
    ];

}
