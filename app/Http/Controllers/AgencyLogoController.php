<?php

namespace App\Http\Controllers;

use App\Support\AgencyLogo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** #176: serves an agency logo by its random file name; SVGs are sandboxed so they can't run scripts on our domain. */
class AgencyLogoController extends Controller
{
    public function show(string $file): Response
    {
        $path = AgencyLogo::DIRECTORY.'/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $svg = str_ends_with($file, '.svg');

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => $svg ? 'image/svg+xml' : 'image/png',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
