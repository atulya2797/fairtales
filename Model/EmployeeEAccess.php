<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class EmployeeEAccess extends Model {

    protected $table = 'Employee_EAccess';

    const ACCESS_FR = 1;
    const ACCESS_BO = 2;
    const ACCESS_TL = 3;
    const ACCESS_PM = 4;
    const ACCESS_CH = 5;
    const ACCESS_RM = 6;
    const ACCESS_STL = 7;
    const ACCESS_OM = 8;
    const ACCESS_ADMIN = 9;
    const ACCESS_SUPER_ADMIN = 10;
    const ACCESS_TM = 11;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'EAccess', 'created_at', 'updated_at'
    ];

}
