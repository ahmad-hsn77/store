<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/config.example.php';
}
$config = require $configFile;

if (!is_dir($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0775, true);
}

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    fail('Database connection failed: ' . $e->getMessage(), 500);
}

$input = input();
$path = request_path();

try {
    switch ($path) {
        case '/employee/register':
            register_employee();
            break;
        case '/login':
            login();
            break;
        case '/profile/information':
            require_user();
            profile_information();
            break;
        case '/add_center':
            require_user();
            add_center();
            break;
        case '/centers':
            require_user();
            centers_index();
            break;
        case '/centers/information':
            require_user();
            center_information();
            break;
        case '/centers/roles':
            require_user();
            center_roles();
            break;
        case '/show_employees':
            require_user();
            show_employees();
            break;
        case '/grant_role':
            require_user();
            grant_role();
            break;
        case '/center/employees':
            require_user();
            center_employees();
            break;
        case '/centers/products':
            require_user();
            product_store();
            break;
        case '/product/show/all':
            require_user();
            products_index();
            break;
        case '/product/categories/index':
            require_user();
            product_categories();
            break;
        case '/product/update':
            require_user();
            product_update();
            break;
        case '/product/delete':
            require_user();
            product_delete();
            break;
        case '/products/multidelete':
            require_user();
            products_multi_delete();
            break;
        case '/products/multistore':
            require_user();
            products_multi_store();
            break;
        case '/products/images/upload':
            require_user();
            products_images_upload();
            break;
        case '/product/image/delete':
            require_user();
            product_image_delete();
            break;
        case '/TodaySales':
            require_user();
            today_sales();
            break;
        case '/Center/DailySales':
            require_user();
            sales_by_period('day');
            break;
        case '/Center/MonthSales':
            require_user();
            sales_by_period('month');
            break;
        case '/Center/YearSales':
            require_user();
            sales_by_period('year');
            break;
        case '/Statistics':
            require_user();
            statistics();
            break;
        default:
            fail('Route not found: ' . $path, 404);
    }
} catch (PDOException $e) {
    fail($e->getMessage(), 500);
} catch (Throwable $e) {
    fail($e->getMessage(), 500);
}

function input(): array
{
    $json = json_decode(file_get_contents('php://input'), true);
    return array_replace_recursive(is_array($json) ? $json : [], $_POST);
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($scriptDir !== '' && $scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
        $uri = substr($uri, strlen($scriptDir));
    }
    $uri = '/' . trim($uri, '/');
    return $uri === '/index.php' ? '/' : $uri;
}

