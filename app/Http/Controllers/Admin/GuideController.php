<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminNavigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * „Admin súgó" — a csapat-felület minden menüpontja egy helyen, rövid
 * magyarázattal. Ugyanabból a forrásból (App\Support\AdminNavigation) épül,
 * mint az oldalsáv, így a leírások nem duplázódnak.
 */
class GuideController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Guide', [
            'sections' => AdminNavigation::sections($request->user()),
        ]);
    }
}
