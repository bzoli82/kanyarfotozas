<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\WebScheduler;
use Illuminate\Http\Response;

/**
 * A „webes ütemező" végpontja — egy külső cron szolgáltatás (cron-job.org stb.)
 * percenként meghívja a titkos URL-t, ez elindítja a soron következő ütemezett
 * feladatokat. Stateless (routes/api.php), nincs session/CSRF.
 */
class WebSchedulerController extends Controller
{
    public function __invoke(string $token, WebScheduler $scheduler): Response
    {
        abort_unless($scheduler->enabled(), 404);
        abort_unless($scheduler->tokenMatches($token), 404);

        $result = $scheduler->tick();

        return response(
            $result === 'dispatched'
                ? "OK — utemezett feladatok elinditva\n"
                : "OK — kihagyva (tul suru hivas)\n",
            200,
        )->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
