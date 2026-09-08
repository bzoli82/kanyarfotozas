<?php

namespace App\Console\Commands;

use App\Services\ThemeSettings;
use Illuminate\Console\Command;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;

/**
 * A fotós mobil PWA (EPIC-15) ikonjait generalja a `public/` alá — az admin
 * accent szinbol + "KF" monogrammal. Egyszeri futtatas eleg (a fajlok verziozva
 * commitolhatok), de a parancs barmikor ujragyarthatja oket.
 */
class GeneratePwaIcons extends Command
{
    protected $signature = 'kanyarfotozas:generate-pwa-icons';

    protected $description = 'A mobil PWA ikonok (192/512/maskable/apple-touch) legyartasa a public/ alá';

    public function handle(ThemeSettings $theme): int
    {
        $manager = new ImageManager(new Driver);
        $font = resource_path('fonts/Arimo-Bold.ttf');
        $accent = $theme->accentColor();

        $variants = [
            // [fajlnev, meret, "KF" szoveg relativ merete] — a maskable-nel kisebb, hogy
            // a maszkolas ne vagja le.
            ['pwa-192.png', 192, 0.44],
            ['pwa-512.png', 512, 0.44],
            ['pwa-maskable-512.png', 512, 0.32],
            ['apple-touch-icon.png', 180, 0.44],
        ];

        foreach ($variants as [$name, $size, $textScale]) {
            $img = $manager->createImage($size, $size)->fill($accent);

            $img->text('KF', (int) ($size / 2), (int) ($size / 2), function ($f) use ($font, $size, $textScale) {
                $f->filename($font);
                $f->size((int) round($size * $textScale));
                $f->color('#ffffff');
                $f->align('center', 'center');
            });

            $path = public_path($name);
            file_put_contents($path, (string) $img->encode(new PngEncoder));
            $this->info("✓ {$name}");
        }

        return self::SUCCESS;
    }
}
