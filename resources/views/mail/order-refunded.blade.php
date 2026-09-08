@component('mail::message')
# {{ $heading }}

{{ $intro }}

@component('mail::panel')
Visszatérített összeg: **{{ $amount }}**
@if ($fullRefund)

A rendelés teljes összegét visszatérítettük — a letöltési link már nem használható.
@endif
@endcomponent

@if (! empty($outro))
{{ $outro }}
@endif

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
