@component('mail::message')
# {{ $heading }}

{{ $intro }}

@component('mail::table')
| | |
|:---|---:|
| Eladott média | {{ $report['media_sold'] }} db |
| Bruttó bevétel | {{ number_format($report['revenue_cents'], 0, ',', ' ') }} Ft |
| A te részesedésed ({{ $photographer->revenue_share_percent }}%) | {{ number_format($report['photographer_share_cents'], 0, ',', ' ') }} Ft |
@endcomponent

@if ($report['top_events']->isNotEmpty())
**Legjobban teljesítő eseményeid:**
@foreach ($report['top_events'] as $event)
- {{ $event['name'] }} — {{ number_format($event['revenue_cents'], 0, ',', ' ') }} Ft
@endforeach
@endif

@if ($period === 'havi')
A teljes tételes lista a csatolt CSV-fájlban található.
@endif

@component('mail::button', ['url' => route('photographer.dashboard')])
Részletek a dashboardon
@endcomponent

@if (! empty($outro))
{{ $outro }}
@endif

@if (! empty($signature))
{!! nl2br(e($signature)) !!}
@endif
@endcomponent
