<?php

namespace App\Services\PlateRecognition;

/**
 * Rendszámfelismerő szolgáltató absztrakció (EPIC-13). Jelenleg a Plate
 * Recognizer felhő API (ingyenes szint) van implementálva; később ugyanezen az
 * interfészen keresztül bővíthető (Google Vision, helyi Python sidecar stb.).
 */
interface PlateRecognitionProvider
{
    /**
     * @return list<PlateDetection>
     */
    public function detect(string $absoluteImagePath): array;
}
