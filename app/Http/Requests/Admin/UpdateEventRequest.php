<?php

namespace App\Http\Requests\Admin;

use Illuminate\Support\Arr;

class UpdateEventRequest extends StoreEventRequest
{
    public function authorize(): bool
    {
        // Meglevo esemeny szerkesztese: admin barmelyiket, fotos csak a sajatjat (created_by).
        return $this->user()?->can('manage-event', $this->route('event')) ?? false;
    }

    /**
     * A szerkesztes csak az esemeny-mezoket allitja; a media-import (`import_paths`)
     * kizarolag letrehozaskor ertelmezett.
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['import_paths', 'import_paths.*', 'import_photographer_id']);
    }
}
