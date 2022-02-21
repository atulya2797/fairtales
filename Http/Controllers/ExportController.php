<?php

namespace App\Http\Controllers;

use App\Model\Export\Export;
use App\Http\Requests\ExportTable;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller {

    public function __construct() {
        parent::__construct();
    }

    public function showTables() {
        $getAllTables = DB::select('SHOW TABLES');
        $dbName = DB::connection()->getDatabaseName();
        $tables = array_column($getAllTables, 'Tables_in_' . $dbName);
        $removeTables = ['failed_jobs', 'migrations', 'password_resets', 'users'];
        $mainTables = array_diff($tables, $removeTables);

        return view('export.showTables', compact('mainTables'));
    }

    public function exportTable(ExportTable $request) {
        $input = request()->all();
        $table = $input['table'];
        $limitFrom = $input['from'] ?? 0;
        $limitTo = $input['to'] ?? null;

        if ($limitFrom > $limitTo) {
            return $this->sendResponse(false, '', 'Record From Cannot be grater then Recor to.');
        }
        $data = $this->exportTableDetail($table, $limitFrom, $limitTo);
        return $this->sendResponse(true, '', 'Export Successfuly', $data, 'exportDataTable');
    }

    public function exportTableDetail($table, $limitFrom = 0, $limitTo = null) {
        $exportModel = new Export($table);

        if (($limitTo !== null) && ($limitFrom !== null)) {
            $resultData = $exportModel->limit($limitTo)
                    ->offset($limitFrom)
                    ->get()
                    ->toArray();
        } else {
            $resultData = $exportModel
                    ->get()
                    ->toArray();
        }

        /**
         * add column header
         */
        if (count($resultData)) {
            $getFirstArray = $resultData['0'];
            $getHeader = array_keys($getFirstArray);
            $resultData = array_merge([$getHeader], $resultData);
        }

        $fileName = $table . '_' . uniqid() . '.csv';
        $file = fopen(public_path() . '/temp/' . $fileName, 'w');
        foreach ($resultData as $row) {
            fputcsv($file, $row);
        }
        fclose($file);
        $fileUrl = url('/') . '/temp/' . $fileName;
        $data = [
            'fileUrl' => $fileUrl
        ];
        return $data;
    }

}
