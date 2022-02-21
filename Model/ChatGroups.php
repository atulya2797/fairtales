<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ChatGroups extends Model {

    const TypeTeam = 1;
    const TypeCity = 2;

    protected $table = 'ChatGroups';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'GrpName', 'GrpId', 'type', 'created_at', 'updated_at'
    ];

    public function getChatCityGroup() {
        return $this->hasMany('App\Model\ChatCityGroup', 'GrpId', 'GrpId');
    }

    public function getChatTeamGroup() {
        return $this->hasMany('App\Model\ChatTeamGroup', 'GrpId', 'GrpId');
    }

}
