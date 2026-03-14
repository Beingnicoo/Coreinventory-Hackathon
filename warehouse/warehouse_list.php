<?php
$pageTitle = 'Warehouses - CoreInventory';
$currentPage = 'warehouse';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$warehouses = $conn->query("SELECT * FROM warehouses ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Prepare display names so duplicate warehouse names get A/B suffixes
$nameCounts = [];
foreach ($warehouses as $w) {
    $name = $w['name'] ?? '';
    if ($name !== '') {
        $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;
    }
}
$nameIndex = [];

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-building text-primary"></i> Warehouses</h2>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($warehouses)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">No warehouses.</td></tr>
                    <?php else: ?>
                    <?php foreach ($warehouses as $w): ?>
                    <tr>
                        <td><?= $w['id'] ?></td>
                        <td>
                            <?php
                            $name = $w['name'] ?? '';
                            if ($name !== '' && ($nameCounts[$name] ?? 0) > 1) {
                                $nameIndex[$name] = ($nameIndex[$name] ?? 0) + 1;
                                $suffix = chr(ord('A') + $nameIndex[$name] - 1);
                                $displayName = $name . ' ' . $suffix;
                            } else {
                                $displayName = $name;
                            }
                            ?>
                            <?= htmlspecialchars($displayName) ?>
                        </td>
                        <td><?= htmlspecialchars($w['location'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
