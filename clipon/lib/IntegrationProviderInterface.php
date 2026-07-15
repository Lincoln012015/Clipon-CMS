<?php

interface IntegrationProviderInterface
{
    public function id(): string;
    public function authenticate(IntegrationRequest $request): bool;
    public function upsert(array $payload): IntegrationResult;
    public function delete(string $externalId): IntegrationResult;
}
