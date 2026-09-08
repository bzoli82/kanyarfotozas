<?php

namespace App\Http\Requests\Admin;

use App\Models\Event;
use App\Models\User;
use App\Services\FtpImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Uj esemeny letrehozasa: admin barmikor, fotos is barmikor (o lesz a created_by).
        return $this->user()?->can('manage-event') ?? false;
    }

    public function rules(): array
    {
        return [
            'country_id' => ['required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'event_date' => ['required', 'date'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in([
                Event::STATUS_DRAFT, Event::STATUS_ANNOUNCED, Event::STATUS_LIVE, Event::STATUS_ARCHIVED,
            ])],

            // Esemeny-szintu arazas: egy ar minden fotora, egy minden videora (Ft).
            'photo_price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'video_price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],

            // Esemeny-szervezo + reszesedese (csak admin allitja, ld. EventController).
            'organizer_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('role', User::ROLE_ORGANIZER)],
            'organizer_share_percent' => ['nullable', 'integer', 'min:0', 'max:100', 'required_with:organizer_id'],

            // Opcionalis: media importalasa a tarolobol mindjart letrehozaskor.
            // A fotos a sajat neveben importal (nem kell import_photographer_id).
            'import_paths' => ['nullable', 'array', 'max:'.FtpImport::MAX_PER_IMPORT],
            'import_paths.*' => ['required', 'string', 'max:1024'],
            'import_photographer_id' => [
                'nullable',
                Rule::requiredIf(fn (): bool => filled($this->input('import_paths')) && (bool) $this->user()?->isAdmin()),
                'uuid',
                Rule::exists('users', 'id')->where('role', User::ROLE_PHOTOGRAPHER),
            ],
        ];
    }
}
