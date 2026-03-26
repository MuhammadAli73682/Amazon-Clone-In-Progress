<?php
// Lightweight "AI-like" recommendations based on user activity + product similarity.
// No external services required.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function reco_session_key(): string {
    $sid = session_id();
    if (!is_string($sid) || $sid === '') {
        return '';
    }
    return hash('sha256', $sid);
}

function reco_safe_exec(PDO $pdo, string $sql): void {
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        // best-effort migrations; ignore if unsupported on older MySQL versions
    }
}

function ensure_recommendations_schema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    // Keep schema minimal and resilient (no foreign keys) to avoid migration failures.
    reco_safe_exec($pdo, "
        CREATE TABLE IF NOT EXISTS product_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            session_key VARCHAR(64) NULL,
            product_id INT NOT NULL,
            event_type VARCHAR(20) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Indexes (try/catch because MySQL < 8 doesn't support IF NOT EXISTS for CREATE INDEX).
    reco_safe_exec($pdo, "CREATE INDEX idx_pe_user_date ON product_events (user_id, created_at)");
    reco_safe_exec($pdo, "CREATE INDEX idx_pe_session_date ON product_events (session_key, created_at)");
    reco_safe_exec($pdo, "CREATE INDEX idx_pe_product_type_date ON product_events (product_id, event_type, created_at)");
    reco_safe_exec($pdo, "CREATE INDEX idx_pe_type_date ON product_events (event_type, created_at)");
}

function track_product_event(PDO $pdo, ?int $userId, int $productId, string $eventType, int $quantity = 1): void {
    if ($productId <= 0) {
        return;
    }
    $eventType = strtolower(trim($eventType));
    $allowed = ['view', 'cart_add', 'wishlist_add', 'purchase'];
    if (!in_array($eventType, $allowed, true)) {
        return;
    }
    $quantity = max(1, (int)$quantity);
    $sessionKey = reco_session_key();

    ensure_recommendations_schema($pdo);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO product_events (user_id, session_key, product_id, event_type, quantity)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $sessionKey ?: null, $productId, $eventType, $quantity]);
    } catch (Exception $e) {
        // ignore tracking failures
    }
}

function reco_add_candidate(array &$acc, array $row, float $score, string $reason): void {
    $pid = (int)($row['id'] ?? 0);
    if ($pid <= 0) {
        return;
    }
    if (!isset($acc[$pid])) {
        $acc[$pid] = [
            'product' => [
                'id' => $pid,
                'name' => $row['name'] ?? '',
                'price' => (float)($row['price'] ?? 0),
                'image' => $row['image'] ?? '',
                'category' => $row['category'] ?? '',
                'shop_name' => $row['shop_name'] ?? '',
                'avg_rating' => (float)($row['avg_rating'] ?? 0),
                'review_count' => (int)($row['review_count'] ?? 0),
            ],
            'score' => 0.0,
            'reasons' => [],
        ];
    }
    $acc[$pid]['score'] += $score;
    if ($reason !== '' && count($acc[$pid]['reasons']) < 2 && !in_array($reason, $acc[$pid]['reasons'], true)) {
        $acc[$pid]['reasons'][] = $reason;
    }
}

