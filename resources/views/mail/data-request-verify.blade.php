@component('mail::message')
# {{ $isDelete ? 'Adattörlési kérelem' : 'Adatkiadási kérelem' }}

Erre az e-mail címre {{ $isDelete
    ? 'a hozzád tartozó személyes adatok törlésére'
    : 'a hozzád tartozó személyes adatok kiadására' }} érkezett kérelem.

Ha te kérted, erősítsd meg az alábbi gombbal. A kérés csak ezután indul el.

@component('mail::button', ['url' => $verifyUrl])
Megerősítem a kérelmet
@endcomponent

Ha nem te kérted, hagyd figyelmen kívül ezt az e-mailt — semmi nem történik.
@endcomponent
