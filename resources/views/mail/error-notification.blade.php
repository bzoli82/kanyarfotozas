@component('mail::message')
# {{ $isNew ? 'Új hiba a rendszerben' : 'Ismétlődő hiba' }}

**{{ $event->exception_class }}**

> {{ Str::limit($event->message, 400) }}

@component('mail::table')
| | |
|---|---|
| Hol | `{{ $event->file }}:{{ $event->line }}` |
| Kérés | {{ $event->method }} {{ $event->url ?? '—' }} |
| Előfordulás | {{ $event->count }}× |
| Először | {{ $event->first_seen_at?->format('Y-m-d H:i') }} |
| Legutóbb | {{ $event->last_seen_at?->format('Y-m-d H:i') }} |
@endcomponent

@if (! empty($url))
@component('mail::button', ['url' => $url])
Hibanapló megnyitása
@endcomponent
@endif

Erre a hibára a következő értesítés csak egy idő után megy ki (nincs spam egy hibaciklusnál).
@endcomponent
