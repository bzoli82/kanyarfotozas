<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Publikus űrlapok (Kapcsolat, „Kérdés a fotóshoz") spam-védelme — **külső
 * szolgáltatás nélkül**, több réteggel:
 *
 *   1. Time-trap — a challenge-token aláírt kiadási időbélyeget hordoz; a
 *      `min_fill_seconds`-nél gyorsabb beküldés bot (ember lassabban tölt ki).
 *   2. Számtani kérdés — a látogató kézzel válaszol (a token aláírja a helyes
 *      választ, a kliens nem tudja meghamisítani).
 *   3. Proof-of-work — a böngésző egy hashcash puzzle-t old meg (SHA-256,
 *      `pow_bits` vezető nulla-bit); láthatatlan (~1 s), de a tömeges automata
 *      beküldést számításigényessé teszi.
 *   4. Replay-védelem — egy megoldott token csak egyszer használható (cache).
 *
 * A honeypot-ellenőrzés külön (`isBot()`) — arra a hívó „csendben sikeres"
 * választ ad, hogy a botnak ne adjunk visszajelzést.
 *
 * A challenge STATELESS: a token = base64url(payload).base64url(HMAC-SHA256).
 */
class FormGuard
{
    /** @var list<string> honeypot mezőnevek — ember egyiket sem tölti ki */
    public const HONEYPOTS = ['website', 'nickname'];

    /**
     * Új challenge a nézetnek: `{ token, question, pow: { salt, bits } }`.
     *
     * @return array{token: string, question: string, pow: array{salt: string, bits: int}}
     */
    public function issue(): array
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        $salt = bin2hex(random_bytes(8));
        $bits = $this->powBits();

        $payload = [
            't' => now()->timestamp,
            'a' => $a,
            'b' => $b,
            's' => $salt,
            'd' => $bits,
            'n' => Str::random(16),
        ];

        return [
            'token' => $this->sign($payload),
            'question' => "Mennyi {$a} + {$b}?",
            'pow' => ['salt' => $salt, 'bits' => $bits],
        ];
    }

    /**
     * A honeypot mezők bármelyike ki van töltve → bot.
     */
    public function isBot(Request $request): bool
    {
        foreach (self::HONEYPOTS as $field) {
            if (filled($request->input($field))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Teljes ellenőrzés. Hiba esetén `ValidationException` (`guard` /
     * `guard_answer` kulcson).
     *
     * @param  bool  $skipArithmetic  ha a számtani kérdést egy másik réteg (hCaptcha) váltja
     *
     * @throws ValidationException
     */
    public function verify(Request $request, bool $skipArithmetic = false): void
    {
        $payload = $this->decode((string) $request->input('guard_token'));

        if ($payload === null) {
            $this->fail('tampered');
        }

        $age = now()->timestamp - (int) $payload['t'];

        if ($age < (int) config('formguard.min_fill_seconds', 3)) {
            $this->fail('too_fast');
        }

        if ($age > (int) config('formguard.max_age_seconds', 7200)) {
            $this->fail('expired');
        }

        if (! $skipArithmetic && (int) $request->input('guard_answer') !== (int) $payload['a'] + (int) $payload['b']) {
            $this->fail('wrong_answer', 'guard_answer');
        }

        if (! $this->powValid((string) $payload['s'], (int) $payload['d'], (int) $request->input('guard_pow'))) {
            $this->fail('pow_failed');
        }

        $usedKey = 'formguard:used:'.$payload['n'];

        if (Cache::has($usedKey)) {
            $this->fail('replay');
        }

        Cache::put($usedKey, true, (int) config('formguard.max_age_seconds', 7200));
    }

    /**
     * A proof-of-work megoldása (PHP-ben) — tesztekhez / dokumentációhoz.
     */
    public function solveProofOfWork(string $salt, int $bits): int
    {
        for ($i = 0; $i < 50_000_000; $i++) {
            if ($this->leadingZeroBits(hash('sha256', $salt.$i, true)) >= $bits) {
                return $i;
            }
        }

        throw new RuntimeException('A proof-of-work nem oldható meg (túl magas nehézség).');
    }

    private function powBits(): int
    {
        return max(1, min(28, (int) config('formguard.pow_bits', 17)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sign(array $payload): string
    {
        $json = $this->b64url((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $json.'.'.$this->hmac($json);
    }

    private function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @return array{t: int, a: int, b: int, s: string, d: int, n: string}|null
     */
    private function decode(string $token): ?array
    {
        if (! str_contains($token, '.')) {
            return null;
        }

        [$json, $sig] = explode('.', $token, 2);

        if (! hash_equals($this->hmac($json), $sig)) {
            return null;
        }

        $decoded = json_decode((string) base64_decode(strtr($json, '-_', '+/')), true);

        if (! is_array($decoded) || ! isset($decoded['t'], $decoded['a'], $decoded['b'], $decoded['s'], $decoded['d'], $decoded['n'])) {
            return null;
        }

        return $decoded;
    }

    private function hmac(string $data): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, (string) config('app.key'), true)), '+/', '-_'), '=');
    }

    private function powValid(string $salt, int $bits, int $solution): bool
    {
        if ($solution < 0 || $bits < 1 || $bits > 28) {
            return false;
        }

        return $this->leadingZeroBits(hash('sha256', $salt.$solution, true)) >= $bits;
    }

    private function leadingZeroBits(string $bytes): int
    {
        $count = 0;

        foreach (str_split($bytes) as $ch) {
            $o = ord($ch);

            if ($o === 0) {
                $count += 8;

                continue;
            }

            return $count + (8 - strlen(decbin($o)));
        }

        return $count;
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $key, string $field = 'guard'): never
    {
        throw ValidationException::withMessages([$field => __("formguard.$key")]);
    }
}
