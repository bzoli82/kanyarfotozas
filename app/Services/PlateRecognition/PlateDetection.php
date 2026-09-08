<?php

namespace App\Services\PlateRecognition;

/**
 * Egy felismert rendszám az OCR szolgáltatótól. A `box` a FORRÁS kép pixeleiben
 * értendő (`{x, y, w, h}`) — a homályosításnál a célváltozat méretére skálázzuk.
 */
final class PlateDetection
{
    /**
     * @param  float  $confidence  0..1
     * @param  array{x: int, y: int, w: int, h: int}  $box
     */
    public function __construct(
        public string $plate,
        public float $confidence,
        public array $box,
    ) {}
}