function ok(array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['error' => 0, 'message' => 'success'], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['error' => 1, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function cfg(string $key)
{
    global $config;
    return $config[$key] ?? null;
}

function in_value(string $key, $default = null)
{
    global $input;
    return $input[$key] ?? $default;
}

function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function require_user(): array
{
    $token = bearer_token();
    if (!$token) {
        fail('Unauthenticated', 401);
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE api_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        fail('Unauthenticated', 401);
    }
    return $user;
}

function now_string(): string
{
    return date('Y-m-d\TH:i:s.000000\Z');
}

function public_url(?string $path): string
{
    if (!$path) {
        return '';
    }
    if (preg_match('/^https?:\/\//', $path)) {
        return $path;
    }
    return rtrim((string) cfg('base_url'), '/') . '/' . ltrim($path, '/');
}

function save_upload(string $field, string $prefix): ?string
{
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    return save_uploaded_file($_FILES[$field], $prefix);
}

function save_uploaded_file(array $file, string $prefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = pathinfo($file['name'] ?? 'upload.bin', PATHINFO_EXTENSION);
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . ($ext ? '.' . $ext : '');
    $target = rtrim((string) cfg('upload_dir'), '/\\') . DIRECTORY_SEPARATOR . $name;
    move_uploaded_file($file['tmp_name'], $target);
    return 'uploads/' . $name;
}

function user_payload(array $user, ?string $token = null): array
{
    return [
        'user' => [[
            'id' => (int) $user['id'],
            'phone' => $user['phone'],
            'name' => $user['name'],
            'email' => $user['email'],
            'image' => ['url' => public_url($user['image'] ?? '')],
            'is_center_admin' => (int) ($user['is_center_admin'] ?? 0),
            'updated_at' => $user['updated_at'] ?? now_string(),
            'created_at' => $user['created_at'] ?? now_string(),
        ]],
        'roles' => roles_for_user((int) $user['id']),
        'access_token' => $token ?? $user['api_token'] ?? null,
    ];
}

function employee_payload(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'phone' => $user['phone'],
        'name' => $user['name'],
        'email' => $user['email'],
        'image' => ['url' => public_url($user['image'] ?? '')],
        'is_center_admin' => (int) ($user['is_center_admin'] ?? 0),
        'roles' => roles_for_user((int) $user['id']),
        'updated_at' => $user['updated_at'] ?? now_string(),
        'created_at' => $user['created_at'] ?? now_string(),
    ];
}

function customer_payload(array $user): array
{
    $roles = roles_for_user((int) $user['id']);
    $payload = [
        'id' => (int) $user['id'],
        'phone' => $user['phone'],
        'name' => $user['name'],
        'email' => $user['email'],
        'image' => public_url($user['image'] ?? ''),
        'token' => $user['api_token'] ?? '',
        'is_center_admin' => (int) ($user['is_center_admin'] ?? 0),
        'updated_at' => $user['updated_at'] ?? now_string(),
        'created_at' => $user['created_at'] ?? now_string(),
        'total' => count($roles),
    ];
    foreach ($roles as $i => $role) {
        $payload['role' . $i] = $role;
    }
    return $payload;
}

function roles_for_user(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT r.* FROM roles r INNER JOIN role_user ru ON ru.role_id = r.id WHERE ru.user_id = ? ORDER BY r.id'
    );
    $stmt->execute([$userId]);
    return array_map('role_payload', $stmt->fetchAll());
}

function role_payload(array $role): array
{
    return [
        'id' => (int) $role['id'],
        'center_id' => isset($role['center_id']) ? (int) $role['center_id'] : null,
        'name' => $role['name'],
        'role_name' => $role['name'],
        'guard_name' => $role['guard_name'] ?? 'api',
        'permissions' => [],
        'updated_at' => $role['updated_at'] ?? now_string(),
        'created_at' => $role['created_at'] ?? now_string(),
    ];
}

function center_payload(array $center): array
{
    return [
        'id' => (int) $center['id'],
        'owner_id' => (int) $center['owner_id'],
        'name' => $center['name'],
        'location' => $center['location'],
        'center_image' => $center['image'] ? [['id' => (int) $center['id'], 'url' => public_url($center['image'])]] : [],
        'updated_at' => $center['updated_at'] ?? now_string(),
        'created_at' => $center['created_at'] ?? now_string(),
    ];
}

