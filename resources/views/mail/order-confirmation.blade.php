@component('mail::message')
# {{ $heading }}

{{ $intro }}

@component('mail::button', ['url' => $downloadUrl])
Letöltési oldal megnyitása
@endcomponent

@component('mail::button', ['url' => $zipUrl, 'color' => 'green'])
Összes letöltése ZIP-ben
@endcomponent

@if (! empty($invoiceUrl))
[Számla letöltése (PDF)]({{ $invoiceUrl }})
@endif

---

@foreach ($items as $item)
**{{ $item['title'] }}** — {{ $item['type'] === 'video' ? 'Videó' : 'Kép' }} ({{ $item['price_cents'] }} Ft)
@foreach ($item['formats'] as $format)
[{{ $format['label'] }} letöltése]({{ $format['url'] }}){{ ! $loop->last ? ' · ' : '' }}
@endforeach

@endforeach

---

A linkek {{ $expiresAt?->translatedFormat('Y. m. d. H:i') }}-ig érvényesek.
@if (! empty($outro))

{{ $outro }}
@endif

{{ $downloadUrl }}

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
