<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Bármely fizetési szolgáltató hibája egységes típusban — a CheckoutController
 * ezt fordítja felhasználóbarát magyar üzenetre (a nyers SDK/HTTP hiba helyett).
 */
class PaymentException extends RuntimeException {}