function category_id(int $centerId, $category): ?int
{
    if ($category === null || $category === '') {
        return null;
    }
    if (is_numeric($category)) {
        $stmt = db()->prepare('SELECT id FROM categories WHERE id = ? AND center_id = ?');
        $stmt->execute([(int) $category, $centerId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
    }
    $name = trim((string) $category);
    $stmt = db()->prepare('SELECT id FROM categories WHERE center_id = ? AND name = ?');
    $stmt->execute([$centerId, $name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $stmt = db()->prepare('INSERT INTO categories (center_id, name) VALUES (?, ?)');
    $stmt->execute([$centerId, $name]);
    return (int) db()->lastInsertId();
}

function product_payload(array $product): array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$product['category_id']]);
    $category = $stmt->fetch();

    $stmt = db()->prepare('SELECT * FROM centers WHERE id = ?');
    $stmt->execute([$product['center_id']]);
    $center = $stmt->fetch();

    $stmt = db()->prepare('SELECT id, url FROM product_images WHERE product_id = ? ORDER BY id');
    $stmt->execute([$product['id']]);
    $images = array_map(function ($img) {
        return ['id' => (int) $img['id'], 'url' => public_url($img['url'])];
    }, $stmt->fetchAll());

    return [
        'id' => (int) $product['id'],
        'center_id' => (int) $product['center_id'],
        'name' => $product['name'],
        'description' => $product['description'],
        'price' => (string) $product['price'],
        'quantity' => (int) $product['quantity'],
        'code' => $product['code'],
        'unit' => $product['unit'],
        'category' => $category ? ['id' => (int) $category['id'], 'name' => $category['name']] : null,
        'center' => $center ? center_payload($center) : null,
        'images' => $images,
        'product_image' => $images,
        'updated_at' => $product['updated_at'] ?? now_string(),
        'created_at' => $product['created_at'] ?? now_string(),
    ];
}

function register_employee(): void
{
    $token = bin2hex(random_bytes(32));
    $image = save_upload('image', 'user');
    $stmt = db()->prepare(
        'INSERT INTO users (name, email, phone, password, image, api_token) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        in_value('name', ''),
        in_value('email', null),
        in_value('phone', ''),
        password_hash((string) in_value('password', ''), PASSWORD_BCRYPT),
        $image,
        $token,
    ]);
    $user = find_user((int) db()->lastInsertId());
    ok(user_payload($user, $token), 201);
}

function login(): void
{
    $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([in_value('phone', '')]);
    $user = $stmt->fetch();
    if (!$user || !password_verify((string) in_value('password', ''), $user['password'])) {
        fail('Invalid credentials', 401);
    }
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE users SET api_token = ? WHERE id = ?')->execute([$token, $user['id']]);
    $user = find_user((int) $user['id']);
    ok(user_payload($user, $token));
}

function find_user(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        fail('User not found', 404);
    }
    return $user;
}

function profile_information(): void
{
    $user = require_user();
    ok(user_payload($user));
}

function add_center(): void
{
    $user = require_user();
    $image = save_upload('image', 'center');
    $stmt = db()->prepare('INSERT INTO centers (owner_id, name, location, image) VALUES (?, ?, ?, ?)');
    $stmt->execute([(int) $user['id'], in_value('center_name', ''), in_value('center_location', ''), $image]);
    $centerId = (int) db()->lastInsertId();
    db()->prepare('UPDATE users SET is_center_admin = 1 WHERE id = ?')->execute([$user['id']]);
    seed_center_roles($centerId);
    $roleId = db()->prepare('SELECT id FROM roles WHERE center_id = ? AND name = ? LIMIT 1');
    $roleId->execute([$centerId, 'Manager']);
    $managerRoleId = $roleId->fetchColumn();
    if ($managerRoleId) {
        db()->prepare('INSERT IGNORE INTO role_user (user_id, role_id, center_id) VALUES (?, ?, ?)')
            ->execute([$user['id'], $managerRoleId, $centerId]);
    }
    ok(['center' => [center_payload(find_center($centerId))]], 201);
}

function seed_center_roles(int $centerId): void
{
    foreach (['Manager', 'Seller', 'Viewer'] as $name) {
        $stmt = db()->prepare('INSERT INTO roles (center_id, name) VALUES (?, ?)');
        $stmt->execute([$centerId, $name]);
    }
}

function find_center(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM centers WHERE id = ?');
    $stmt->execute([$id]);
    $center = $stmt->fetch();
    if (!$center) {
        fail('Center not found', 404);
    }
    return $center;
}

function centers_index(): void
{
    $stmt = db()->query('SELECT * FROM centers ORDER BY id DESC');
    ok(['center' => array_map('center_payload', $stmt->fetchAll())]);
}

function center_information(): void
{
    ok(['center' => [center_payload(find_center((int) in_value('center_id', 0)))]]);
}

