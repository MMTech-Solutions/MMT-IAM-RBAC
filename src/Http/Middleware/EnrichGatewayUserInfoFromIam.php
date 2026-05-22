<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mmtech\Rbac\Authorization\IamUserProfileClient;
use Mmtech\Rbac\Support\InternalServiceRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Fetches the full IAM user profile from MMT-AUTH-SERVICE and merges it into
 * {@see Request::$attributes} key `gateway_auth_user_info` so
 * {@see BindGatewayUserToAuth} binds a complete {@see \Mmtech\Rbac\Auth\GatewayUser}.
 *
 * Run after gateway JWT resolution (`rbac.auth.user`) and before `rbac.bind.gateway.user`.
 */
final class EnrichGatewayUserInfoFromIam
{
    public function __construct(
        private readonly IamUserProfileClient $iamUserProfileClient
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (InternalServiceRequest::isTrusted($request)) {
            return $next($request);
        }

        if (! (bool) config('rbac.iam_user.enabled', true)) {
            return $next($request);
        }

        $gatewayAuthUserInfo = $request->attributes->get('gateway_auth_user_info');
        if (! is_array($gatewayAuthUserInfo) || empty($gatewayAuthUserInfo['sub'])) {
            return $next($request);
        }

        $userUuid = trim((string) $gatewayAuthUserInfo['sub']);
        if ($userUuid === '') {
            return $next($request);
        }

        try {
            $profile = $this->iamUserProfileClient->fetchBySub($userUuid);
            if ($profile === null) {
                return $this->failOrContinue($request, $next, $gatewayAuthUserInfo);
            }

            $merged = array_merge($gatewayAuthUserInfo, $profile);
            if (! isset($merged['sub']) || trim((string) $merged['sub']) === '') {
                $merged['sub'] = $userUuid;
            }

            $request->attributes->set('gateway_auth_user_info', $merged);

            return $next($request);
        } catch (Throwable $e) {
            $this->logFailure('IAM user profile enrichment failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            if ((bool) config('rbac.iam_user.fail_open', true)) {
                return $this->failOrContinue($request, $next, $gatewayAuthUserInfo);
            }

            return new JsonResponse(['message' => 'Unable to resolve user profile.'], 502);
        }
    }

    /**
     * @param  array<string, mixed>  $gatewayAuthUserInfo
     */
    private function failOrContinue(Request $request, Closure $next, array $gatewayAuthUserInfo): Response
    {
        if ((bool) config('rbac.iam_user.fail_open', true)) {
            $request->attributes->set('gateway_auth_user_info', $gatewayAuthUserInfo);

            return $next($request);
        }

        return new JsonResponse(['message' => 'Unable to resolve user profile.'], 502);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logFailure(string $message, array $context): void
    {
        if ((bool) config('rbac.iam_user.log_failures', false)) {
            Log::warning($message, $context);
        }
    }
}
