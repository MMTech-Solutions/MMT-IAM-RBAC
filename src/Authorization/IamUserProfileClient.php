<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Authorization;

use Illuminate\Support\Facades\Http;
use Mmtech\Rbac\Support\IamApiResponse;
use Mmtech\Rbac\Support\IamInternalHttpHeaders;
use RuntimeException;

final class IamUserProfileClient
{
    /**
     * @return array<string, mixed>|null Profile payload from IAM `data`, or null when not found / not configured.
     */
    public function fetchBySub(string $sub): ?array
    {
        if (! (bool) config('rbac.iam_user.enabled', true)) {
            return null;
        }

        $baseUrl = trim((string) config('rbac.iam_user.base_url', config('rbac.fallback.base_url', '')));
        if ($baseUrl === '') {
            return null;
        }

        $headers = IamInternalHttpHeaders::build();
        $tokenHeader = (string) config('rbac.internal.token_header', 'X-Internal-Token');
        if (($headers[$tokenHeader] ?? '') === '') {
            return null;
        }

        $response = Http::timeout($this->timeoutSeconds())
            ->acceptJson()
            ->withHeaders($headers)
            ->get($this->userUrl($sub));

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException('IAM user profile request failed with status '.$response->status().'.');
        }

        $profile = IamApiResponse::extractDataPayload($response->json());

        return $profile !== [] ? $profile : null;
    }

    public function userUrl(string $sub): string
    {
        $baseUrl = rtrim(trim((string) config('rbac.iam_user.base_url', config('rbac.fallback.base_url', ''))), '/');
        $path = '/'.trim((string) config('rbac.iam_user.path', '/api/iam/v1/rbac/admin/users'), '/');

        return $baseUrl.$path.'/'.rawurlencode(trim($sub));
    }

    private function timeoutSeconds(): int
    {
        $timeoutMs = (int) config('rbac.iam_user.timeout_ms', config('rbac.fallback.timeout_ms', 1500));

        return max(1, (int) ceil($timeoutMs / 1000));
    }
}
