<?php

final class IntegrationDispatcher
{
    public function __construct(private ServiceRegistry $registry, private IntegrationRateLimiter $limiter) {}

    public function dispatch(IntegrationRequest $request): IntegrationResult
    {
        $providerId = $request->query('provider');
        if (!preg_match('/^[a-z0-9_-]+$/', $providerId)) {
            return IntegrationResult::error('provider_not_found', 'Integration provider was not found.', 404);
        }
        $key = 'integration.provider.' . $providerId;
        if (!$this->registry->has($key)) return IntegrationResult::error('provider_not_found', 'Integration provider was not found.', 404);

        $rate = $this->limiter->consume($providerId, $request->clientIp());
        if (!$rate['allowed']) return IntegrationResult::error('rate_limited', 'Too many requests.', 429, ['retry_after' => $rate['retry_after']]);

        $provider = $this->registry->get($key);
        if (!$provider instanceof IntegrationProviderInterface || $provider->id() !== $providerId) {
            return IntegrationResult::error('provider_not_found', 'Integration provider was not found.', 404);
        }
        if (!$provider->authenticate($request)) return IntegrationResult::error('unauthorized', 'Invalid or missing Bearer token.', 401);

        try {
            if ($request->method() === 'POST') {
                $payload = $request->json();
                if ($payload === null) return IntegrationResult::error('validation_error', 'Request body must contain valid JSON.', 400);
                return $provider->upsert($payload);
            }
            if ($request->method() === 'DELETE') {
                $id = $request->query('id');
                if ($id === '') return IntegrationResult::error('validation_error', 'The id query parameter is required.', 400);
                return $provider->delete($id);
            }
            return IntegrationResult::error('method_not_allowed', 'Only POST and DELETE are supported.', 405);
        } catch (Throwable $e) {
            if (class_exists('Log')) Log::error('Integration ' . $providerId . ' failed: ' . $e->getMessage());
            return IntegrationResult::error('write_failed', 'The integration request could not be completed.', 500);
        }
    }
}
