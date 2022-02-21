<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class EmployeeEDesg extends Model {

    protected $table = 'Employee_EDesg';

    const DESG_FR = 1;
    const DESG_BO = 2;
    const DESG_TL = 3;
    const DESG_PM = 4;
    const DESG_CH = 5;
    const DESG_RM = 6;
    const DESG_STL = 7;
    const DESG_OM = 8;
    const DESG_ADMIN = 9;
    const DESG_SUPER_ADMIN = 10;
    const DESG_TM = 11;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'EDesg', 'created_at', 'updated_at'
    ];

}
