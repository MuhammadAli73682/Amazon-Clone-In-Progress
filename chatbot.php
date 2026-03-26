<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/recommendations.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$token = $_POST['csrf_token'] ?? '';
if (!csrf_validate($token)) {
    json_error('Invalid CSRF token', 403);
}

$message = trim((string) ($_POST['message'] ?? ''));
if ($message === '') {
    json_error('Message is required');
}

$userId = $_SESSION['user_id'] ?? null;
$userType = $_SESSION['user_type'] ?? null;
$text = mb_strtolower($message);

function reply_ok($reply, $suggestions = [])
{
    echo json_encode([
        'success' => true,
        'reply' => $reply,
        'suggestions' => $suggestions,
    ]);
    exit;
}

function format_price($value): string
{
    return '$' . number_format((float) $value, 2);
}

function base_product_query(): string
{
    return "
        SELECT
            p.id,
            p.name,
            p.description,
            p.price,
            p.category,
            p.stock,
            p.status,
            p.image,
            p.seller_id,
            u.shop_name,
            u.full_name AS seller_name,
            COALESCE(rv.avg_rating, 0) AS avg_rating,
            COALESCE(rv.review_count, 0) AS review_count
        FROM products p
        JOIN users u ON u.id = p.seller_id
        LEFT JOIN (
            SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
            FROM reviews
            GROUP BY product_id
        ) rv ON rv.product_id = p.id
        WHERE p.status = 'active'
    ";
}

function clean_lookup_phrase(string $message): string
{
    $value = mb_strtolower(trim($message));
    $patterns = [
        '/\bwhat(?:\'s| is)?\b/u',
        '/\bshow\b/u',
        '/\btell me\b/u',
        '/\babout\b/u',
        '/\bdetails?\b/u',
        '/\bdetail\b/u',
        '/\bprice\b/u',
        '/\bcost\b/u',
        '/\bproduct\b/u',
        '/\bitem\b/u',
        '/\bof\b/u',
        '/\bfor\b/u',
        '/\bthe\b/u',
        '/\bany\b/u',
        '/\bstore\b/u',
        '/\bshop\b/u',
        '/\breviews?\b/u',
        '/\brating\b/u',
        '/\blast\b/u',
        '/\blatest\b/u',
        '/\brecent(?:ly)?\b/u',
        '/\bview(?:ed)?\b/u',
        '/\bmy\b/u',
    ];
    $value = preg_replace($patterns, ' ', $value);
    $value = preg_replace('/\s+/u', ' ', (string) $value);
    return trim((string) $value);
}