function get_ai_recommendations(PDO $pdo, ?int $userId, string $sessionKey, ?int $contextProductId, int $limit = 8): array {
    ensure_recommendations_schema($pdo);

    $limit = max(1, min(12, (int)$limit));
    $contextProductId = $contextProductId ? (int)$contextProductId : null;

    $acc = [];
    $excludeIds = [];
    if ($contextProductId) {
        $excludeIds[$contextProductId] = true;
    }

    // Precompute ratings once.
    $ratingsJoin = "
        LEFT JOIN (
            SELECT product_id, COALESCE(AVG(rating),0) AS avg_rating, COUNT(*) AS review_count
            FROM reviews
            GROUP BY product_id
        ) rv ON rv.product_id = p.id
    ";

    // Context: load product meta.
    $context = null;
    if ($contextProductId) {
        $stmt = $pdo->prepare("SELECT id, category, price FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$contextProductId]);
        $context = $stmt->fetch() ?: null;
        if (!$context) {
            $contextProductId = null;
        }
    }

    // 1) Also bought together (strongest when context is present).
    if ($contextProductId) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.image, p.category, u.shop_name,
                   COALESCE(rv.avg_rating,0) AS avg_rating, COALESCE(rv.review_count,0) AS review_count,
                   SUM(oi2.quantity) AS cnt
            FROM order_items oi
            JOIN order_items oi2 ON oi.order_id = oi2.order_id AND oi2.product_id <> oi.product_id
            JOIN products p ON p.id = oi2.product_id
            JOIN users u ON u.id = p.seller_id
            $ratingsJoin
            WHERE oi.product_id = ?
              AND p.status = 'active'
              AND p.stock > 0
            GROUP BY p.id
            ORDER BY cnt DESC
            LIMIT 30
        ");
        $stmt->execute([$contextProductId]);
        foreach ($stmt->fetchAll() as $row) {
            $pid = (int)$row['id'];
            if (isset($excludeIds[$pid])) {
                continue;
            }
            $cnt = (int)($row['cnt'] ?? 0);
            $score = 90 + min(60, $cnt * 6);
            reco_add_candidate($acc, $row, $score, 'Often bought together');
        }
    }

    // 2) Personalized categories (recent views).
    if ($userId || $sessionKey) {
        $stmt = $pdo->prepare("
            SELECT p.category, COUNT(*) AS cnt
            FROM product_events e
            JOIN products p ON p.id = e.product_id
            WHERE e.event_type = 'view'
              AND e.created_at > (NOW() - INTERVAL 30 DAY)
              AND (
                    (? IS NOT NULL AND e.user_id = ?)
                 OR (? <> '' AND e.session_key = ?)
              )
              AND p.status = 'active'
            GROUP BY p.category
            ORDER BY cnt DESC
            LIMIT 3
        ");
        $uid = $userId ? (int)$userId : null;
        $stmt->execute([$uid, $uid, $sessionKey, $sessionKey]);
        $topCats = $stmt->fetchAll();

        // Exclude products already viewed recently.
        $seen = [];
        $seenStmt = $pdo->prepare("
            SELECT DISTINCT product_id
            FROM product_events
            WHERE event_type = 'view'
              AND created_at > (NOW() - INTERVAL 30 DAY)
              AND (
                    (? IS NOT NULL AND user_id = ?)
                 OR (? <> '' AND session_key = ?)
              )
            LIMIT 200
        ");
        $seenStmt->execute([$uid, $uid, $sessionKey, $sessionKey]);
        foreach ($seenStmt->fetchAll() as $r) {
            $seen[(int)$r['product_id']] = true;
        }

        foreach ($topCats as $catRow) {
            $cat = $catRow['category'] ?? '';
            if ($cat === '') {
                continue;
            }
            $cstmt = $pdo->prepare("
                SELECT p.id, p.name, p.price, p.image, p.category, u.shop_name,
                       COALESCE(rv.avg_rating,0) AS avg_rating, COALESCE(rv.review_count,0) AS review_count
                FROM products p
                JOIN users u ON u.id = p.seller_id
                $ratingsJoin
                WHERE p.status = 'active'
                  AND p.stock > 0
                  AND p.category = ?
                ORDER BY p.created_at DESC
                LIMIT 24
            ");
            $cstmt->execute([$cat]);
            foreach ($cstmt->fetchAll() as $row) {
                $pid = (int)$row['id'];
                if (isset($excludeIds[$pid]) || isset($seen[$pid])) {
                    continue;
                }
                $score = 70;
                reco_add_candidate($acc, $row, $score, 'Picked for you');
            }
        }
    }

    // 3) Similar products (category + price proximity).
    if ($context && !empty($context['category'])) {
        $category = $context['category'];
        $price = (float)$context['price'];
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.image, p.category, u.shop_name,
                   COALESCE(rv.avg_rating,0) AS avg_rating, COALESCE(rv.review_count,0) AS review_count,
                   ABS(p.price - ?) AS price_diff
            FROM products p
            JOIN users u ON u.id = p.seller_id
            $ratingsJoin
            WHERE p.status = 'active'
              AND p.stock > 0
              AND p.category = ?
              AND p.id <> ?
            ORDER BY price_diff ASC, p.created_at DESC
            LIMIT 30
        ");
        $stmt->execute([$price, $category, $contextProductId]);
        foreach ($stmt->fetchAll() as $row) {
            $pid = (int)$row['id'];
            if (isset($excludeIds[$pid])) {
                continue;
            }
            $diff = (float)($row['price_diff'] ?? 0);
            $score = 55 + max(0, 25 - min(25, $diff / 10.0));
            reco_add_candidate($acc, $row, $score, 'Similar to this item');
        }
    }

    // 4) Trending (recent events weighted).
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.image, p.category, u.shop_name,
               COALESCE(rv.avg_rating,0) AS avg_rating, COALESCE(rv.review_count,0) AS review_count,
               SUM(
                   CASE e.event_type
                       WHEN 'purchase' THEN 6 * e.quantity
                       WHEN 'cart_add' THEN 2 * e.quantity
                       WHEN 'wishlist_add' THEN 2 * e.quantity
                       WHEN 'view' THEN 1 * e.quantity
                       ELSE 0
                   END
               ) AS trend_score
        FROM product_events e
        JOIN products p ON p.id = e.product_id
        JOIN users u ON u.id = p.seller_id
        $ratingsJoin
        WHERE e.created_at > (NOW() - INTERVAL 14 DAY)
          AND p.status = 'active'
          AND p.stock > 0
        GROUP BY p.id
        ORDER BY trend_score DESC
        LIMIT 30
    ");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $pid = (int)$row['id'];
        if (isset($excludeIds[$pid])) {
            continue;
        }
        $ts = (int)($row['trend_score'] ?? 0);
        if ($ts <= 0) {
            continue;
        }
        $score = 35 + min(60, $ts);
        reco_add_candidate($acc, $row, $score, 'Trending now');
    }

    // If still empty, fallback to newest active products.
    if (empty($acc)) {
        $stmt = $pdo->query("
            SELECT p.id, p.name, p.price, p.image, p.category, u.shop_name,
                   COALESCE(rv.avg_rating,0) AS avg_rating, COALESCE(rv.review_count,0) AS review_count
            FROM products p
            JOIN users u ON u.id = p.seller_id
            $ratingsJoin
            WHERE p.status = 'active' AND p.stock > 0
            ORDER BY p.created_at DESC
            LIMIT 24
        ");
        foreach ($stmt->fetchAll() as $row) {
            $pid = (int)$row['id'];
            if (isset($excludeIds[$pid])) {
                continue;
            }
            reco_add_candidate($acc, $row, 10, 'New arrivals');
        }
    }

    // Sort by score desc; tie-break by rating.
    uasort($acc, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            $ar = (float)($a['product']['avg_rating'] ?? 0);
            $br = (float)($b['product']['avg_rating'] ?? 0);
            return $br <=> $ar;
        }
        return $b['score'] <=> $a['score'];
    });

    $out = [];
    foreach ($acc as $pid => $entry) {
        if (count($out) >= $limit) {
            break;
        }
        if (isset($excludeIds[$pid])) {
            continue;
        }
        $p = $entry['product'];
        $p['reason'] = $entry['reasons'][0] ?? '';
        $out[] = $p;
    }
    return $out;
}
?>
