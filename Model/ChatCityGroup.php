<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ChatCityGroup extends Model {

    protected $table = 'Chat_City_Grp';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'EID', 'GrpName', 'GrpId', 'created_at', 'updated_at'
    ];

}
