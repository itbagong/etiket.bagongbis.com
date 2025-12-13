<?php
require_once __DIR__ . '/src/dashboard_functions.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    $filter = $_GET['filter'] ?? 'all';

    $data = [
        'stats' => getDashboardStats($filter),
        'categories' => getCategoryBreakdown($filter),
        'locations' => getLocationBreakdown($filter),
        'recent' => getRecentWorkOrders($filter)
    ];

    echo json_encode($data, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
