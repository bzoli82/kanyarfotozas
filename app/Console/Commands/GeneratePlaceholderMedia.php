<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\PlaceholderMediaGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('roadsidephoto:generate-placeholder-media {--force : Meglévő fájlok felülírása is}')]
#[Description('Demo/fejlesztői placeholder képek + rövid videók generálása minden Media rekordhoz')]
class GeneratePlaceholderMedia extends Command
{
    public function handle(PlaceholderMediaGenerator $generator): int
    {
        $force = (bool) $this->option('force');
        $media = Media::query()->with('event')->get();

        if ($media->isEmpty()) {
            $this->warn('Nincs Media rekord — előbb futtasd a DemoDataSeeder-t.');

            return self::SUCCESS;
        }

        $this->withProgressBar($media, function (Media $item) use ($generator, $force) {
            try {
                $generator->generateForMedia($item, $force);
            } catch (Throwable $e) {
                $this->newLine();
                $this->warn("Media #{$item->id} kihagyva: {$e->getMessage()}");
            }
        });

        $this->newLine(2);

        $hero = $generator->generateHeroSlides();
        if ($hero !== []) {
            $this->info(count($hero).' demo hero kép generálva a főoldalhoz.');
        }

        $this->info('Placeholder média generálva. Ha még nincs, futtasd: php artisan storage:link');

        return self::SUCCESS;
    }
}
