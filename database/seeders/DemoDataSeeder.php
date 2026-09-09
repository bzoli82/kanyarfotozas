<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Event;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\PlaceholderMediaGenerator;
use App\Services\SiteBranding;
use App\Services\SocialLinks;
use Illuminate\Database\Seeder;
use Throwable;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // A rendszer-fiókok domainje a beállított végleges domain (vagy semleges placeholder),
        // hogy a demó adat SE tartalmazzon a régi márkanévre utaló e-mailt.
        $mailDomain = app(SiteBranding::class)->mailDomain();

        $superadmin = User::query()->updateOrCreate(
            ['email' => "superadmin@{$mailDomain}"],
            [
                'name' => 'Superadmin',
                'password' => 'password',
                'role' => User::ROLE_SUPERADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $superadmin->assignRole(User::ROLE_SUPERADMIN);

        $admin = User::query()->updateOrCreate(
            ['email' => "admin@{$mailDomain}"],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                // Egy „Admin" nevű koordinátor-fiók ne legyen kint a nyilvános Fotósok oldalon.
                'is_public' => false,
                'bio' => 'A csapat koordinátora — a helyszínek szervezése és a képek gondozása.',
                'email_verified_at' => now(),
            ],
        );
        $admin->assignRole(User::ROLE_ADMIN);

        $photographerProfiles = [
            ['name' => 'Kovács Péter', 'bio' => 'Rali- és túraautó-fókusz. A meredek emelkedők és a technikás szakaszok specialistája, 8 éve a pálya mellett.', 'public_email' => 'peter', 'website' => 'kovacspeterfoto.example', 'social_instagram' => 'https://instagram.com/kovacspeterfoto', 'social_facebook' => 'https://facebook.com/kovacspeterfoto'],
            ['name' => 'Nagy Anna', 'bio' => 'Motorosok a kanyarban, alacsony szögből. A dőlésszög és a fény a szenvedélye.', 'public_email' => 'anna', 'social_instagram' => 'https://instagram.com/annakanyar', 'social_tiktok' => 'https://tiktok.com/@annakanyar'],
            ['name' => 'Tóth Bence', 'bio' => 'Sprint- és hegyi versenyek, nagy telefotó. Szereti az egészen közeli, drámai kompozíciókat.', 'public_email' => 'bence', 'social_youtube' => 'https://youtube.com/@tothbencefoto'],
        ];
        $photographers = collect($photographerProfiles)->map(function (array $profile, int $i) use ($mailDomain) {
            $user = User::query()->updateOrCreate(
                ['email' => 'fotos'.($i + 1)."@{$mailDomain}"],
                [
                    'name' => $profile['name'],
                    'password' => 'password',
                    'role' => User::ROLE_PHOTOGRAPHER,
                    'revenue_share_percent' => 70,
                    'is_active' => true,
                    'is_public' => true,
                    'bio' => $profile['bio'],
                    'public_email' => $profile['public_email']."@{$mailDomain}",
                    'website' => $profile['website'] ?? null,
                    'social_instagram' => $profile['social_instagram'] ?? null,
                    'social_facebook' => $profile['social_facebook'] ?? null,
                    'social_youtube' => $profile['social_youtube'] ?? null,
                    'social_tiktok' => $profile['social_tiktok'] ?? null,
                    'email_verified_at' => now(),
                ],
            );
            $user->assignRole(User::ROLE_PHOTOGRAPHER);

            return $user;
        });

        $hu = Country::query()->where('code', 'HU')->first();
        $at = Country::query()->where('code', 'AT')->first();

        $eventDefs = [
            ['name' => 'Eger-kanyar', 'location' => 'Eger', 'lat' => 47.9025, 'lon' => 20.3772, 'country' => $hu, 'status' => Event::STATUS_LIVE, 'daysAgo' => 3],
            ['name' => 'Mátra Rally', 'location' => 'Mátraháza', 'lat' => 47.8667, 'lon' => 19.9667, 'country' => $hu, 'status' => Event::STATUS_LIVE, 'daysAgo' => 10],
            ['name' => 'Bükk Túra', 'location' => 'Lillafüred', 'lat' => 48.1167, 'lon' => 20.6167, 'country' => $hu, 'status' => Event::STATUS_LIVE, 'daysAgo' => 20],
            ['name' => 'Grossglockner Sprint', 'location' => 'Grossglockner', 'lat' => 47.0742, 'lon' => 12.8306, 'country' => $at, 'status' => Event::STATUS_ANNOUNCED, 'daysAgo' => -14],
        ];

        foreach ($eventDefs as $def) {
            $startsAt = now()->subDays($def['daysAgo'])->setTime(9, 0);

            $event = Event::query()->updateOrCreate(
                ['name' => $def['name'], 'location' => $def['location']],
                [
                    'country_id' => $def['country']->id,
                    'latitude' => $def['lat'],
                    'longitude' => $def['lon'],
                    'event_date' => $startsAt->toDateString(),
                    'starts_at' => $startsAt,
                    'ends_at' => (clone $startsAt)->addHours(6),
                    'status' => $def['status'],
                    'created_by' => $admin->id,
                ],
            );

            if ($def['status'] === Event::STATUS_LIVE) {
                Media::factory()
                    ->photo()
                    ->count(12)
                    ->for($event)
                    ->state(fn () => ['photographer_id' => $photographers->random()->id, 'price_cents' => $event->priceFor(Media::TYPE_PHOTO)])
                    ->create();

                Media::factory()
                    ->video()
                    ->count(3)
                    ->for($event)
                    ->state(fn () => ['photographer_id' => $photographers->random()->id, 'price_cents' => $event->priceFor(Media::TYPE_VIDEO)])
                    ->create();
            }
        }

        // A demóban látszódjon minden fotós-adat (éles alap: contacts KI, attribution BE).
        SiteSetting::set('photographer_contacts_public', '1');
        SiteSetting::set('photographer_attribution_public', '1');

        // Cég szintű közösségi média linkek (a Kapcsolat oldal „Kövess minket" + a lábléc) —
        // demó/placeholder URL-ek, hogy a szekció ne tűnjön el egy friss telepítés után.
        app(SocialLinks::class)->update([
            'facebook' => 'https://facebook.com/roadsidephoto',
            'instagram' => 'https://instagram.com/roadsidephoto',
            'youtube' => 'https://youtube.com/@roadsidephoto',
            'tiktok' => 'https://tiktok.com/@roadsidephoto',
        ]);

        $this->generatePlaceholderImages();

        try {
            app(PlaceholderMediaGenerator::class)->generateHeroSlides();
        } catch (Throwable $e) {
            $this->command?->warn("Demo hero képek kihagyva: {$e->getMessage()}");
        }
    }

    /**
     * Demo galeria feltoltese felismerheto placeholder kepekkel/videokkal.
     * Hiba eseten (pl. hianyzo FFmpeg) csak figyelmeztet — a seed nem all le.
     */
    private function generatePlaceholderImages(): void
    {
        $generator = app(PlaceholderMediaGenerator::class);

        Media::query()->with('event')->get()->each(function (Media $media) use ($generator) {
            try {
                $generator->generateForMedia($media);
            } catch (Throwable $e) {
                $this->command?->warn("Placeholder média kihagyva (#{$media->id}): {$e->getMessage()}");
            }
        });
    }
}
