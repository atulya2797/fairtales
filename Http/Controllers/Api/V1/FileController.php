<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController;

class FileController extends ApiController {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
//        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function fileUpload(Request $request) {

        $input = request()->all();

        $file = $input['file'];

        $fileName = $file->getClientOriginalName();
        $newName = uniqid() . $this->clearString($fileName);
        $file->move(base_path() . '/public/temp/', $newName);
        $data = [
            'fileName' => url('/') . '/temp/' . $newName
        ];

        return $this->jsonResponse(true, 200, "File Successfully Uploaded", $data);
    }

    public function bulkFileUpload(Request $request) {
        $input = request()->all();
        if (!isset($input['file'])) {
            return $this->jsonResponse(false, 422, "Please Select Files");
        }
//        print_r($input['file']);die;
        $data = [];
        if (count($input['file'])) {
            foreach ($input['file'] as $key => $val) {
                $file = $val;
                $fileName = $file->getClientOriginalName();
                $newName = uniqid() . $this->clearString($fileName);
                $file->move(base_path() . '/public/temp/', $newName);
                $data[] = [
                    'key' => $key,
                    'value' => url('/') . '/temp/' . $newName
                ];
            }
        }
        return $this->jsonResponse(true, 200, "File Successfully Uploaded", $data);
    }

    public function clearString($string) {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        return preg_replace('/[^A-Za-z.0-9\-]/', '', $string); // Removes special chars.
    }

}
