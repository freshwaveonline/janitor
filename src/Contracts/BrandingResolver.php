<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Contracts;

use FreshwaveOnline\Janitor\Data\Branding;
use Illuminate\Http\Request;

/**
 * Decides whose application the visitor is looking at.
 *
 * The default implementation reads the package config and, when Filament is
 * installed, the active panel. A multi-tenant application binds its own:
 *
 *     class TenantBranding implements BrandingResolver
 *     {
 *         public function resolve(Request $request, int $statusCode): Branding
 *         {
 *             $tenant = Tenant::forHost($request->getHost());
 *
 *             return new Branding(
 *                 name: $tenant?->name,
 *                 logo: $tenant?->logo_url,
 *                 primaryColor: $tenant?->brand_colour,
 *                 homeUrl: $tenant?->url,
 *                 supportEmail: $statusCode >= 500 ? $tenant?->support_email : null,
 *             );
 *         }
 *     }
 *
 * This runs while an exception is being rendered, so it must not throw and must
 * not assume the database is reachable — that may be exactly what failed.
 */
interface BrandingResolver
{
    /**
     * @param  int  $statusCode  Lets the implementation decide per status whether
     *                           to offer a support address at all.
     */
    public function resolve(Request $request, int $statusCode): Branding;
}
