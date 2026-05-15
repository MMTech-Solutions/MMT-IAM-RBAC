<?php

declare(strict_types=1);

namespace Mmtech\Rbac;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Mmtech\Rbac\Auth\GatewayUser;
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use Mmtech\Rbac\Support\SurfaceResolver;

final class RbacModule
{
    public static function boot(): void
    {
        self::registerRequestMacros();
        self::registerGateBefore();
    }

    private static function registerRequestMacros(): void
    {
        if (! Request::hasMacro('getGatewayAuthUserInfo')) {
            Request::macro('getGatewayAuthUserInfo', function (): ?array {
                /** @var Request $this */
                $value = $this->attributes->get('gateway_auth_user_info');

                return is_array($value) ? $value : null;
            });
        }

        if (! Request::hasMacro('getGatewayAuthSub')) {
            Request::macro('getGatewayAuthSub', function (): ?string {
                /** @var Request $this */
                $info = $this->getGatewayAuthUserInfo();
                if (! is_array($info) || empty($info['sub'])) {
                    return null;
                }

                $sub = trim((string) $info['sub']);

                return $sub !== '' ? $sub : null;
            });
        }

        if (! Request::hasMacro('rbacRoles')) {
            Request::macro('rbacRoles', function (?string $surface = null): array {
                /** @var Request $this */
                $user = $this->user();
                if ($user instanceof GatewayUser) {
                    return $user->rbacRoles($surface);
                }

                return [];
            });
        }

        if (! Request::hasMacro('rbacRole')) {
            Request::macro('rbacRole', function (?string $surface = null): ?array {
                /** @var Request $this */
                $user = $this->user();
                if ($user instanceof GatewayUser) {
                    return $user->rbacRole($surface);
                }

                return null;
            });
        }
    }

    private static function registerGateBefore(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (! method_exists($user, 'getAuthIdentifier')) {
                return null;
            }

            $sub = trim((string) $user->getAuthIdentifier());
            if ($sub === '') {
                return null;
            }

            $surface = SurfaceResolver::resolve(request());
            $checker = app(PermissionCheckerInterface::class);

            $allowed = $checker->userCan($sub, $ability, $surface);

            if ($allowed) {
                return true;
            }

            $strictDeny = (bool) config('rbac.auth.strict_deny', true);
            if ($strictDeny) {
                return false;
            }

            return null;
        });
    }
}

