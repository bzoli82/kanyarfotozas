@component('mail::message')
# Megérkeztek a felvételek!

Feliratkoztál a **{{ $event->location }}** helyszín értesítőjére — az **„{{ $event->name }}”** esemény képei és videói most elérhetők a galériában.

@component('mail::button', ['url' => $galleryUrl])
Galéria megnyitása
@endcomponent

Keresd magad időpont szerint, nézd meg vízjeles előnézetben, és regisztráció nélkül vásárolj.

Üdvözlettel,<br>
{{ $brandName }}

---

<small>Nem kérsz több értesítőt erről a helyszínről? [Leiratkozás]({{ $unsubscribeUrl }})</small>
@endcomponent
