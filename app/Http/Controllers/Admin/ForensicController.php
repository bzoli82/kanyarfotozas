<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DownloadFingerprint;
use App\Models\Order;
use App\Services\DashboardStatsService;
use App\Services\ForensicWatermark;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kép-visszakövetés: egy (feltehetően kiszivárgott) fotóból visszafejti, melyik
 * rendelésből — és így melyik vásárlótól — származik. A jelet a
 * `ForensicWatermark` teszi minden megvásárolt letöltésbe.
 */
class ForensicController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Forensics/Index', [
            'enabled' => ForensicWatermark::enabled(),
            'result' => null,
        ]);
    }

    public function identify(Request $request, ForensicWatermark $forensic, DashboardStatsService $stats): Response
    {
        $request->validate([
            'image' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,webp,png'],
        ]);

        $mark = $forensic->read((string) file_get_contents($request->file('image')->getRealPath()));

        $result = ['matched' => false];

        if ($mark !== null) {
            $order = Order::query()->with(['media' => fn ($q) => $q->where('media.id', $mark['media_id'])->with('event:id,name')])->find($mark['order_id']);

            if ($order) {
                $media = $order->media->firstWhere('id', $mark['media_id']);

                $result = [
                    'matched' => true,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'buyer_email_masked' => $stats->maskEmail($order->buyer_email),
                    'buyer_email' => $order->buyer_email,
                    'purchased_at' => $order->created_at?->toIso8601String(),
                    'payment_status' => $order->payment_status,
                    'issued_at' => CarbonImmutable::createFromTimestamp($mark['issued_at'])->toIso8601String(),
                    'event' => $media?->event?->name,
                    'downloads' => DownloadFingerprint::query()
                        ->where('order_id', $mark['order_id'])
                        ->where('media_id', $mark['media_id'])
                        ->orderByDesc('created_at')
                        ->limit(20)
                        ->get(['format', 'created_at'])
                        ->map(fn (DownloadFingerprint $f) => [
                            'format' => $f->format,
                            'at' => $f->created_at?->toIso8601String(),
                        ]),
                ];
            } else {
                // A jel érvényes és aláírt, de a rendelés már törölve lett.
                $result = ['matched' => true, 'order_id' => $mark['order_id'], 'order_number' => null, 'orphaned' => true];
            }
        }

        return Inertia::render('Admin/Forensics/Index', [
            'enabled' => ForensicWatermark::enabled(),
            'result' => $result,
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        ForensicWatermark::setEnabled($request->boolean('enabled'));

        return back()->with('success', 'Beállítás mentve.');
    }
}
