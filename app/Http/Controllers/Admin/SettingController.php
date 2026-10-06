<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Strictly read-only display of config('permit.*'). It never writes to .env or
 * config files from an HTTP request — environment knobs are changed only by an
 * operator editing .env on the server.
 */
class SettingController extends Controller
{
    public function show(): View
    {
        return view('admin.settings', [
            'permit' => config('permit'),
        ]);
    }
}
