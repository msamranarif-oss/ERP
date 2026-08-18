<?php

namespace App\Http\Requests\Api\V1;

class UpdateInstallmentRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
