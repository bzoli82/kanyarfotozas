<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * „Forensic" (láthatatlan) vízjel a MEGVÁSÁROLT, vízjel nélküli letöltésekbe.
 *
 * A jel a rendeléshez van kötve: minden letöltött fájlba belekerül egy tömör,
 * HMAC-kal aláírt azonosító (`rendelés-id | média-id | időbélyeg`). Ha egy ilyen
 * fájl később kiszivárog (közösségi média, fórum, torrent), az admin a
 * „Kép-visszakövetés" oldalon (`/admin/forensics`) visszafejtheti, MELYIK
 * rendelésből származik → melyik vásárló adta ki.
 *
 * A jel KÉT rétegben ül a fájlban, kép-újrakódolás NÉLKÜL (gyors, nem rontja a
 * minőséget):
 *   1. JPEG-nél egy COM (comment) szegmens közvetlenül az SOI után,
 *   2. minden formátumnál egy záró marker a fájl végén.
 *
 * KORLÁT: a metaadat/záró marker szándékos eltávolítással (pl. `exiftool -all=`,
 * teljes újratömörítés képszerkesztőben) törölhető. Ez tehát elrettentés +
 * a gondatlan továbbadás visszakövetése — NEM feltörhetetlen DRM. Egy valóban
 * pixel-szintű, tömörítés-tűrő vízjel külön DSP-könyvtárat igényelne.
 */
class ForensicWatermark
{
    private const MARKER = "\x00\x00KFWM1";

    private const VERSION = 'v1';

    public static function enabled(): bool
    {
        return (bool) SiteSetting::get('forensic_watermark_enabled', true);
    }

    public static function setEnabled(bool $enabled): void
    {
        SiteSetting::set('forensic_watermark_enabled', $enabled ? '1' : '0');
    }

    /**
     * Beleírja a rendelés-jelet a fájl bájtjaiba (JPEG COM + záró marker).
     */
    public function embed(string $binary, int $orderId, int $mediaId): string
    {
        $payload = implode('|', [self::VERSION, $orderId, $mediaId, now()->timestamp]);
        $marker = self::MARKER.$payload.'|'.$this->sign($payload);

        if (str_starts_with($binary, "\xFF\xD8")) {
            $binary = $this->insertJpegComment($binary, $marker);
        }

        return $binary.$marker;
    }

    /**
     * Kiolvassa és ELLENŐRZI a jelet egy (esetleg kiszivárgott) fájlból.
     *
     * @return array{order_id: int, media_id: int, issued_at: int}|null
     */
    public function read(string $binary): ?array
    {
        $raw = $this->extractFromTrailer($binary) ?? $this->extractFromJpegComment($binary);

        if ($raw === null) {
            return null;
        }

        $parts = explode('|', $raw);

        if (count($parts) !== 5 || $parts[0] !== self::VERSION) {
            return null;
        }

        [$version, $orderId, $mediaId, $issuedAt, $sig] = $parts;
        $payload = implode('|', [$version, $orderId, $mediaId, $issuedAt]);

        if (! hash_equals($this->sign($payload), $sig)) {
            return null;
        }

        return [
            'order_id' => (int) $orderId,
            'media_id' => (int) $mediaId,
            'issued_at' => (int) $issuedAt,
        ];
    }

    private function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 20);
    }

    private function insertJpegComment(string $jpeg, string $comment): string
    {
        $length = strlen($comment) + 2;

        if ($length > 0xFFFF) {
            return $jpeg;
        }

        $segment = "\xFF\xFE".pack('n', $length).$comment;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    private function extractFromTrailer(string $binary): ?string
    {
        $pos = strrpos($binary, self::MARKER);

        if ($pos === false) {
            return null;
        }

        return substr($binary, $pos + strlen(self::MARKER));
    }

    private function extractFromJpegComment(string $binary): ?string
    {
        if (! str_starts_with($binary, "\xFF\xD8")) {
            return null;
        }

        $i = 2;
        $n = strlen($binary);

        while ($i + 4 <= $n && $binary[$i] === "\xFF") {
            $marker = ord($binary[$i + 1]);

            if ($marker === 0xD9 || $marker === 0xDA) {
                break;
            }

            $segLen = unpack('n', substr($binary, $i + 2, 2))[1];

            if ($marker === 0xFE) {
                $com = substr($binary, $i + 4, $segLen - 2);

                if (str_starts_with($com, self::MARKER)) {
                    return substr($com, strlen(self::MARKER));
                }
            }

            $i += 2 + $segLen;
        }

        return null;
    }
}
