@component('mail::message')
# Sürgős rendszerfigyelmeztetések

A rendszer az alábbi, azonnali figyelmet igénylő problémákat észlelte:

@foreach ($alerts as $alert)
**{{ $alert['title'] }}**
{{ $alert['description'] }}

@endforeach

@component('mail::button', ['url' => $dashboardUrl])
Dashboard megnyitása
@endcomponent

Ezt az összefoglalót a rendszer 15 percenként ellenőrzi, és csak akkor küld, ha új sürgős figyelmeztetés jelent meg.

Üdvözlettel,<br>
{{ $brandName }}
@endcomponent
