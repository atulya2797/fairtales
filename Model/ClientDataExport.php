<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ClientDataExport extends Model {

    protected $table = 'clientDataExport';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'name', 'data', 'created_at', 'updated_at'
    ];

}
