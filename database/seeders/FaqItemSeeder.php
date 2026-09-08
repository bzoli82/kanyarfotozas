<?php

namespace Database\Seeders;

use App\Models\FaqItem;
use Illuminate\Database\Seeder;

class FaqItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'question_hu' => 'Hogyan találom meg a képem?',
                'answer_hu' => 'Keress rá a helyszínre, dátumra és időpontra a főoldali keresőben, vagy böngéssz az esemény galériában.',
                'category' => 'general',
                'is_homepage' => true,
            ],
            [
                'question_hu' => 'Milyen formátumban kapom a képet?',
                'answer_hu' => 'Vásárlás után JPEG és WebP formátumban is letöltheted, vízjel nélkül, eredeti felbontásban.',
                'category' => 'download',
                'is_homepage' => true,
            ],
            [
                'question_hu' => 'Látható a rendszámom az előnézetben?',
                'answer_hu' => 'Nem — a nyilvános előnézetekben a felismert rendszámokat automatikusan elhomályosítjuk.',
                'category' => 'general',
                'is_homepage' => true,
            ],
            [
                'question_hu' => 'Van lehetőség visszatérítésre?',
                'answer_hu' => 'A megvásárolt digitális tartalom jellegéből adódóan alapesetben nincs visszatérítés — ezért ajánlott az ingyenes előnézetet alaposan megnézni vásárlás előtt.',
                'category' => 'payment',
                'is_homepage' => true,
            ],
            [
                'question_hu' => 'Meddig érhető el a letöltési link?',
                'answer_hu' => 'A letöltési link a fizetéstől számított 72 óráig aktív, és maximum 5 alkalommal használható.',
                'category' => 'download',
                'is_homepage' => true,
            ],
            [
                'question_hu' => 'Van videó a képek mellett?',
                'answer_hu' => 'Igen, sok eseményen videó is készül — a galériában szűrhetsz kép/videó típus szerint.',
                'category' => 'video',
                'is_homepage' => false,
            ],
            [
                'question_hu' => 'Kell regisztrálnom a vásárláshoz?',
                'answer_hu' => 'Nem. Elég az e-mail-címed és egy bankkártya; a letöltési linket e-mailben küldjük, és a „Korábbi vásárlásaim" oldalon később is elérheted.',
                'category' => 'payment',
                'is_homepage' => false,
            ],
            [
                'question_hu' => 'Mennyibe kerül egy kép vagy videó?',
                'answer_hu' => 'Az árat eseményenként a szervező / a fotós határozza meg — külön ár a fotókra és külön a videókra. A pontos árat mindig az adott esemény galériájában és a kosárban látod vásárlás előtt.',
                'category' => 'payment',
                'is_homepage' => false,
            ],
            [
                'question_hu' => 'Milyen fizetési módokat fogadtok el?',
                'answer_hu' => 'Bankkártyás fizetés Stripe-on vagy SimplePay-en keresztül. A sikeres fizetésről automatikus számlát is kiállítunk.',
                'category' => 'payment',
                'is_homepage' => false,
            ],
            [
                'question_hu' => 'Fotós vagyok — hogyan árulhatom itt a képeimet?',
                'answer_hu' => 'Küldj üzenetet a kapcsolat aloldalon. Ezután felvesszük veled a kapcsolatot. Együttműködő partnereink feltöltik a saját anyagaikat, de a rendszer intézi a vízjelezést, és a webshopot. Te az eladás előre megbeszélt százalékát kapod.',
                'category' => 'general',
                'is_homepage' => false,
            ],
            [
                'question_hu' => 'Esemény-szervező vagyok — van bevétel-megosztás?',
                'answer_hu' => 'Igen. A hozzád rendelt esemény bevételéből előre megállapodott részesedést kaphatsz, amit egy saját, csak-olvasható portálon követhetsz nyomon.',
                'category' => 'general',
                'is_homepage' => false,
            ],
        ];

        foreach ($items as $index => $item) {
            FaqItem::query()->create([
                ...$item,
                'sort_order' => $index + 1,
                'active' => true,
            ]);
        }
    }
}
