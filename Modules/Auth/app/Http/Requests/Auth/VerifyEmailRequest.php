<?php

namespace Modules\Auth\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hash' => ['required', 'string'],
            'id' => ['required', 'string'],
            'expires' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ];
    }
}
