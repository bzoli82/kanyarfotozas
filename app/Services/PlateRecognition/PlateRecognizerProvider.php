<?php

namespace App\Services\PlateRecognition;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Plate Recognizer (platerecognizer.com) felhő API — Snapshot végpont.
 * Ingyenes szint: 2500 lekérés / hó. A kulcs a `site_settings`-ből (titkosítva).
 *
 * @see https://guides.platerecognizer.com/docs/snapshot/api-reference/
 */
final class PlateRecognizerProvider implements PlateRecognitionProvider
{
    private const ENDPOINT = 'https://api.platerecognizer.com/v1/plate-reader/';

    public function __construct(private string $apiKey) {}

    public function detect(string $absoluteImagePath): array
    {
        $response = Http::withHeaders(['Authorization' => 'Token '.$this->apiKey])
            ->timeout(30)
            ->attach('upload', (string) file_get_contents($absoluteImagePath), basename($absoluteImagePath))
            ->post(self::ENDPOINT);

        if ($response->status() === 403) {
            throw new RuntimeException('Plate Recognizer: érvénytelen API kulcs.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Plate Recognizer hiba ('.$response->status().'): '.$response->body());
        }

        return collect($response->json('results', []))
            ->map(fn (array $result) => new PlateDetection(
                plate: mb_strtoupper((string) ($result['plate'] ?? '')),
                confidence: (float) ($result['score'] ?? 0),
                box: $this->box($result['box'] ?? []),
            ))
            ->filter(fn (PlateDetection $d) => $d->plate !== '' && $d->box['w'] > 1)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $box  {xmin, ymin, xmax, ymax}
     * @return array{x: int, y: int, w: int, h: int}
     */
    private function box(array $box): array
    {
        $xmin = (int) ($box['xmin'] ?? 0);
        $ymin = (int) ($box['ymin'] ?? 0);
        $xmax = (int) ($box['xmax'] ?? 0);
        $ymax = (int) ($box['ymax'] ?? 0);

        return [
            'x' => max(0, $xmin),
            'y' => max(0, $ymin),
            'w' => max(0, $xmax - $xmin),
            'h' => max(0, $ymax - $ymin),
        ];
    }
}
