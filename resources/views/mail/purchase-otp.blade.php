@component('mail::message')
# Belépési kód a vásárlásaidhoz

Az alábbi 6 jegyű kóddal láthatod a korábbi vásárlásaidat és a letöltési linkjeidet:

@component('mail::panel')
# {{ $otp }}
@endcomponent

A kód **{{ $ttlMinutes }} percig** érvényes. Ha nem te kérted, hagyd figyelmen kívül ezt az e-mailt — a fiókodhoz nem fér hozzá senki.

Üdvözlettel,<br>
{{ $brandName }}
@endcomponent
