<?php

namespace App\Http\Requests\Api\V1;

class VerifyCreditCustomerRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'verification_data' => 'required|array',
            'verification_data.identity_document' => 'required|string|max:255',
            'verification_data.address_proof' => 'required|string|max:255',
            'verification_data.income_proof' => 'required|string|max:255',
        ];
    }
}
