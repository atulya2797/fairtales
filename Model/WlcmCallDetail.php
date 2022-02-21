<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WlcmCallDetail extends Model {

    protected $table = 'WlcmCall_Detail';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'CRM_ID', 'Call_Agent', 'Call_TimeStamp', 'Call_Recording', 'CallOutcome', 'Call_FinalStatus', 'Call_FinalStatusRemark', 'CallBackTime', 'created_at', 'updated_at'
    ];

    public function getSignup() {
        return $this->hasOne('App\Model\Signup', 'CRM_ID', 'CRM_ID');
    }

}
