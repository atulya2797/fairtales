<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class EmployeeECity extends Model {

    protected $table = 'Employee_ECity';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'Ecity', 'created_at', 'updated_at'
    ];

}