function find_product_matches(PDO $pdo, string $phrase, int $limit = 3): array
{
    $phrase = trim($phrase);
    if ($phrase === '') {
        return [];
    }

    $sql = base_product_query() . "
        AND (
            p.name LIKE ?
            OR p.description LIKE ?
            OR p.category LIKE ?
            OR u.shop_name LIKE ?
        )
        ORDER BY
            CASE
                WHEN LOWER(p.name) = LOWER(?) THEN 1
                WHEN LOWER(p.name) LIKE LOWER(?) THEN 2
                WHEN LOWER(u.shop_name) = LOWER(?) THEN 3
                ELSE 4
            END,
            rv.review_count DESC,
            p.created_at DESC
        LIMIT " . max(1, min(5, $limit));

    $like = '%' . $phrase . '%';
    $startsWith = $phrase . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$like, $like, $like, $like, $phrase, $startsWith, $phrase]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function find_store_matches(PDO $pdo, string $phrase, int $limit = 3): array
{
    $phrase = trim($phrase);
    if ($phrase === '') {
        return [];
    }

    $sql = "
        SELECT
            u.id,
            u.shop_name,
            u.full_name,
            COUNT(DISTINCT p.id) AS product_count,
            COALESCE(AVG(r.rating), 0) AS avg_rating,
            COUNT(r.id) AS review_count
        FROM users u
        LEFT JOIN products p ON p.seller_id = u.id AND p.status = 'active'
        LEFT JOIN reviews r ON r.product_id = p.id
        WHERE u.user_type = 'seller'
          AND (
              u.shop_name LIKE ?
              OR u.full_name LIKE ?
          )
        GROUP BY u.id, u.shop_name, u.full_name
        ORDER BY
            CASE
                WHEN LOWER(u.shop_name) = LOWER(?) THEN 1
                WHEN LOWER(u.shop_name) LIKE LOWER(?) THEN 2
                WHEN LOWER(u.full_name) = LOWER(?) THEN 3
                ELSE 4
            END,
            review_count DESC,
            product_count DESC
        LIMIT " . max(1, min(5, $limit));

    $like = '%' . $phrase . '%';
    $startsWith = $phrase . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$like, $like, $phrase, $startsWith, $phrase]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_recent_viewed_product(PDO $pdo, ?int $userId): ?array
{
    ensure_recommendations_schema($pdo);
    $sessionKey = reco_session_key();
    $uid = $userId ? (int) $userId : null;

    $sql = base_product_query() . "
        AND p.id = (
            SELECT e.product_id
            FROM product_events e
            WHERE e.event_type = 'view'
              AND (
                    (? IS NOT NULL AND e.user_id = ?)
                 OR (? <> '' AND e.session_key = ?)
              )
            ORDER BY e.created_at DESC
            LIMIT 1
        )
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid, $uid, $sessionKey, $sessionKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function build_product_summary(array $product, bool $includeDescription = true): string
{
    $summary = $product['name'] . " " . format_price($product['price']);
    if (!empty($product['category'])) {
        $summary .= " in " . $product['category'];
    }
    if (!empty($product['shop_name'])) {
        $summary .= " from " . $product['shop_name'];
    }
    if ((int) ($product['review_count'] ?? 0) > 0) {
        $summary .= ". Rating: " . number_format((float) $product['avg_rating'], 1) . "/5 from " . (int) $product['review_count'] . " reviews";
    } else {
        $summary .= ". No reviews yet";
    }
    $summary .= ". Stock: " . ((int) ($product['stock'] ?? 0) > 0 ? 'Available' : 'Out of stock');
    if ($includeDescription && !empty($product['description'])) {
        $summary .= ". " . mb_substr(trim((string) $product['description']), 0, 140);
    }
    return rtrim($summary, '.') . '.';
}

function product_suggestions(array $products): array
{
    $suggestions = [];
    foreach ($products as $product) {
        $suggestions[] = [
            'label' => (string) $product['name'],
            'href' => 'product-detail.php?id=' . (int) $product['id'],
        ];
    }
    return $suggestions;
}

function store_suggestions(array $stores): array
{
    $suggestions = [];
    foreach ($stores as $store) {
        $suggestions[] = [
            'label' => (string) ($store['shop_name'] ?: $store['full_name']),
            'href' => 'products.php?seller_id=' . (int) $store['id'],
        ];
    }
    return $suggestions;
}

// Order status / tracking
if (preg_match('/\\b(order|track|tracking|status)\\b/i', $text)) {
    if (!$userId) {
        reply_ok(
            "Order status dekhne ke liye pehle login karein, phir apna order number bhejein. Example: 45872134.",
            [
                ['label' => 'Login', 'href' => 'login.php'],
                ['label' => 'Track Order', 'href' => 'track-order.php'],
            ]
        );
    }

    if (preg_match('/\\b(\\d{2,20})\\b/', $text, $m)) {
        $rawNumber = $m[1];
        $stmt = $pdo->prepare('SELECT id, order_number, status, created_at FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$rawNumber, (int) $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order && ctype_digit($rawNumber)) {
            $stmt = $pdo->prepare('SELECT id, order_number, status, created_at FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([(int) $rawNumber, (int) $userId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($order) {
            $created = $order['created_at'] ? date('M j, Y', strtotime($order['created_at'])) : '';
            $displayNumber = $order['order_number'] ?: $order['id'];
            reply_ok("Order #{$displayNumber} ka status: " . ucfirst($order['status']) . ($created ? " (Placed: {$created})" : '') . ".", [
                ['label' => 'My Orders', 'href' => 'orders.php'],
                ['label' => 'Track Order', 'href' => 'track-order.php'],
                ['label' => 'Invoice', 'href' => 'invoice.php?order_id=' . urlencode((string) $displayNumber)],
            ]);
        }

        $isLikelyOrderNumber = strlen($rawNumber) >= 5;
        $recentStmt = $pdo->prepare('SELECT id, order_number, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5');
        $recentStmt->execute([(int) $userId]);
        $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        $suggestions = [
            ['label' => 'My Orders', 'href' => 'orders.php'],
            ['label' => 'Track Order', 'href' => 'track-order.php'],
        ];
        foreach ($recent as $r) {
            $displayNumber = $r['order_number'] ?: $r['id'];
            $suggestions[] = [
                'label' => 'Order #' . $displayNumber,
                'href' => 'orders.php',
            ];
        }

        $msg = "Mujhe order #{$rawNumber} nahi mila. ";
        $msg .= "Please `orders.php` ya `track-order.php` se apna exact order number check karke bhejein.";
        if (!$isLikelyOrderNumber) {
            $msg .= " Aksar visible order numbers 5 se 8 digits ke hote hain, for example: 45872134.";
        }

        if ($recent) {
            $list = array_map(function ($r) {
                $created = $r['created_at'] ? date('M j', strtotime($r['created_at'])) : '';
                $displayNumber = $r['order_number'] ?: $r['id'];
                return "#" . $displayNumber . " (" . ucfirst((string) $r['status']) . ($created ? ", {$created}" : '') . ")";
            }, $recent);
            $msg .= " Aapke recent orders: " . implode(', ', $list) . ".";
        }

        reply_ok($msg, $suggestions);
    }

    reply_ok("Apna order number bhejein taake main status bata sakoon. Example: 45872134.", [
        ['label' => 'My Orders', 'href' => 'orders.php'],
        ['label' => 'Track Order', 'href' => 'track-order.php'],
    ]);
}

// Recently viewed / last product
if (preg_match('/\\b(last|latest|recent|recently)\\b.*\\b(view|viewed|product|item)\\b|\\b(last viewed product|recent product)\\b/i', $text)) {
    $product = get_recent_viewed_product($pdo, $userId ? (int) $userId : null);
    if ($product) {
        reply_ok("Aapka recent viewed product: " . build_product_summary($product), [
            ['label' => (string) $product['name'], 'href' => 'product-detail.php?id=' . (int) $product['id']],
            ['label' => 'More Products', 'href' => 'products.php'],
        ]);
    }

    reply_ok("Abhi mujhe aapka recent viewed product nahi mila. Pehle kisi product page ko open karein, phir main uski details ya similar products bata dunga.", [
        ['label' => 'Browse Products', 'href' => 'products.php'],
    ]);
}

// Returns / refunds
if (preg_match('/\\b(return|refund|replace|replacement)\\b/i', $text)) {
    reply_ok(
        "Return/Refund ke liye `Returns` page par request submit karein. Order number aur reason required hota hai.",
        [
            ['label' => 'Returns', 'href' => 'returns.php'],
            ['label' => 'Help Center', 'href' => 'help-center.php'],
        ]
    );
}

// Shipping info
if (preg_match('/\\b(shipping|delivery|ship)\\b/i', $text)) {
    reply_ok(
        "Shipping info ke liye `Shipping Info` page check karein. Delivery time seller aur product par depend karta hai.",
        [
            ['label' => 'Shipping Info', 'href' => 'shipping-info.php'],
            ['label' => 'Track Order', 'href' => 'track-order.php'],
        ]
    );
}

// Payments
if (preg_match('/\\b(payment|cod|cash|card)\\b/i', $text)) {
    reply_ok(
        "Payment methods checkout ke time show hote hain. Agar payment issue ho to `Contact` page par message bhej dein.",
        [
            ['label' => 'Checkout', 'href' => 'checkout.php'],
            ['label' => 'Contact', 'href' => 'contact.php'],
        ]
    );
}

// Store reviews / seller reviews
if (preg_match('/\\b(store|shop|seller)\\b.*\\b(review|reviews|rating)\\b|\\b(review|reviews|rating)\\b.*\\b(store|shop|seller)\\b/i', $text)) {
    $phrase = clean_lookup_phrase($message);
    $stores = find_store_matches($pdo, $phrase, 3);

    if (!$stores && $phrase === '') {
        $stmt = $pdo->query("
            SELECT
                u.id,
                u.shop_name,
                u.full_name,
                COUNT(DISTINCT p.id) AS product_count,
                COALESCE(AVG(r.rating), 0) AS avg_rating,
                COUNT(r.id) AS review_count
            FROM users u
            LEFT JOIN products p ON p.seller_id = u.id AND p.status = 'active'
            LEFT JOIN reviews r ON r.product_id = p.id
            WHERE u.user_type = 'seller' AND u.shop_name IS NOT NULL AND u.shop_name <> ''
            GROUP BY u.id, u.shop_name, u.full_name
            ORDER BY review_count DESC, avg_rating DESC, product_count DESC
            LIMIT 3
        ");
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($stores) {
        $top = $stores[0];
        $name = $top['shop_name'] ?: $top['full_name'];
        $reply = $name . " ka overall rating " . number_format((float) $top['avg_rating'], 1) . "/5 hai";
        $reply .= " based on " . (int) $top['review_count'] . " reviews across " . (int) $top['product_count'] . " active products.";
        if (count($stores) > 1) {
            $more = array_slice($stores, 1, 2);
            $parts = [];
            foreach ($more as $store) {
                $parts[] = ($store['shop_name'] ?: $store['full_name']) . " (" . number_format((float) $store['avg_rating'], 1) . "/5)";
            }
            if ($parts) {
                $reply .= " Related stores: " . implode(', ', $parts) . ".";
            }
        }
        reply_ok($reply, store_suggestions($stores));
    }

    reply_ok("Agar aap kisi specific store ke reviews dekhna chahte hain to store name bhejein, jaise: `reviews of TechWorld Store`.", [
        ['label' => 'Browse Stores', 'href' => 'products.php'],
    ]);
}

// Product price/details/reviews
if (preg_match('/\\b(price|cost|detail|details|about|review|reviews|rating|product)\\b/i', $text)) {
    $phrase = clean_lookup_phrase($message);
    $products = find_product_matches($pdo, $phrase, 3);

    if ($products) {
        $top = $products[0];

        if (preg_match('/\\bprice|cost\\b/i', $text)) {
            $reply = $top['name'] . " ki price " . format_price($top['price']) . " hai";
            if (!empty($top['shop_name'])) {
                $reply .= " at " . $top['shop_name'];
            }
            if ((int) $top['review_count'] > 0) {
                $reply .= ". Rating " . number_format((float) $top['avg_rating'], 1) . "/5 from " . (int) $top['review_count'] . " reviews";
            }
            $reply .= ".";
            reply_ok($reply, product_suggestions($products));
        }

        if (preg_match('/\\breview|reviews|rating\\b/i', $text)) {
            $reply = $top['name'] . " ki rating " . number_format((float) $top['avg_rating'], 1) . "/5 hai";
            $reply .= " based on " . (int) $top['review_count'] . " reviews.";
            if (!empty($top['shop_name'])) {
                $reply .= " Seller: " . $top['shop_name'] . ".";
            }
            reply_ok($reply, product_suggestions($products));
        }

        reply_ok(build_product_summary($top), product_suggestions($products));
    }
}

// Product recommendations
if (preg_match('/\\b(recommend|suggest|recommendation)\\b/i', $text)) {
    $keyword = '';
    if (preg_match('/\\bfor\\b\\s+(.+)$/i', $message, $m)) {
        $keyword = trim($m[1]);
    }
    if ($keyword === '' && preg_match('/\\b(recommend|suggest)\\b\\s+(.+)$/i', $message, $m)) {
        $keyword = trim($m[2]);
    }
    $keyword = mb_substr($keyword, 0, 60);

    if ($keyword !== '') {
        $products = find_product_matches($pdo, $keyword, 3);
        if ($products) {
            $reply = "Aapke liye `" . $keyword . "` se related kuch genuine options ye hain: ";
            $parts = [];
            foreach ($products as $product) {
                $parts[] = $product['name'] . " (" . format_price($product['price']) . ")";
            }
            $reply .= implode(', ', $parts) . ".";
            reply_ok($reply, product_suggestions($products));
        }
        reply_ok("`{$keyword}` se related products nahi mile. Aap search try karein.", [
            ['label' => 'Search Products', 'href' => 'products.php?search=' . urlencode($keyword)],
        ]);
    }

    $sessionKey = reco_session_key();
    $recommended = get_ai_recommendations($pdo, $userId ? (int) $userId : null, $sessionKey, null, 3);
    if ($recommended) {
        $reply = "Aapke liye kuch recommended products: ";
        $parts = [];
        foreach ($recommended as $product) {
            $parts[] = $product['name'] . " (" . format_price($product['price']) . ")";
        }
        $reply .= implode(', ', $parts) . ".";
        reply_ok($reply, product_suggestions($recommended));
    }

    reply_ok("Aap kis cheez ke liye recommendations chahte hain? Example: `recommend headphones` ya `recommend for sports`.");
}

// Vendor/admin help shortcut
if ($userId && $userType === 'seller' && preg_match('/\\b(seller|vendor|dashboard|earnings|sales)\\b/i', $text)) {
    reply_ok("Seller analytics aur earnings aap `Seller Dashboard` me dekh sakte hain.", [
        ['label' => 'Seller Dashboard', 'href' => 'seller/dashboard.php'],
    ]);
}

// Fallback with top products
$topProducts = $pdo->query(base_product_query() . " ORDER BY rv.review_count DESC, rv.avg_rating DESC, p.created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$fallback = "Main order status, returns, shipping, payments, product details, prices, store reviews aur recommendations me help kar sakta hoon. Aap product ya store ka naam bhejein, jaise `price of laptop`, `reviews of TechWorld Store`, ya `last viewed product`.";
$suggestions = [
    ['label' => 'Help Center', 'href' => 'help-center.php'],
    ['label' => 'My Orders', 'href' => 'orders.php'],
    ['label' => 'Products', 'href' => 'products.php'],
];
foreach ($topProducts as $product) {
    $suggestions[] = ['label' => (string) $product['name'], 'href' => 'product-detail.php?id=' . (int) $product['id']];
}

reply_ok($fallback, $suggestions);
