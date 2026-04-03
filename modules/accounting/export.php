<?php
// modules/accounting/export.php — CSV Export
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('reports', 'export');

$scope      = RBAC::farmScope();
$type       = $_GET['type'] ?? 'all';
$year       = (int)($_GET['year'] ?? date('Y'));
$dateFrom   = $_GET['date_from'] ?? null;
$dateTo     = $_GET['date_to']   ?? null;
$farmFilter = $_GET['farm_id']   ?? null;

$conditions = [];
$params = [];

if ($dateFrom && $dateTo) {
    $conditions[] = "s.sale_date BETWEEN ? AND ?"; $params[] = $dateFrom; $params[] = $dateTo;
} else {
    $conditions[] = "YEAR(s.sale_date) = ?"; $params[] = $year;
}
if ($scope) { $conditions[] = "s.farm_id = ?"; $params[] = $scope; }
elseif ($farmFilter) { $conditions[] = "s.farm_id = ?"; $params[] = (int)$farmFilter; }
if ($type !== 'all') { $conditions[] = "s.entity_type = ?"; $params[] = $type; }

$sqlWhere = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$rows = DB::rows("
    SELECT s.sale_date, f.name AS farm, s.entity_type AS type, s.quantity,
           s.unit_price, s.total_amount, s.buyer_name, u.name AS recorded_by, s.notes
    FROM sales s
    JOIN farms f ON f.id = s.farm_id
    LEFT JOIN users u ON u.id = s.recorded_by
    $sqlWhere
    ORDER BY s.sale_date DESC
", $params);

// Also handle egg-only export
if ($type === 'eggs' && isset($_GET['date_from'])) {
    $eggCond = ["date_produced BETWEEN ? AND ?"]; $eggParams = [$dateFrom, $dateTo];
    if ($scope) { $eggCond[] = "ep.farm_id = ?"; $eggParams[] = $scope; }
    $rows = DB::rows("
        SELECT ep.date_produced AS sale_date, f.name AS farm, 'egg' AS type,
               ep.quantity, ep.price_sold / ep.quantity AS unit_price, ep.price_sold AS total_amount,
               '' AS buyer_name, u.name AS recorded_by, ep.notes
        FROM egg_production ep
        JOIN farms f ON f.id = ep.farm_id
        LEFT JOIN users u ON u.id = ep.recorded_by
        WHERE " . implode(' AND ', $eggCond) . "
        ORDER BY ep.date_produced DESC
    ", $eggParams);
}

$filename = 'farmflow_' . $type . '_' . date('Ymd') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Farm', 'Type', 'Quantity', 'Unit Price (₦)', 'Total (₦)', 'Buyer', 'Recorded By', 'Notes']);
foreach ($rows as $row) {
    fputcsv($out, [
        $row['sale_date'], $row['farm'], $row['type'],
        $row['quantity'], number_format($row['unit_price'], 2),
        number_format($row['total_amount'], 2),
        $row['buyer_name'], $row['recorded_by'], $row['notes']
    ]);
}
fclose($out);
exit;
