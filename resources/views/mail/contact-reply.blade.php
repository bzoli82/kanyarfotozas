@component('mail::message')
# {{ $heading }}

{{ $intro }}

@component('mail::panel')
{!! nl2br(e($replyBody)) !!}
@endcomponent

@if (! empty($originalMessage))
---

**Az eredeti üzeneted:**

> {{ $originalMessage }}
@endif

@if (! empty($outro))
{{ $outro }}
@endif

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
