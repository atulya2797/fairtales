<?php

namespace App\Model\Import;

use Illuminate\Database\Eloquent\Model;

class Import extends Model {

    protected $table = '';

    public function __construct($tableName) {
        $this->table = $tableName;
    }

}
