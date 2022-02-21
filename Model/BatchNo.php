<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BatchNo extends Model {

    protected $table = 'batchNo';

    const StatusUsed = 1;
    const StatusUnUsed = 0;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'CharityCode', 'ModeOfDonation', 'batchNo', 'status', 'created_at', 'updated_at'
    ];

}
