<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alapértelmezett fizetési szolgáltató
    |--------------------------------------------------------------------------
    |
    | Az `App\Services\Payments\PaymentGatewayManager` ezt használja, ha a kosár
    | nem küld explicit `provider`-t. Ha a beállított alapértelmezett nincs
    | konfigurálva (nincs kulcsa), az első elérhető szolgáltatóra esik vissza.
    |
    | Támogatott: 'stripe', 'simplepay', 'barion'
    |
    */

    'default' => env('PAYMENT_PROVIDER_DEFAULT', 'stripe'),

];
