<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\DownloadFingerprint;
use App\Models\Media;
use App\Models\Order;
use App\Services\ForensicWatermark;
use App\Services\MediaStorage;
use App\Services\OrderFulfillment;
use App\Services\SiteBranding;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function __construct(private OrderFulfillment $fulfillment) {}

    /**
     * Letoltesi oldal — a token "hasznalatai" itt szamitanak (oldal-megnyitas,
     * NEM egyenkent minden fajl-letoltes), hogy egy tobb tetelt tartalmazo
     * rendelesnel is le tudja tolteni az osszes vasarolt tartalmat.
     */
    public function show(string $token): InertiaResponse
    {
        $order = Order::query()->where('download_token', $token)->firstOrFail();

        abort_unless($order->isPaid(), 404);
        abort_unless($order->isTokenValid(), 410);

        $order->increment('download_token_uses');

        // Ha a kézbesítési gyorsítótár még nem áll készen (pl. a queue worker nem
        // fut, vagy az archív épp elakadt), próbáljuk meg most feltölteni — a
        // prepare() sosem dob kivételt, csak jelzi az állapotot.
        if (! $order->deliveryReady() && $order->fulfillment_status !== 'failed') {
            rescue(fn () => $this->fulfillment->prepare($order), report: false);
            $order->refresh();
        }

        $invoice = $order->normalInvoice();

        return Inertia::render('Download/Show', [
            'token' => $token,
            'orderNumber' => $order->order_number,
            'invoiceUrl' => $invoice && filled($invoice->pdf_path) ? route('public.download.invoice', $token) : null,
            'items' => $order->media->map(fn (Media $media) => [
                ...(new MediaResource($media))->resolve(),
                'download_formats' => $this->fulfillment->formatsFor($media),
            ])->values(),
            'expiresAt' => $order->token_expires_at?->toIso8601String(),
            'usesLeft' => max(0, Order::TOKEN_MAX_USES - $order->download_token_uses),
            // A letöltő fájlok előkészítése (archív → gyors gyorsítótár) folyamatban van-e.
            'preparing' => ! $order->deliveryReady() && $order->fulfillment_status !== 'failed',
        ]);
    }

    /**
     * A rendeléshez tartozó (végszámla) PDF — a letöltési tokennel igazolt vásárlásnak.
     */
    public function invoice(string $token): Response
    {
        $order = Order::query()->where('download_token', $token)->firstOrFail();
        abort_unless($order->isPaid(), 404);

        $invoice = $order->normalInvoice();
        abort_unless($invoice && filled($invoice->pdf_path), 404);

        $contents = Storage::disk(MediaStorage::STAGING)->get($invoice->pdf_path);
        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="szamla-'.($invoice->number ?: $invoice->id).'.pdf"',
        ]);
    }

    public function file(Request $request, string $token, Media $media, string $format): StreamedResponse|Response
    {
        $order = Order::query()->where('download_token', $token)->firstOrFail();

        abort_unless($order->isPaid() && $order->isTokenValid(), 410);
        abort_unless($order->media->contains($media->id), 403);

        $source = $this->fulfillment->resolveSource($order, $media, $format);
        abort_unless($source, 404);

        [$disk, $key] = $source;

        // Fotó: a fájlba láthatatlan, rendeléshez kötött „forensic" jel kerül,
        // hogy egy esetleges kiszivárgás visszakövethető legyen (ld. ForensicWatermark).
        if ($media->isPhoto() && in_array($format, ['jpeg', 'webp'], true) && ForensicWatermark::enabled()) {
            $contents = Storage::disk($disk)->get($key);
            abort_if($contents === null, 404);

            $stamped = app(ForensicWatermark::class)->embed($contents, $order->id, $media->id);
            $this->logFingerprint($order, $media, $format, $request);

            return response($stamped, 200, [
                'Content-Type' => $format === 'webp' ? 'image/webp' : 'image/jpeg',
                'Content-Disposition' => 'attachment; filename="'.$this->downloadFilename($media, $format).'"',
            ]);
        }

        return Storage::disk($disk)->download($key, $this->downloadFilename($media, $format));
    }

    private function logFingerprint(Order $order, Media $media, string $format, Request $request): void
    {
        DownloadFingerprint::create([
            'order_id' => $order->id,
            'media_id' => $media->id,
            'format' => $format,
            'ip_hash' => hash('sha256', $request->ip().(string) config('app.key')),
        ]);
    }

    /**
     * Az osszes vasarolt tetelt egyetlen ZIP-be csomagolja (JPEG+WebP kepekhez,
     * MP4 videokhoz) — a fajlok a gyors kezbesitesi gyorsitotarbol (vagy fallback:
     * az archiv retegrol) streamelve kerulnek bele.
     */
    public function zip(Request $request, string $token): StreamedResponse
    {
        $order = Order::query()->where('download_token', $token)->firstOrFail();

        abort_unless($order->isPaid() && $order->isTokenValid(), 410);

        $forensicOn = ForensicWatermark::enabled();
        $forensic = app(ForensicWatermark::class);

        $tmpPath = tempnam(sys_get_temp_dir(), 'order_zip_');

        $zip = new \ZipArchive;
        $zip->open($tmpPath, \ZipArchive::OVERWRITE);

        foreach ($order->media as $media) {
            foreach ($this->fulfillment->formatsFor($media) as $format) {
                $source = $this->fulfillment->resolveSource($order, $media, $format);
                if (! $source) {
                    continue;
                }

                [$disk, $key] = $source;
                $contents = Storage::disk($disk)->get($key);
                if ($contents === null) {
                    continue;
                }

                if ($forensicOn && $media->isPhoto() && in_array($format, ['jpeg', 'webp'], true)) {
                    $contents = $forensic->embed($contents, $order->id, $media->id);
                    $this->logFingerprint($order, $media, $format, $request);
                }

                $zip->addFromString($this->downloadFilename($media, $format), $contents);
            }
        }

        $zip->close();

        return response()->streamDownload(function () use ($tmpPath) {
            echo file_get_contents($tmpPath);
            unlink($tmpPath);
        }, app(SiteBranding::class)->slug()."-rendeles-{$order->id}.zip", ['Content-Type' => 'application/zip']);
    }

    private function downloadFilename(Media $media, string $format): string
    {
        $extension = $format === 'jpeg' ? 'jpg' : $format;

        return app(SiteBranding::class)->slug()."-{$media->id}.{$extension}";
    }
}
