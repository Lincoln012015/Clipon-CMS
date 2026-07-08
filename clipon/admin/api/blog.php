<?php

require_once __DIR__ . '/../../lib/Auth.php';

AdminAccess::requireUserApi($session);
AdminAccess::requirePost($request);

$csrfToken = (string)$request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    AdminResponder::jsonError(__('error_invalid_csrf') ?: 'Invalid CSRF token', 403);
}

$blogDir = C_CONTENT_PATH . '/blog/';
$pagesDir = C_CONTENT_PATH . '/pages/';
$directoriesFile = C_CONFIG_PATH . '/blog_directories.php';
$tagsFile = C_DATA_PATH . '/blog_tags.php';
$blogService = new BlogService($blogDir, $pagesDir);
$blogDirectoryService = new BlogDirectoryService($directoriesFile, $blogDir);
$blogTagService = new BlogTagService($tagsFile, $blogDir);

$action = (string)$request->post('action', '');
$router = new AdminActionRouter();

$router->on('create_post', function () use ($request, $session, $blogService): void {
    $result = $blogService->createPost($request->post(), $session);
    AdminResponder::json($result);
});

$router->on('add_dir', function () use ($request, $blogDirectoryService): void {
    AdminAccess::requirePermissionJson('create_blog', __('error_no_create_blog_permission') ?: 'Немає прав для створення постів.');

    $name = $request->string('name');
    $parent = $request->post('parent') ?: null;

    $blogDirectoryService->addDirectory($name, $parent);
    AdminResponder::jsonSuccess();
});

$router->on('edit_dir', function () use ($request, $blogDirectoryService): void {
    AdminAccess::requirePermissionJson('edit_blog', __('error_no_edit_blog_permission') ?: 'Немає прав для редагування постів.');

    $id = (string)$request->post('id', '');
    $name = $request->string('name');
    $parent = $request->post('parent') ?: null;

    if (!$blogDirectoryService->editDirectory($id, $name, $parent)) {
        AdminResponder::jsonError('Циклічна залежність директорій.');
    }

    AdminResponder::jsonSuccess();
});

$router->on('delete_dir', function () use ($request, $blogDirectoryService, $blogService): void {
    AdminAccess::requirePermissionJson('delete_blog', __('error_no_delete_blog_permission') ?: 'Немає прав для видалення постів.');

    $id = (string)$request->post('id', '');
    if ($id === '') {
        AdminResponder::jsonError(__('error_invalid_parameters') ?: 'Некоректні параметри.');
    }

    $deleteSnapshot = $blogDirectoryService->deleteDirectoryWithPosts($id);
    if (!$blogService->rebuildRouteMap()) {
        $blogDirectoryService->restoreDirectoryDeletion($deleteSnapshot);
        $blogService->rebuildRouteMap();
        AdminResponder::jsonError(__('system_error') . ': route map rebuild failed');
    }
    AdminResponder::jsonSuccess();
});

$router->on('delete_post', function () use ($request, $blogService): void {
    AdminAccess::requirePermissionJson('delete_blog', __('error_no_delete_blog_permission') ?: 'Немає прав для видалення постів.');

    $slug = (string)$request->post('slug', '');
    if ($slug === '') {
        AdminResponder::jsonError(__('error_invalid_parameters') ?: 'Некоректні параметри.');
    }

    if (!$blogService->deletePost($slug)) {
        AdminResponder::jsonError(__('system_error') . ': route map rebuild failed');
    }
    AdminResponder::jsonSuccess();
});

$router->on('duplicate_post', function () use ($request, $blogService): void {
    AdminAccess::requirePermissionJson('create_blog', __('error_no_create_blog_permission') ?: 'Немає прав для створення постів.');

    $slug = (string)$request->post('slug', '');
    if ($slug === '') {
        AdminResponder::jsonError(__('error_invalid_parameters') ?: 'Некоректні параметри.');
    }

    AdminResponder::json($blogService->duplicatePost($slug));
});

$router->on('update_post', function () use ($request, $session, $blogService): void {
    AdminAccess::requirePermissionJson('edit_blog', __('error_no_edit_blog_permission') ?: 'Немає прав для редагування постів.');

    $result = $blogService->updatePost($request->post(), $session, true);
    AdminResponder::json($result);
});

$router->on('reorder', function () use ($request, $blogDirectoryService, $blogService): void {
    AdminAccess::requirePermissionJson('edit_blog', __('error_no_edit_blog_permission') ?: 'Немає прав для редагування постів.');

    $data = json_decode((string)$request->post('data', '[]'), true);
    if (!is_array($data)) {
        AdminResponder::jsonError(__('error_invalid_parameters') ?: 'Некоректні параметри.');
    }

    $blogDirectoryService->reorder($data);
    $blogService->reorder($data);
    AdminResponder::jsonSuccess();
});

$router->on('toggle_active', function () use ($request, $blogService): void {
    AdminAccess::requirePermissionJson('edit_blog', __('error_no_edit_blog_permission') ?: 'Немає прав для редагування постів.');

    $slug = (string)$request->post('slug', '');
    AdminResponder::json($blogService->toggleActive($slug));
});

$router->on('create_tag', function () use ($request, $blogTagService): void {
    AdminAccess::requirePermissionJson('edit_blog', __('error_no_edit_blog_permission') ?: 'Немає прав для редагування блогу.');
    AdminResponder::json($blogTagService->createTag((string)$request->post('name', ''), (string)$request->post('lang', '')));
});

$router->on('rename_tag', function () use ($request, $blogTagService, $blogService): void {
    AdminAccess::requirePermissionJson('edit_blog', __('error_no_edit_blog_permission') ?: 'Немає прав для редагування блогу.');

    $result = $blogTagService->renameTag((string)$request->post('id', ''), (string)$request->post('name', ''), (string)$request->post('lang', ''));
    if (($result['status'] ?? '') === 'success' && !$blogService->rebuildRouteMap()) {
        AdminResponder::jsonError(__('system_error') . ': route map rebuild failed');
    }
    AdminResponder::json($result);
});

$router->on('update_tag_locale', function () use ($request, $blogTagService, $blogService): void {
    AdminAccess::requirePermissionJson('edit_blog', __('error_no_edit_blog_permission') ?: 'Немає прав для редагування блогу.');

    $labels = $request->post('labels', []);
    $result = $blogTagService->updateTagTranslations(
        (string)$request->post('id', ''),
        is_array($labels) ? $labels : []
    );
    if (($result['status'] ?? '') === 'success' && !$blogService->rebuildRouteMap()) {
        AdminResponder::jsonError(__('system_error') . ': route map rebuild failed');
    }
    AdminResponder::json($result);
});

$router->on('delete_tag', function () use ($request, $blogTagService, $blogService): void {
    AdminAccess::requirePermissionJson('edit_blog', __('error_no_edit_blog_permission') ?: 'Немає прав для редагування блогу.');

    $result = $blogTagService->deleteTag((string)$request->post('id', ''));
    if (($result['status'] ?? '') === 'success' && !$blogService->rebuildRouteMap()) {
        AdminResponder::jsonError(__('system_error') . ': route map rebuild failed');
    }
    AdminResponder::json($result);
});

$router->dispatch($action, function (): void {
    AdminResponder::jsonError('Unknown action', 400);
});
