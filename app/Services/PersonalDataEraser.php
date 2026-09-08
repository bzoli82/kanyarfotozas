<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Egy e-mail cím személyes adatainak törlése / anonimizálása (GDPR 17. cikk).
 *
 * A rendelések NEM törlődnek (számviteli megőrzési kötelezettség), de a
 * `buyer_email` anonim tokenre cserélődik és a letöltési link lejár. A
 * kifejezetten személyes segéd-rekordok (kapcsolati üzenet, esemény-feliratkozás,
 * OTP) törlődnek.
 *
 * @return array<string, int> mit mennyit érintett
 */
class PersonalDataEraser
{
    /**
     * @return array<string, int>
     */
    public function eraseEmail(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $anon = 'torolt-'.substr(sha1($email.config('app.key')), 0, 12).'@torolt.invalid';
        $emailHash = hash('sha256', $email);

        return DB::transaction(function () use ($email, $anon, $emailHash) {
            $orders = Order::query()->whereRaw('LOWER(buyer_email) = ?', [$email])->get();
            foreach ($orders as $order) {
                $order->forceFill([
                    'buyer_email' => $anon,
                    'download_token' => null,
                    'token_expires_at' => now(),
                    'refund_reason' => null,
                ])->save();
            }

            $contact = DB::table('contact_messages')->whereRaw('LOWER(email) = ?', [$email])->delete();
            $subs = DB::table('event_subscriptions')->whereRaw('LOWER(email) = ?', [$email])->delete();
            $otps = DB::table('purchase_otps')->where('email_hash', $emailHash)->delete();

            return [
                'orders_anonymised' => $orders->count(),
                'contact_messages_deleted' => (int) $contact,
                'event_subscriptions_deleted' => (int) $subs,
                'purchase_otps_deleted' => (int) $otps,
            ];
        });
    }
}
