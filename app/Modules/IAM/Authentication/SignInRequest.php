<?php

namespace App\Modules\IAM\Authentication;

use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * ROUTE-101's request: the e-mail and the password, each required (MSG-12 at the fields; IP-14). No "remember me"
 * is read (AU-08). An empty field is the only answer that names a field; every other refusal is AU-04's one.
 */
final class SignInRequest extends LoginRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|max:254',
            'password' => 'required|string|max:1024',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('forms.required'),
            'password.required' => __('forms.required'),
            'email.max' => __('messages.msg_19'),
            'password.max' => __('messages.msg_19'),
        ];
    }
}
