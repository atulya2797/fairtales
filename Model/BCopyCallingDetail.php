<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BCopyCallingDetail extends Model {

    protected $table = 'BCopyCallingDetail';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'CRM_ID', 'Call_Agent', 'Call_TimeStamp', 'Call_Recording', 'callFinalOutcome', 'CallFinalStatus', 'Remarks', 'created_at', 'updated_at'
    ];

    public function getBCopyCalling() {
        return $this->hasOne('App\Model\BCopyCalling', 'CRM_ID', 'CRM_ID');
    }

}
