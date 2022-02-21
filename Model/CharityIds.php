<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class CharityIds extends Model {

    protected $table = 'CharityIds';

    const StatusUsed = 1;
    const StatusUnUsed = 0;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'CharityCode', 'ModeOfDonation', 'Charity_ID', 'status', 'created_at', 'updated_at'
    ];

}
