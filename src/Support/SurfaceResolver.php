<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Support;

use Illuminate\Http\Request;

final class SurfaceResolver
{
    public static function resolve(Request $request): string
    {
        $forced = config('rbac.surface.default');
        if (is_string($forced) && trim($forced) !== '') {
            return trim($forced);
        }

        $path = '/'.ltrim($request->path(), '/');

        if (str_contains($path, '/admin')) {
            return 'admin_panel';
        }

        return 'customer_app';
    }
}

