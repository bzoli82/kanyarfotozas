@component('mail::message')
# {{ $heading }}

{{ $intro }}

@component('mail::button', ['url' => $acceptUrl])
Meghívó elfogadása
@endcomponent

A meghívó {{ $expiresAt?->translatedFormat('Y. m. d. H:i') }}-ig érvényes.
@if (! empty($outro))

{{ $outro }}
@endif

{{ $acceptUrl }}

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
