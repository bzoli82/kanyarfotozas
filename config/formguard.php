<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Űrlap-védelem (App\Support\FormGuard) — a publikus űrlapokhoz
    | (Kapcsolat, „Kérdés a fotóshoz"). Külső szolgáltatás nélkül.
    |--------------------------------------------------------------------------
    */

    // Proof-of-work nehézség bitekben. 17 ≈ ~130k SHA-256 hash (~0,1–1 s a
    // böngészőben, láthatatlan a látogatónak), de a tömeges automata beküldést
    // számításigényessé teszi. Tesztben alacsonyra állítva.
    'pow_bits' => (int) env('FORMGUARD_POW_BITS', 17),

    // Time-trap: ennyi másodpercnél gyorsabb beküldés = bot (ember lassabban tölt ki).
    'min_fill_seconds' => (int) env('FORMGUARD_MIN_FILL_SECONDS', 3),

    // A kiadott challenge eddig érvényes (utána újra kell tölteni az oldalt).
    'max_age_seconds' => (int) env('FORMGUARD_MAX_AGE_SECONDS', 7200),
];
