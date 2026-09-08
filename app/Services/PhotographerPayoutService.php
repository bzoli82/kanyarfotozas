<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PhotographerEarning;
use App\Models\PhotographerPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fotós kifizetés-elszámolás. Minden kifizetett rendelés minden olyan tétele,
 * amelynek van fotósa, egy `PhotographerEarning` sort kap (jutalék = az eladási
 * ár × a fotós aktuális jutalék %-a, snapshot-olva). Az admin ezekből képez
 * kifizetéseket (`PhotographerPayout`): a nyitott (pending) tételek egy
 * bizonylatba kerülnek, majd a tényleges utaláskor „paid" lesz mindkettő.
 */
class PhotographerPayoutService
{
    /** Választható kifizetési módok (admin űrlap). */
    public const METHODS = ['banki átutalás', 'revolut', 'wise', 'készpénz', 'egyéb'];

    /**
     * A fotós teljes elszámolási panelje az admin fotós-részletnézethez
     * (nyitott egyenleg + főkönyv + bizonylatok).
     *
     * @return array{outstanding: array<string, mixed>, ledger: list<array<string, mixed>>, history: list<array<string, mixed>>, methods: list<string>}
     */
    public function panelFor(User $photographer): array
    {
        $o = $this->outstanding($photographer);

        $ledger = $this->ledger($photographer)->limit(200)->get()->map(fn (PhotographerEarning $e) => [
            'id' => $e->id,
            'earned_at' => $e->earned_at?->toIso8601String(),
            'order_number' => $e->order?->order_number,
            'event' => $e->media?->event?->name,
            'media_id' => $e->media_id,
            'media_type' => $e->media?->type,
            'gross_cents' => $e->gross_cents,
            'share_percent' => $e->share_percent,
            'amount_cents' => $e->amount_cents,
            'status' => $e->status,
            'payout_id' => $e->payout_id,
        ])->values()->all();

        $history = PhotographerPayout::query()
            ->where('photographer_id', $photographer->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PhotographerPayout $p) => [
                'id' => $p->id,
                'payout_number' => $p->payout_number,
                'status' => $p->status,
                'amount_cents' => $p->amount_cents,
                'media_count' => $p->media_count,
                'method' => $p->method,
                'reference' => $p->reference,
                'note' => $p->note,
                'period_start' => $p->period_start?->toDateString(),
                'period_end' => $p->period_end?->toDateString(),
                'paid_at' => $p->paid_at?->toIso8601String(),
            ])->values()->all();

