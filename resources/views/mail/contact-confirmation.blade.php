@component('mail::message')
# {{ $heading }}

{{ $intro }}

@component('mail::panel')
{{ $contactMessage->subject }}
@endcomponent

> {{ $contactMessage->message }}

@if (! empty($outro))
{{ $outro }}
@endif

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
