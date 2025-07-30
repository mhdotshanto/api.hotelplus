<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccBankInsertRequest extends FormRequest
{
    public function rules()
    {
        $rules = [
            'banking_type' => ['required', 'in:regular,mfs'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:100'],
            'gateway_fee' => ['required', 'numeric', 'min:0'],
            'contact_person' => ['required', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^1\d{9}$/'],
            'email' => ['required', 'email'],
        ];

        if ($this->input('banking_type') === 'mfs') {
            $rules['mfs_provider'] = ['required', 'in:bkash,nagad,rocket'];
            $rules['mfs_number'] = ['required', 'regex:/^1\d{9}$/'];
        }

        if ($this->input('banking_type') === 'regular') {
            $rules['branch_name'] = ['required', 'string'];
            $rules['account_number'] = ['required', 'string'];
            $rules['account_name'] = ['required', 'string'];
        }

        return $rules;
    }
}
