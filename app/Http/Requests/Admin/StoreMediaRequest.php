<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'photographer_id' => [
                $this->user()->isAdmin() ? 'required' : 'nullable',
                'exists:users,id',
            ],
            'files' => ['required', 'array', 'min:1', 'max:200'],
            'files.*' => [
                'required',
                'file',
                'max:512000', // 500 MB / fajl (eredeti feltoltes, MVP korlat)
                'mimes:jpg,jpeg,png,mp4,mov,avi',
            ],
        ];
    }
}