        return [
            'outstanding' => [
                'amount_cents' => $o['amount_cents'],
                'gross_cents' => $o['gross_cents'],
                'media_count' => $o['media_count'],
                'oldest_at' => $o['oldest_at']?->toIso8601String(),
            ],
            'ledger' => $ledger,
            'history' => $history,
            'methods' => self::METHODS,
        ];
    }

    /**
     * Egy frissen kifizetett rendelés fotós-jutalékainak rögzítése. Idempotens:
     * ugyanarra az `order_media` sorra nem hoz létre második tételt.
     */
    public function recordForOrder(Order $order): void
    {
        if (! $order->isPaid()) {
            return;
        }

        $order->loadMissing('media');

        foreach ($order->media as $media) {
            if (blank($media->photographer_id)) {
                continue;
            }

            $orderMediaId = (int) $media->pivot->id;

            if (PhotographerEarning::query()->where('order_media_id', $orderMediaId)->exists()) {
                continue;
            }

            $gross = (int) $media->pivot->price_cents;
            $share = (int) (User::query()->whereKey($media->photographer_id)->value('revenue_share_percent') ?? 0);

            PhotographerEarning::create([
                'photographer_id' => $media->photographer_id,
                'order_id' => $order->id,
                'media_id' => $media->id,
                'order_media_id' => $orderMediaId,
                'gross_cents' => $gross,
                'share_percent' => $share,
                'amount_cents' => (int) round($gross * $share / 100),
                'status' => PhotographerEarning::STATUS_PENDING,
                'earned_at' => now(),
            ]);
        }
    }

    /**
     * Teljes visszatérítéskor: a rendelés még ki nem fizetett (pending) jutalékait
     * érvényteleníti. A már kifizetett tételeket nem bántja — azt az admin
     * a következő elszámolásnál kézzel korrigálja (a bizonylaton látszik).
     */
    public function reverseForOrder(Order $order): void
    {
        PhotographerEarning::query()
            ->where('order_id', $order->id)
            ->where('status', PhotographerEarning::STATUS_PENDING)
            ->update([
                'status' => PhotographerEarning::STATUS_REVERSED,
                'reversed_at' => now(),
            ]);
    }

    /**
     * A még ki nem fizetett (pending, bizonylathoz nem rendelt) jutalékok
     * összesítője egy fotósra.
     *
     * @return array{amount_cents: int, gross_cents: int, media_count: int, oldest_at: ?Carbon}
     */
    public function outstanding(User $photographer): array
    {
        $row = $this->pendingQuery($photographer)
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as amount, COALESCE(SUM(gross_cents), 0) as gross, COUNT(*) as cnt, MIN(earned_at) as oldest')
            ->first();

        return [
            'amount_cents' => (int) ($row->amount ?? 0),
            'gross_cents' => (int) ($row->gross ?? 0),
            'media_count' => (int) ($row->cnt ?? 0),
            'oldest_at' => $row?->oldest ? Carbon::parse($row->oldest) : null,
        ];
    }

    /**
     * Minden fotós elszámolási állapota az admin listához.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summary(): Collection
    {
        $photographers = User::query()
            ->where('role', User::ROLE_PHOTOGRAPHER)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'revenue_share_percent']);

        $pending = PhotographerEarning::query()
            ->where('status', PhotographerEarning::STATUS_PENDING)
            ->whereNull('payout_id')
            ->groupBy('photographer_id')
            ->selectRaw('photographer_id, SUM(amount_cents) as amount, COUNT(*) as cnt, MIN(earned_at) as oldest')
            ->get()
            ->keyBy('photographer_id');

        $paid = PhotographerPayout::query()
            ->where('status', PhotographerPayout::STATUS_PAID)
            ->groupBy('photographer_id')
            ->selectRaw('photographer_id, SUM(amount_cents) as amount, MAX(paid_at) as last_paid')
            ->get()
            ->keyBy('photographer_id');

        return $photographers->map(function (User $p) use ($pending, $paid) {
            $pd = $pending->get($p->id);
            $pa = $paid->get($p->id);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'is_active' => (bool) $p->is_active,
                'revenue_share_percent' => (int) $p->revenue_share_percent,
                'outstanding_cents' => (int) ($pd->amount ?? 0),
                'outstanding_count' => (int) ($pd->cnt ?? 0),
                'oldest_pending_at' => $pd?->oldest ? Carbon::parse($pd->oldest)->toIso8601String() : null,
                'total_paid_cents' => (int) ($pa->amount ?? 0),
                'last_paid_at' => $pa?->last_paid ? Carbon::parse($pa->last_paid)->toIso8601String() : null,
            ];
        })->values();
    }

    /**
     * A pending jutalékokból új, „draft" állapotú kifizetési bizonylat.
     *
     * @param  array{method?: ?string, reference?: ?string, note?: ?string}  $meta
     *
     * @throws ValidationException ha nincs elszámolható tétel
     */
    public function createDraft(User $photographer, ?Carbon $until = null, array $meta = []): PhotographerPayout
    {
        return DB::transaction(function () use ($photographer, $until, $meta) {
            $earnings = $this->pendingQuery($photographer)
                ->when($until, fn ($q) => $q->where('earned_at', '<=', $until))
                ->lockForUpdate()
                ->get();

            if ($earnings->isEmpty()) {
                throw ValidationException::withMessages([
                    'payout' => 'Nincs elszámolható (nyitott) jutalék ehhez a fotóshoz.',
                ]);
            }

            $payout = PhotographerPayout::create([
                'photographer_id' => $photographer->id,
                'period_start' => $earnings->min('earned_at'),
                'period_end' => $until ?? $earnings->max('earned_at'),
                'gross_cents' => (int) $earnings->sum('gross_cents'),
                'amount_cents' => (int) $earnings->sum('amount_cents'),
                'media_count' => $earnings->count(),
                'status' => PhotographerPayout::STATUS_DRAFT,
                'method' => $meta['method'] ?? null,
                'reference' => $meta['reference'] ?? null,
                'note' => $meta['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            PhotographerEarning::query()
                ->whereIn('id', $earnings->pluck('id'))
                ->update(['payout_id' => $payout->id]);

            return $payout->refresh();
        });
    }

    /**
     * A bizonylat „paid" jelölése + a hozzá tartozó jutalékok kifizetettre állítása.
     *
     * @param  array{method?: ?string, reference?: ?string, note?: ?string, paid_at?: ?string}  $meta
     */
    public function markPaid(PhotographerPayout $payout, array $meta = []): void
    {
        if ($payout->isPaid()) {
            return;
        }

        DB::transaction(function () use ($payout, $meta) {
            $payout->update([
                'status' => PhotographerPayout::STATUS_PAID,
                'method' => $meta['method'] ?? $payout->method,
                'reference' => $meta['reference'] ?? $payout->reference,
                'note' => $meta['note'] ?? $payout->note,
                'paid_at' => filled($meta['paid_at'] ?? null) ? Carbon::parse($meta['paid_at']) : now(),
            ]);

            $payout->earnings()->update(['status' => PhotographerEarning::STATUS_PAID]);
        });
    }

    /**
     * Draft bizonylat visszavonása — a jutalékok visszakerülnek a nyitott sorba.
     */
    public function deleteDraft(PhotographerPayout $payout): void
    {
        if ($payout->isPaid()) {
            throw ValidationException::withMessages(['payout' => 'Kifizetett bizonylat nem törölhető.']);
        }

        DB::transaction(function () use ($payout) {
            $payout->earnings()->update(['payout_id' => null]);
            $payout->delete();
        });
    }

    /**
     * @return Builder<PhotographerEarning>
     */
    private function pendingQuery(User $photographer): Builder
    {
        return PhotographerEarning::query()
            ->where('photographer_id', $photographer->id)
            ->where('status', PhotographerEarning::STATUS_PENDING)
            ->whereNull('payout_id');
    }

    /**
     * A fotós elszámolási főkönyve (tételes lista) az admin részletnézethez.
     *
     * @return Builder<PhotographerEarning>
     */
    public function ledger(User $photographer): Builder
    {
        return PhotographerEarning::query()
            ->where('photographer_id', $photographer->id)
            ->with(['order:id,order_number', 'media:id,type,event_id', 'media.event:id,name'])
            ->orderByDesc('earned_at')
            ->orderByDesc('id');
    }

    public function ledgerCsv(User $photographer): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Dátum', 'Rendelés', 'Esemény', 'Média ID', 'Típus', 'Bruttó ár (Ft)', 'Jutalék %', 'Jutalék (Ft)', 'Állapot', 'Bizonylat']);

        foreach ($this->ledger($photographer)->get() as $earning) {
            fputcsv($handle, [
                $earning->earned_at?->format('Y-m-d H:i'),
                $earning->order?->order_number,
                $earning->media?->event?->name,
                $earning->media_id,
                $earning->media?->type === 'video' ? 'Videó' : 'Kép',
                $earning->gross_cents,
                $earning->share_percent,
                $earning->amount_cents,
                $earning->status,
                $earning->payout_id ? '#'.$earning->payout_id : '',
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Rendszer-szintű összegzés a Kritikus beállítások állapotjelzőjéhez.
     *
     * @return array{outstanding_cents: int, photographers_with_balance: int, oldest_pending_at: ?Carbon}
     */
    public function globalOutstanding(): array
    {
        $row = PhotographerEarning::query()
            ->where('status', PhotographerEarning::STATUS_PENDING)
            ->whereNull('payout_id')
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as amount, COUNT(DISTINCT photographer_id) as photographers, MIN(earned_at) as oldest')
            ->first();

        return [
            'outstanding_cents' => (int) ($row->amount ?? 0),
            'photographers_with_balance' => (int) ($row->photographers ?? 0),
            'oldest_pending_at' => $row?->oldest ? Carbon::parse($row->oldest) : null,
        ];
    }
}
