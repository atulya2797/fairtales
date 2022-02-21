<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SiteSettings extends Model {

    protected $table = 'siteSettings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'name', 'value', 'created_at', 'updated_at'
    ];

    public static function getNameList() {
        return [
            'exotel_calling_number',
            'exotel_lead_calling_number',
            'report_active_action'
        ];
    }

}
