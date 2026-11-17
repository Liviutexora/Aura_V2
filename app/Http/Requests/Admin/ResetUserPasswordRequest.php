<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user();
        $targetUser = $this->route('user');

        return $admin instanceof User
            && $targetUser instanceof User
            && $admin->isSuperAdmin()
            && ! $admin->is($targetUser)
            && $targetUser->role !== 'super_admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Introdu parola contului SUPER ADMIN.',
            'password.confirmed' => 'Parolele noi nu coincid.',
            'password.min' => 'Parola nouă trebuie să aibă cel puțin 8 caractere.',
        ];
    }
}
