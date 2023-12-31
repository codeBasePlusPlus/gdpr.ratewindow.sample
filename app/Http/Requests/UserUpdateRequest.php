<?php

namespace App\Http\Requests;

use App\Rules\UserRole;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
            'name' => ['required'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->route()->user->id)],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)->symbols()],
            'role' => ['required', new UserRole],
            'organisation_id' => ['required', 'exists:organisations,id'],
            'disabled' => 'nullable|sometimes|boolean'
        ];
    }
}