function center_roles(): void
{
    $stmt = db()->prepare('SELECT * FROM roles WHERE center_id = ? ORDER BY id');
    $stmt->execute([(int) in_value('center_id', 0)]);
    ok(['roles' => array_map('role_payload', $stmt->fetchAll())]);
}

function show_employees(): void
{
    $stmt = db()->query('SELECT * FROM users ORDER BY id DESC');
    ok(['employees' => array_map('employee_payload', $stmt->fetchAll())]);
}

function grant_role(): void
{
    $stmt = db()->prepare('INSERT IGNORE INTO role_user (user_id, role_id, center_id) VALUES (?, ?, ?)');
    $stmt->execute([(int) in_value('emp_id'), (int) in_value('role_id'), (int) in_value('center_id')]);
    ok();
}

function center_employees(): void
{
    $stmt = db()->prepare(
        'SELECT DISTINCT u.* FROM users u INNER JOIN role_user ru ON ru.user_id = u.id WHERE ru.center_id = ? ORDER BY u.id'
    );
    $stmt->execute([(int) in_value('center_id')]);
    ok(['employees' => array_map('employee_payload', $stmt->fetchAll())]);
}

function product_store(): void
{
    $centerId = (int) in_value('center_id');
    $categoryId = category_id($centerId, in_value('category'));
    $stmt = db()->prepare(
        'INSERT INTO products (center_id, category_id, name, description, price, quantity, code, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $centerId,
        $categoryId,
        in_value('name', ''),
        in_value('description', ''),
        in_value('price', 0),
        in_value('quantity', 0),
        in_value('code', ''),
        in_value('unit', '1'),
    ]);
    $productId = (int) db()->lastInsertId();
    save_product_uploads($productId, 'image', 'product');
    ok(['product' => product_payload(find_product($productId))], 201);
}

function find_product(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        fail('Product not found', 404);
    }
    return $product;
}

function save_product_uploads(int $productId, string $field, string $prefix): void
{
    if (!isset($_FILES[$field])) {
        return;
    }
    $files = normalize_files($_FILES[$field]);
    foreach ($files as $file) {
        $path = save_uploaded_file($file, $prefix);
        if ($path) {
            db()->prepare('INSERT INTO product_images (product_id, url) VALUES (?, ?)')->execute([$productId, $path]);
        }
    }
}

function normalize_files(array $files): array
{
    if (!is_array($files['name'])) {
        return [$files];
    }
    $out = [];
    foreach ($files['name'] as $key => $name) {
        if (is_array($name)) {
            continue;
        }
        $out[] = [
            'name' => $name,
            'type' => $files['type'][$key] ?? null,
            'tmp_name' => $files['tmp_name'][$key] ?? null,
            'error' => $files['error'][$key] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$key] ?? 0,
        ];
    }
    return $out;
}

