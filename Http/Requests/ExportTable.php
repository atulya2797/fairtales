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
class ExportTable extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $input = request()->all();

        $rule = [
            'table' => 'required|string',
            'from' => 'nullable|integer|min:0',
            'to' => 'nullable|integer|min:0|max:5000'
        ];
        return $rule;
    }

    public function messages() {
        return [
            'table.required' => 'Please Select Table.',
            'table.string' => 'Invalid Table Name.',
            'from.integer' => 'Invalid Limit From Value.',
            'from.min' => 'Min Value of Limit To will be 0.',
            'to.integer' => 'Invalid Limit To Value.',
            'to.min' => 'Min Value of Limit To will be 0.',
        ];
    }

}
