<?php
require_once __DIR__ . '/../../lib/Auth.php';
AdminAccess::requireUserApi($session); AdminAccess::requirePost($request);
if ((string)$session->get('role', '') !== 'admin') AdminResponder::jsonError('Access denied.', 403);
if (!Csrf::validate((string)$request->post('csrf_token', ''))) AdminResponder::jsonError('Invalid CSRF token.', 403);
$provider = strtolower(trim((string)$request->post('provider', ''))); $key = 'integration.admin.' . $provider;
if (!preg_match('/^[a-z0-9_-]+$/', $provider) || !registry()->has($key)) AdminResponder::jsonError('Integration provider not found.', 404);
$service = registry()->get($key); $action = (string)$request->post('action', '');
if ($action === 'save') AdminResponder::json($service->save($request->post()));
if ($action === 'rotate_token') AdminResponder::json($service->rotateToken());
AdminResponder::jsonError('Unknown action.', 400);
