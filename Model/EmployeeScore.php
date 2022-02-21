<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class EmployeeScore extends Model {

    protected $table = 'EmployeeScore';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'EID', 'CurrentDate', 'CountProspect', 'CountNACHSignup', 'CountNachAVReject', 'CountNACHAVSuccess', 'CountNACHAVPending', 'FPNachAccept', 'FPNachReject', 'FPNachModify', 'WCNachVerified', 'WCNachReject', 'DataEntryNachAccept', 'DataEntryNachReject', 'CountENACHSignup', 'CountENachAVReject', 'CountENACHAVSuccess', 'WCENachVerified', 'WCENachReject', 'DataEntryENachAccept', 'DataEntryENachReject', 'CountOnlineSignup', 'CountOnlineAVReject', 'CountOnlineAVSuccess', 'WCOnlineVerified', 'WCOnlineReject', 'DataEntryOnlineAccept', 'DataEntryOnlineReject', 'created_at', 'updated_at'
    ];

    public function getEmployee() {
        return $this->hasOne('App\Model\EmployeeMaster', 'EID', 'EID');
    }

}
