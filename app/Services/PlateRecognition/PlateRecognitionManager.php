<?php

namespace App\Services\PlateRecognition;

use App\Models\Media;
use App\Services\PlateRecognitionSettings;
use Illuminate\Support\Facades\Log;

/**
 * A rendszámfelismerés belépési pontja a feldolgozó pipeline felé (EPIC-13).
 * A funkció adminból TELJESEN kikapcsolható: ha nincs bekapcsolva vagy nincs
 * API kulcs, az `analyze()` `PlateAnalysis::skipped()`-et ad vissza és SEMMILYEN
 * külső hívás nem történik.
 */
class PlateRecognitionManager
{
    public function __construct(private PlateRecognitionSettings $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->isConfigured();
    }

    public function provider(): PlateRecognitionProvider
    {
        if (! $this->settings->isConfigured()) {
            return new NullPlateRecognitionProvider;
        }

        return match ($this->settings->provider()) {
            'platerecognizer' => new PlateRecognizerProvider((string) $this->settings->apiKey()),
            default => new NullPlateRecognitionProvider,
        };
    }

    /**
     * Egyetlen kép elemzése. Hiba esetén (pl. API időtúllépés) nem dobja tovább —
     * naplóz és úgy tér vissza, mintha nem talált volna rendszámot (a feldolgozás
     * nem akadhat el egy külső szolgáltatás miatt).
     */
    public function analyze(string $absoluteImagePath): PlateAnalysis
    {
        if (! $this->settings->isConfigured()) {
            return PlateAnalysis::skipped();
        }

        try {
            $detections = $this->provider()->detect($absoluteImagePath);
        } catch (\Throwable $e) {
            Log::warning('Rendszámfelismerés sikertelen: '.$e->getMessage());

            return new PlateAnalysis(performed: true, status: Media::PLATE_NONE);
        }

        return $this->fromDetections($detections);
    }

    /**
     * Több kockából (videó) a legjobb (legmagasabb confidence) találat.
     *
     * @param  list<string>  $framePaths
     */
    public function analyzeFrames(array $framePaths): PlateAnalysis
    {
        if (! $this->settings->isConfigured()) {
            return PlateAnalysis::skipped();
        }

        $best = null;

        foreach ($framePaths as $path) {
            $analysis = $this->analyze($path);
            if ($analysis->detection && (! $best || $analysis->detection->confidence > $best->confidence)) {
                $best = $analysis->detection;
            }
        }

        return $this->fromDetections($best ? [$best] : []);
    }

    /**
     * @param  list<PlateDetection>  $detections
     */
    private function fromDetections(array $detections): PlateAnalysis
    {
        if ($detections === []) {
            return new PlateAnalysis(performed: true, status: Media::PLATE_NONE);
        }

        $best = collect($detections)->sortByDesc(fn (PlateDetection $d) => $d->confidence)->first();
        $meetsThreshold = $best->confidence >= $this->settings->minConfidence() / 100;

        return new PlateAnalysis(
            performed: true,
            detection: $best,
            status: $meetsThreshold ? Media::PLATE_DETECTED : Media::PLATE_UNIDENTIFIABLE,
            shouldBlur: $meetsThreshold && $this->settings->autoBlur(),
        );
    }
}
