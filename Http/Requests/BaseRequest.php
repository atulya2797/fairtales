<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Validator as CustomValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 
 * @class   BaseRequest extends FormRequest
 * 
 * @Notice  All the custom FormRequest method for custom validation function are included in to base request.
 * 
 */
class BaseRequest extends FormRequest {

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        CustomValidator::extend('phone', function($attribute, $value, $parameters, $validator) {
            $response = (preg_match('%^(?:(?:\(?(?:00|\+)([1-4]\d\d|[1-9]\d?)\)?)?[\-\.\ \\\/]?)?((?:\(?\d{1,}\)?[\-\.\ \\\/]?){0,})(?:[\-\.\ \\\/]?(?:#|ext\.?|extension|x)[\-\.\ \\\/]?(\d+))?$%i', $value) && strlen($value) >= 10 && strlen($value) <= 10) ? true : false;
            return $response;
        });
        CustomValidator::replacer('phone', function($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, ':attribute is invalid phone number');
        });
        CustomValidator::extend('passwordRegex', function($attribute, $value, $parameters, $validator) {
            return preg_match('/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/', $value);
        });
        CustomValidator::replacer('passwordRegex', function($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, ':attribute should be atleast 8 characters and contain atleast one digit and one special character.');
        });
        CustomValidator::extend('domain', function($attribute, $value, $parameters, $validator) {
            return preg_match('/^(http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$/m', $value);
        });
        CustomValidator::replacer('domain', function($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, ':attribute is invalid Website/Domain');
        });

        return true;
    }

    /**
     * 
     * @param Validator $validator This is use to rewrite the error for 
     *                  Api only based on the header type application/json otherwise 
     *                  this will return simple error same as before
     * @throws type
     */
    protected function failedValidation(Validator $validator) {
        /**
         * 
         * Warning: Don't use this for Backend, Because if you do you 
         *          will not receive required error format
         * 
         */
        $getHeaders = getAllHeaders();
        $response = null;
        if (isset($getHeaders['Accept']) && (strtolower($getHeaders['Accept']) == 'application/json')) {
            $errorData = [
                'status' => false,
                'message' => 'Invalid Input Data',
//                'code' => 422,
                'data' => []
            ];
            $headers = [];
            $options = 0;
            if ($validator->errors() && $validator->errors()->messages() && count($validator->errors()->messages())) {
                $errorDetails = [];
                foreach ($validator->errors()->messages() as $key => $val) {
                    $errorDetails[$key] = isset($val[0]) ? $val[0] : 'Please fix error for ' . $key;
                }
                /**
                 * Testing for IOS
                 */
                $getAllKey = array_keys($errorDetails);
                $errorData['message'] = implode(',', $getAllKey);
                /*
                 * Remove after Test IOS error message
                 */
                $errorData['data'] = $errorDetails;
            }
            $response = response()->json($errorData, 422, $headers, $options);
        }
        throw (new ValidationException($validator, $response))
                ->errorBag($this->errorBag)
                ->redirectTo($this->getRedirectUrl());
    }

}
