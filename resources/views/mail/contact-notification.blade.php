@component('mail::message')
# {{ $heading }}

@if (! empty($intro))
{{ $intro }}
@endif

**Név:** {{ $contactMessage->name }}
**E-mail:** {{ $contactMessage->email }}
**Tárgy:** {{ $contactMessage->subject }}

---

{{ $contactMessage->message }}

@if (! empty($outro))
{{ $outro }}
@endif

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
