<?php

namespace App\Model\Export;

use Illuminate\Database\Eloquent\Model;

class Export extends Model {

    protected $table = '';

    public function __construct($tableName) {
        $this->table = $tableName;
    }

}