function products_index(): void
{
    $centerId = (int) in_value('center_id');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $params = [$centerId];
    $where = 'WHERE center_id = ?';
    if (in_value('category_id') !== null) {
        $where .= ' AND category_id = ?';
        $params[] = (int) in_value('category_id');
    }
    $count = db()->prepare("SELECT COUNT(*) FROM products $where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $lastPage = max(1, (int) ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;
    $stmt = db()->prepare("SELECT * FROM products $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $items = array_map('product_payload', $stmt->fetchAll());
    ok([
        'data' => $items,
        'products' => $items,
        'meta' => [
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
        ],
    ]);
}

function product_categories(): void
{
    $stmt = db()->prepare('SELECT id, name FROM categories WHERE center_id = ? ORDER BY name');
    $stmt->execute([(int) in_value('center_id')]);
    $categories = array_map(function ($r) {
        return ['id' => (int) $r['id'], 'name' => $r['name']];
    }, $stmt->fetchAll());
    ok(['Category' => $categories, 'categories' => $categories, 'data' => $categories]);
}

function product_update(): void
{
    $productId = (int) in_value('product_id');
    $product = find_product($productId);
    $categoryId = in_value('category') !== null ? category_id((int) $product['center_id'], in_value('category')) : $product['category_id'];
    $stmt = db()->prepare(
        'UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, quantity = ?, code = ?, unit = ? WHERE id = ?'
    );
    $stmt->execute([
        $categoryId,
        in_value('name', $product['name']),
        in_value('description', $product['description']),
        in_value('price', $product['price']),
        in_value('quantity', $product['quantity']),
        in_value('code', $product['code']),
        in_value('unit', $product['unit']),
        $productId,
    ]);
    save_product_uploads($productId, 'image', 'product');
    ok(['product' => product_payload(find_product($productId))]);
}

function product_delete(): void
{
    db()->prepare('DELETE FROM products WHERE id = ?')->execute([(int) in_value('product_id')]);
    ok();
}

function products_multi_delete(): void
{
    $ids = in_value('productsIds', []);
    if (!is_array($ids) || count($ids) === 0) {
        ok();
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    db()->prepare("DELETE FROM products WHERE id IN ($placeholders)")->execute(array_map('intval', $ids));
    ok();
}

function products_multi_store(): void
{
    $products = in_value('products', []);
    if (!is_array($products)) {
        fail('products must be an array');
    }
    $saved = [];
    foreach ($products as $item) {
        if (!is_array($item)) {
            continue;
        }
        $centerId = (int) ($item['center_id'] ?? 0);
        $categoryId = category_id($centerId, $item['category'] ?? null);
        $existing = null;
        if (!empty($item['product_number']) && is_numeric($item['product_number'])) {
            $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND center_id = ?');
            $stmt->execute([(int) $item['product_number'], $centerId]);
            $existing = $stmt->fetch();
        }
        if (!$existing && !empty($item['product_number'])) {
            $stmt = db()->prepare('SELECT * FROM products WHERE product_number = ? AND center_id = ?');
            $stmt->execute([(string) $item['product_number'], $centerId]);
            $existing = $stmt->fetch();
        }
        if ($existing) {
            db()->prepare(
                'UPDATE products SET category_id = ?, product_number = ?, name = ?, description = ?, price = ?, quantity = ?, code = ?, unit = ? WHERE id = ?'
            )->execute([
                $categoryId,
                $item['product_number'] ?? $existing['product_number'],
                $item['name'] ?? $existing['name'],
                $item['description'] ?? $existing['description'],
                $item['price'] ?? $existing['price'],
                $item['quantity'] ?? $existing['quantity'],
                $item['code'] ?? $existing['code'],
                $item['unit'] ?? $existing['unit'],
                $existing['id'],
            ]);
            $saved[] = product_payload(find_product((int) $existing['id']));
        } else {
            db()->prepare(
                'INSERT INTO products (center_id, category_id, product_number, name, description, price, quantity, code, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $centerId,
                $categoryId,
                $item['product_number'] ?? null,
                $item['name'] ?? '',
                $item['description'] ?? '',
                $item['price'] ?? 0,
                $item['quantity'] ?? 0,
                $item['code'] ?? '',
                $item['unit'] ?? '1',
            ]);
            $saved[] = product_payload(find_product((int) db()->lastInsertId()));
        }
    }
    ok(['products' => $saved], 201);
}

function products_images_upload(): void
{
    if (!isset($_FILES['images'])) {
        ok();
    }
    $centerId = (int) in_value('center_id');
    foreach ($_FILES['images']['name'] as $productNumber => $names) {
        $product = find_product_by_number($centerId, (string) $productNumber);
        if (!$product || !is_array($names)) {
            continue;
        }
        foreach ($names as $i => $name) {
            $file = [
                'name' => $name,
                'type' => $_FILES['images']['type'][$productNumber][$i] ?? null,
                'tmp_name' => $_FILES['images']['tmp_name'][$productNumber][$i] ?? null,
                'error' => $_FILES['images']['error'][$productNumber][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['images']['size'][$productNumber][$i] ?? 0,
            ];
            $path = save_uploaded_file($file, 'product');
            if ($path) {
                db()->prepare('INSERT INTO product_images (product_id, url) VALUES (?, ?)')->execute([$product['id'], $path]);
            }
        }
    }
    ok();
}

function find_product_by_number(int $centerId, string $number): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE center_id = ? AND (product_number = ? OR id = ?) LIMIT 1');
    $stmt->execute([$centerId, $number, is_numeric($number) ? (int) $number : 0]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function product_image_delete(): void
{
    db()->prepare('DELETE FROM product_images WHERE id = ?')->execute([(int) in_value('id')]);
    ok();
}

function today_sales(): void
{
    ok(['products' => invoices_for_center((int) in_value('id'), 'day')]);
}

function sales_by_period(string $period): void
{
    $key = $period === 'day' ? 'todaySales' : 'dailySales';
    ok([$key => invoices_for_center((int) in_value('id'), $period)]);
}

function invoices_for_center(int $centerId, string $period): array
{
    $where = 'WHERE i.center_id = ?';
    if ($period === 'day') {
        $where .= ' AND DATE(i.created_at) = CURDATE()';
    } elseif ($period === 'month') {
        $where .= ' AND YEAR(i.created_at) = YEAR(CURDATE()) AND MONTH(i.created_at) = MONTH(CURDATE())';
    } elseif ($period === 'year') {
        $where .= ' AND YEAR(i.created_at) = YEAR(CURDATE())';
    }
    $stmt = db()->prepare("SELECT i.*, u.name, u.email, u.phone, u.image, u.api_token, u.is_center_admin, u.created_at AS user_created_at, u.updated_at AS user_updated_at FROM invoices i INNER JOIN users u ON u.id = i.customer_id $where ORDER BY i.created_at DESC");
    $stmt->execute([$centerId]);
    return array_map('invoice_payload', $stmt->fetchAll());
}

function invoice_payload(array $invoice): array
{
    $customer = [
        'id' => $invoice['customer_id'],
        'name' => $invoice['name'],
        'email' => $invoice['email'],
        'phone' => $invoice['phone'],
        'image' => $invoice['image'],
        'api_token' => $invoice['api_token'],
        'is_center_admin' => $invoice['is_center_admin'],
        'created_at' => $invoice['user_created_at'],
        'updated_at' => $invoice['user_updated_at'],
    ];
    $stmt = db()->prepare('SELECT ii.*, p.* FROM invoice_items ii INNER JOIN products p ON p.id = ii.product_id WHERE ii.invoice_id = ?');
    $stmt->execute([$invoice['id']]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = ['quantity' => (int) $row['quantity'], 'product' => product_payload($row)];
    }
    return [
        'id' => (int) $invoice['id'],
        'total_price' => (string) $invoice['total_price'],
        'created_at' => $invoice['created_at'],
        'updated_at' => $invoice['updated_at'],
        'items' => $items,
        'customer' => customer_payload($customer),
    ];
}

function statistics(): void
{
    $day = sales_sum('DATE(created_at) = CURDATE()');
    $month = sales_sum('YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())');
    $year = sales_sum('YEAR(created_at) = YEAR(CURDATE())');
    ok([
        'DaySales' => (string) $day,
        'MonthSales' => (string) $month,
        'YearSales' => (string) $year,
        'CentersCount' => (int) db()->query('SELECT COUNT(*) FROM centers')->fetchColumn(),
        'EmployeesCount' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'BuyersCount' => (int) db()->query('SELECT COUNT(DISTINCT customer_id) FROM invoices')->fetchColumn(),
    ]);
}

function sales_sum(string $where): float
{
    return (float) db()->query("SELECT COALESCE(SUM(total_price), 0) FROM invoices WHERE $where")->fetchColumn();
}
