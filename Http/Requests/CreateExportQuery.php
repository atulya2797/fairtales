<?php

namespace App\Http\Requests;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class CreateExportQuery extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $rules = [
            'name' => 'required|string|unique:clientDataExport,name',
            'headerName.*' => 'required|string|distinct',
            'defaultValue.*' => 'nullable|string'
        ];
        if (request()->route('id')) {
            $rules['name'] = 'required|string|unique:clientDataExport,name,' . request()->route('id');
        }

        return $rules;
    }

    public function messages() {
        return [
            'name.required' => 'Please Enter Name.',
            'name.unique' => 'Name Already used, Please enter another one.',
            'headerName.*.required' => 'Please Enter Headers.',
            'headerName.*.string' => 'Invalid Header Name.',
            'headerName.*.distinct' => 'Header Name Cannot be same.',
            'defaultValue.*.string' => 'Invalid Default Value.'
        ];
    }

}
