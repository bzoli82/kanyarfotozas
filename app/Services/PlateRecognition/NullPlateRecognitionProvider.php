<?php

namespace App\Services\PlateRecognition;

/**
 * Nincs-felismerés szolgáltató — ez a provider, ha a funkció adminból ki van
 * kapcsolva vagy nincs API kulcs. Sose hív külső szolgáltatást, mindig üres.
 */
final class NullPlateRecognitionProvider implements PlateRecognitionProvider
{
    public function detect(string $absoluteImagePath): array
    {
        return [];
    }
}
