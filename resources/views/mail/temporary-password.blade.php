@component('mail::message')
# {{ $heading }}

{{ $intro }}

@component('mail::panel')
{{ $temporaryPassword }}
@endcomponent

@component('mail::button', ['url' => $loginUrl])
Bejelentkezés
@endcomponent

@if (! empty($outro))
{{ $outro }}
@endif

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
