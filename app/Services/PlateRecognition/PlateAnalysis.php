<?php

namespace App\Services\PlateRecognition;

use App\Models\Media;

/**
 * Egy médiafájl rendszám-elemzésének eredménye — a feldolgozó job ez alapján
 * dönt a mezők mentéséről és a régió homályosításáról.
 */
final class PlateAnalysis
{
    public function __construct(
        public bool $performed,
        public ?PlateDetection $detection = null,
        public string $status = Media::PLATE_PENDING,
        public bool $shouldBlur = false,
    ) {}

    /** A felismerés ki van kapcsolva / nincs kulcs — a pipeline kihagyja. */
    public static function skipped(): self
    {
        return new self(performed: false);
    }

    /**
     * @return array<string, mixed> a Media modellre menthető mezők
     */
    public function toMediaAttributes(): array
    {
        return [
            'license_plate' => $this->detection?->plate,
            'license_plate_bbox' => $this->detection?->box,
            'license_plate_confidence' => $this->detection?->confidence,
            'license_plate_blurred' => $this->shouldBlur,
            'license_plate_status' => $this->performed ? $this->status : Media::PLATE_PENDING,
        ];
    }
}
