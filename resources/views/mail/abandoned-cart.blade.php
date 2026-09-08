@component('mail::message')
# {{ $heading }}

{{ $intro }}

@component('mail::button', ['url' => $resumeUrl])
Vissza a kosárhoz
@endcomponent

@if (! empty($outro))
{{ $outro }}
@endif

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
