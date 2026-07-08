<?php

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/JsonStorage.php';

AdminAccess::requireUserApi($session);

$pagesDir = C_CONTENT_PATH . '/pages/';
$directoriesFile = C_CONFIG_PATH . '/directories.php';
$conversionConfigFile = C_CONFIG_PATH . '/conversions.php';

$directoryService = new PageDirectoryService($directoriesFile);
$pageService = null;
if (class_exists('Hooks')) {
    $pageService = Hooks::applyFilters('multilang:page_service_instance', null, $pagesDir, $conversionConfigFile, $directoryService);
}
if (!is_object($pageService)) {
    $pageService = new PageService($pagesDir, $conversionConfigFile, $directoryService);
}
$conversionTypes = Settings::getConversionTypes() ?: [];

AdminAccess::requirePost($request);

$csrfToken = (string)$request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    AdminResponder::jsonError(__('error_invalid_csrf') ?: 'Invalid CSRF token', 403);
}

$action = (string)$request->post('action', '');

$router = new AdminActionRouter();

$router->on('create_page', function () use ($request, $session, $pageService, $conversionTypes): void {
    AdminAccess::requirePermissionJson('create_pages', __('error_no_create_pages_permission'));

    $result = $pageService->createPage($request->post(), $session, $conversionTypes);
    AdminResponder::json($result);
});

$router->on('add_dir', function () use ($request, $directoryService): void {
    AdminAccess::requirePermissionJson('create_pages', __('error_no_create_pages_permission'));

    $name = $request->string('name');
    $parent = $request->post('parent') ?: null;
    $directoryService->addDirectory($name, $parent);
    AdminResponder::jsonSuccess();
});

$router->on('edit_dir', function () use ($request, $directoryService): void {
    AdminAccess::requirePermissionJson('edit_pages', __('error_no_edit_pages_permission'));

    $id = (string)$request->post('id', '');
    $name = $request->string('name');
    $parent = $request->post('parent') ?: null;

    if (!$directoryService->editDirectory($id, $name, $parent)) {
        AdminResponder::jsonError(__('error_directory_cycle_dependency'));
    }

    AdminResponder::jsonSuccess();
});

$router->on('delete_dir', function () use ($request, $directoryService, $pageService): void {
    AdminAccess::requirePermissionJson('delete_pages', __('error_no_delete_pages_permission'));

    $id = (string)$request->post('id', '');
    if ($id === '') {
        AdminResponder::jsonError(__('error_invalid_parameters'));
    }

    $deleteSnapshot = $directoryService->deleteDirectoryWithPages($id, C_CONTENT_PATH . '/pages/');
    if (!$pageService->rebuildRouteMap()) {
        $directoryService->restoreDirectoryDeletion($deleteSnapshot, C_CONTENT_PATH . '/pages/');
        $pageService->rebuildRouteMap();
        AdminResponder::jsonError(__('system_error') . ': route map rebuild failed');
    }
    $pageService->rebuildConversionMap();
    AdminResponder::jsonSuccess();
});

$router->on('delete_page', function () use ($request, $pageService): void {
    AdminAccess::requirePermissionJson('delete_pages', __('error_no_delete_pages_permission'));

    $slug = (string)$request->post('slug', '');
    if ($slug === '') {
        AdminResponder::jsonError(__('error_invalid_parameters'));
    }

        if (!$pageService->deletePage($slug)) {
            AdminResponder::jsonError(__('system_error') . ': route map rebuild failed');
        }
    AdminResponder::jsonSuccess();
});

$router->on('copy_page', function () use ($request, $session, $pageService, $conversionTypes): void {
    AdminAccess::requirePermissionJson('create_pages', __('error_no_copy_pages_permission'));

    $slug = (string)$request->post('slug', '');
    if ($slug === '') {
        AdminResponder::jsonError(__('error_invalid_parameters'));
    }

        if (!$pageService->copyPage($slug, $session, $conversionTypes)) {
            AdminResponder::jsonError(__('system_error') . ': route map rebuild failed');
        }
    AdminResponder::jsonSuccess();
});

$router->on('update_page', function () use ($request, $session, $pageService, $conversionTypes): void {
    AdminAccess::requirePermissionJson('edit_pages', __('error_no_edit_pages_permission'));

    $result = $pageService->updatePage($request->post(), $session, $conversionTypes, true);
    AdminResponder::json($result);
});

$router->on('reorder', function () use ($request, $pageService): void {
    AdminAccess::requirePermissionJson('edit_pages', __('error_no_reorder_pages_permission'));

    $data = json_decode((string)$request->post('data', '[]'), true);
    if (!is_array($data)) {
        AdminResponder::jsonError(__('error_invalid_parameters'));
    }

    $pageService->reorder($data);
    AdminResponder::jsonSuccess();
});

$router->on('get_history', function () use ($request, $pageService): void {
    AdminAccess::requirePermissionJson('view_versions', __('error_no_view_versions_permission') ?: 'Немає прав для перегляду версій.');

    $slug = (string)$request->post('slug', '');
    AdminResponder::json($pageService->getHistory($slug));
});

$router->on('restore_version', function () use ($request, $pageService): void {
    AdminAccess::requirePermissionJson('restore_versions', __('error_no_restore_permission'));

    $slug = (string)$request->post('slug', '');
    $timestamp = (string)$request->post('timestamp', '');
    AdminResponder::json($pageService->restoreVersion($slug, $timestamp));
});

$router->on('toggle_active', function () use ($request, $pageService): void {
    AdminAccess::requirePermissionJson('edit_pages', __('error_no_edit_pages_permission'));

    $slug = (string)$request->post('slug', '');
        AdminResponder::json($pageService->toggleActive($slug));
});

$router->on('set_homepage', function () use ($request, $pageService): void {
    AdminAccess::requirePermissionJson('edit_pages', __('error_no_change_home_permission'));

    $slug = (string)$request->post('slug', '');
        AdminResponder::json($pageService->setHomepage($slug));
});

$router->dispatch($action, function (): void {
    AdminResponder::jsonError('Unknown action', 400);
});
