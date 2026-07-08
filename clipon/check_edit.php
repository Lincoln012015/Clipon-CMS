<?php
require_once __DIR__ . '/bootstrap.php';

$edit = $session->has('user') && $request->query('edit') !== null;

header('Content-Type: application/json');
echo json_encode(['edit' => $edit]);
?>