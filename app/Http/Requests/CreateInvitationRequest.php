<?php

namespace App\Http\Requests;

class CreateInvitationRequest extends SaveUserRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['user_id'] = ['prohibited'];
        $rules['role'] = ['required', 'in:professional'];
        $rules['password'] = ['prohibited'];
        $rules['password_confirmation'] = ['prohibited'];

        return $rules;
    }
}
