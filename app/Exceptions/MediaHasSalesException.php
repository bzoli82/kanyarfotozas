<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Egy médiát nem törlünk véglegesen, ha fizetett rendelés hivatkozik rá — a
 * vásárlónak joga van később is letölteni a megvásárolt fájlt. Ilyenkor a
 * médiát inkább el kell rejteni (`status` váltás), nem törölni.
 */
class MediaHasSalesException extends RuntimeException
{
    public function __construct(public int $mediaId)
    {
        parent::__construct("A(z) #{$mediaId} médiát megvásárolták — nem törölhető véglegesen, csak elrejthető.");
    }
}
