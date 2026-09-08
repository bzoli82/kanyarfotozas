@component('mail::message')
# {{ $heading }}

{{ $intro }}

@component('mail::button', ['url' => $downloadUrl])
Letöltés most
@endcomponent

A link **{{ $expiresAt?->translatedFormat('Y. m. d. H:i') }}**-kor lejár.
@if (! empty($outro))

{{ $outro }}
@endif

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
