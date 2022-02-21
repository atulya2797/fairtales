<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class AttendanceStamps extends Model {

    protected $table = 'attendanceStamps';
    protected $fillable = [
        'id', 'attendance_id', 'InStamp', 'OutStamp', 'created_at', 'updated_at'
    ];

}
