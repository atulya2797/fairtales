<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class EmployeeTargetQaulity extends Model {

    protected $table = 'EmployeeTargetQaulity';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'EID', 'target', 'quality', 'date', 'created_at', 'updated_at'
    ];

}
