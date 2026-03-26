<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'seller') {
    header('Location: ../login.php');
    exit;
}

$sellerId = (int)$_SESSION['user_id'];
$fatalErrors = [];
$rowErrors = [];
$results = [
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
];

function normalize_csv_header($value) {
    $value = (string)$value;
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value); // strip UTF-8 BOM
    return strtolower(trim($value));
}

function safe_image_path_or_null($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    if (strlen($value) > 255) {
        return null;
    }
    if (preg_match('/^(https?:)?\\/\\//i', $value)) {
        return null;
    }
    if (str_contains($value, '..')) {
        return null;
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_fail();

    if (!isset($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $fatalErrors[] = 'Please upload a valid CSV file.';
    } else {
        $maxBytes = 5 * 1024 * 1024;
        if (($_FILES['csv']['size'] ?? 0) > $maxBytes) {
            $fatalErrors[] = 'CSV file is too large (max 5MB).';
        } else {
            $fh = fopen($_FILES['csv']['tmp_name'], 'r');
            if (!$fh) {
                $fatalErrors[] = 'Unable to read the uploaded CSV file.';
            } else {
                $header = fgetcsv($fh);
                if (!is_array($header)) {
                    $fatalErrors[] = 'CSV is empty or invalid.';
                } else {
                    $headerMap = [];
                    foreach ($header as $i => $h) {
                        $key = normalize_csv_header($h);
                        if ($key !== '' && !isset($headerMap[$key])) {
                            $headerMap[$key] = (int)$i;
                        }
                    }

                    $required = ['name', 'price'];
                    foreach ($required as $key) {
                        if (!isset($headerMap[$key])) {
                            $fatalErrors[] = "Missing required column: {$key}";
                        }
                    }

                    if (!$fatalErrors) {
                        $pdo->beginTransaction();
                        try {
                            $selectSellerStmt = $pdo->prepare('SELECT seller_id FROM products WHERE id = ?');
                            $insertStmt = $pdo->prepare('
                                INSERT INTO products (seller_id, name, description, price, stock, category, status, image, language, currency)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ');
                            $updateStmt = $pdo->prepare('
                                UPDATE products
                                SET name = ?, description = ?, price = ?, stock = ?, category = ?, status = ?, image = ?, language = ?, currency = ?
                                WHERE id = ? AND seller_id = ?
                            ');

                            $line = 1; // header line
                            $maxRows = 5000;
                            $hitFatal = false;

                            while (($row = fgetcsv($fh)) !== false) {
                                $line++;
                                if ($line > ($maxRows + 1)) {
                                    $fatalErrors[] = "Too many rows (max {$maxRows}).";
                                    $hitFatal = true;
                                    break;
                                }
                                if (!is_array($row) || count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                                    continue;
                                }

                                $get = function (string $key) use ($headerMap, $row) {
                                    $idx = $headerMap[$key] ?? null;
                                    return $idx === null ? '' : (string)($row[$idx] ?? '');
                                };

                                $idRaw = trim($get('id'));
                                $name = trim($get('name'));
                                $description = trim($get('description'));
                                $priceRaw = trim($get('price'));
                                $stockRaw = trim($get('stock'));
                                $category = trim($get('category'));
                                $status = strtolower(trim($get('status') ?: 'active'));
                                $image = safe_image_path_or_null($get('image'));
                                $language = strtolower(trim($get('language') ?: 'en'));
                                $currency = strtoupper(trim($get('currency') ?: 'USD'));

                                if ($name === '') {
                                    $results['skipped']++;
                                    $rowErrors[] = "Line {$line}: name is required.";
                                    continue;
                                }

                                if ($priceRaw === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
                                    $results['skipped']++;
                                    $rowErrors[] = "Line {$line}: invalid price.";
                                    continue;
                                }
                                $price = (float)$priceRaw;

                                $stock = 0;
                                if ($stockRaw !== '') {
                                    if (!is_numeric($stockRaw) || (int)$stockRaw < 0) {
                                        $results['skipped']++;
                                        $rowErrors[] = "Line {$line}: invalid stock.";
                                        continue;
                                    }
                                    $stock = (int)$stockRaw;
                                }

                                if (!in_array($status, ['active', 'inactive'], true)) {
                                    $status = 'active';
                                }
                                if ($language === '' || strlen($language) > 10) {
                                    $language = 'en';
                                }
                                if ($currency === '' || strlen($currency) > 3) {
                                    $currency = 'USD';
                                }

                                $description = $description === '' ? null : $description;
                                $category = $category === '' ? null : $category;
                                $imageForDb = $image ?? 'assets/images/products/default.jpg';

                                if ($idRaw !== '' && ctype_digit($idRaw)) {
                                    $productId = (int)$idRaw;
                                    $selectSellerStmt->execute([$productId]);
                                    $existing = $selectSellerStmt->fetch(PDO::FETCH_ASSOC);
                                    if ($existing && (int)$existing['seller_id'] !== $sellerId) {
                                        $results['skipped']++;
                                        $rowErrors[] = "Line {$line}: product id {$productId} does not belong to this seller.";
                                        continue;
                                    }

                                    if ($existing) {
                                        $updateStmt->execute([
                                            $name,
                                            $description,
                                            $price,
                                            $stock,
                                            $category,
                                            $status,
                                            $imageForDb,
                                            $language,
                                            $currency,
                                            $productId,
                                            $sellerId,
                                        ]);
                                        $results['updated']++;
                                        continue;
                                    }
                                }

                                $insertStmt->execute([
                                    $sellerId,
                                    $name,
                                    $description,
                                    $price,
                                    $stock,
                                    $category,
                                    $status,
                                    $imageForDb,
                                    $language,
                                    $currency,
                                ]);
                                $results['inserted']++;
                            }

                            fclose($fh);

                            if ($hitFatal || $fatalErrors) {
                                $pdo->rollBack();
                            } else {
                                $pdo->commit();
                            }
                        } catch (Throwable $e) {
                            fclose($fh);
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            $fatalErrors[] = 'Import failed: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Products (CSV) - Seller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/backend-header.php'; ?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Bulk Import Products</h2>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="download-products-template.php">
                <i class="fas fa-download"></i> CSV Template
            </a>
            <a class="btn btn-secondary" href="products.php">Back</a>
        </div>
    </div>

    <div class="alert alert-info">
        Excel se export/import ke liye CSV use karein. Template download karke same headers ke sath file upload karein.
        <br>
        Tip: Agar aap `id` column fill kar dein to existing product update ho jayega (sirf aapke products).
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php if ($fatalErrors): ?>
            <div class="alert alert-danger">
                <strong>Import failed.</strong>
                <ul class="mb-0">
                    <?php foreach (array_slice($fatalErrors, 0, 20) as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($fatalErrors) > 20): ?>
                    <div class="mt-2 text-muted">Showing first 20 errors.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                Import complete: inserted <?= (int)$results['inserted'] ?>, updated <?= (int)$results['updated'] ?>.
            </div>
            <?php if ($rowErrors): ?>
                <div class="alert alert-warning">
                    Imported with warnings. Skipped <?= (int)$results['skipped'] ?> row(s).
                    <ul class="mb-0">
                        <?php foreach (array_slice($rowErrors, 0, 20) as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($rowErrors) > 20): ?>
                        <div class="mt-2 text-muted">Showing first 20 warnings.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="mb-3">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csv" accept=".csv,text/csv" class="form-control" required>
                    <div class="form-text">Max 5MB, max 5000 rows.</div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Import
                </button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
