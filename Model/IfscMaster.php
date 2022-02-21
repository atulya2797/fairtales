<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class IfscMaster extends Model {

    protected $table = 'ifsc_master';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'bank', 'ifsc', 'branch', 'created_at', 'updated_at'
    ];

}
