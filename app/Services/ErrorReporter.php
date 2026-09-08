<?php

namespace App\Services;

use App\Mail\ErrorNotificationMail;
use App\Models\ErrorEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Kezeletlen kivételek rögzítése + értesítés — külső szolgáltatás nélkül.
 * A bootstrap/app.php `report()` hívja minden be nem kapott kivételnél.
 * Sose dob (a hibakezelőben fut): minden külső hívás try/catch-elve.
 */
class ErrorReporter
{
    public function __construct(private MonitoringSettings $settings) {}

    public function report(Throwable $e): void
    {
        try {
            if ($this->shouldIgnore($e)) {
                return;
            }

            $fingerprint = hash('sha256', $e::class.'|'.$e->getFile().'|'.$e->getLine());

            $event = ErrorEvent::query()->firstOrNew(['fingerprint' => $fingerprint]);
            $isNew = ! $event->exists;

            $event->fill([
                'exception_class' => $e::class,
                'message' => Str::limit($e->getMessage(), 2000, ''),
                'file' => Str::after($e->getFile(), base_path().DIRECTORY_SEPARATOR) ?: $e->getFile(),
                'line' => $e->getLine(),
                'url' => rescue(fn () => request()?->fullUrl(), null, false),
                'method' => rescue(fn () => request()?->method(), null, false),
                'last_seen_at' => now(),
            ]);

            if ($isNew) {
                $event->first_seen_at = now();
                $event->count = 1;
            } else {
                $event->count++;
                $event->resolved_at = null; // újra előjött → nyisd meg
            }

            $event->save();

            $this->maybeNotify($event, $fingerprint, $isNew);
        } catch (Throwable) {
            // A hibakövető maga sose bukhat el zajosan — a Laravel logger úgyis rögzíti az eredetit.
        }
    }

    private function shouldIgnore(Throwable $e): bool
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() < 500;
        }

        foreach ([
            ValidationException::class,
            AuthenticationException::class,
            AuthorizationException::class,
            TokenMismatchException::class,
            ThrottleRequestsException::class,
            ModelNotFoundException::class,
        ] as $ignored) {
            if ($e instanceof $ignored) {
                return true;
            }
        }

        return false;
    }

    private function maybeNotify(ErrorEvent $event, string $fingerprint, bool $isNew): void
    {
        $cacheKey = "error_notified:{$fingerprint}";

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addMinutes((int) config('monitoring.error_cooldown_minutes', 30)));

        if ($this->settings->notifyEmail()) {
            rescue(function () use ($event, $isNew) {
                User::query()->where('role', User::ROLE_SUPERADMIN)->pluck('email')
                    ->each(fn ($email) => Mail::to($email)->send(new ErrorNotificationMail($event, $isNew)));
            }, null, false);
        }

        if ($url = $this->settings->webhookUrl()) {
            rescue(fn () => Http::timeout(5)->post($url, [
                'text' => sprintf(
                    "[%s] %s\n%s\n%s (%dx)\n%s",
                    config('app.name'),
                    $isNew ? 'ÚJ HIBA' : 'ismétlődő hiba',
                    $event->exception_class,
                    Str::limit($event->message, 300),
                    $event->count,
                    $event->url ?? '—',
                ),
            ]), null, false);
        }
    }
}
