<?php
declare(strict_types=1);

session_start();
require dirname(__DIR__) . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_response(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        api_response(400, ['error' => 'ข้อมูลคำขอต้องเป็น JSON ที่ถูกต้อง']);
    }
    return $data;
}

function require_csrf(): void
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '');
    if (!isset($_SESSION['csrf']) || !is_string($provided) || !hash_equals($_SESSION['csrf'], $provided)) {
        api_response(419, ['error' => 'เซสชันหมดอายุแล้ว โปรดโหลดหน้าเว็บใหม่แล้วลองอีกครั้ง']);
    }
}

function positive_id(mixed $value, string $field): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        $labels = ['id' => 'รหัสสินค้า', 'category_id' => 'หมวดหมู่', 'supplier_id' => 'ผู้จัดจำหน่าย'];
        api_response(422, ['error' => ($labels[$field] ?? 'รหัส') . 'ต้องเป็นตัวเลขจำนวนเต็มบวก']);
    }
    return $id;
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $length = preg_match_all('/./us', $value);
    return $length === false ? strlen($value) : $length;
}

function product_data(array $body): array
{
    if (!isset($body['product_name']) || !is_string($body['product_name'])) {
        api_response(422, ['error' => 'โปรดระบุชื่อสินค้า']);
    }
    if (!isset($body['unit']) || !is_string($body['unit'])) {
        api_response(422, ['error' => 'โปรดระบุหน่วยนับ']);
    }
    $name = trim($body['product_name']);
    $unit = trim($body['unit']);
    $categoryId = positive_id($body['category_id'] ?? null, 'category_id');
    $supplierId = positive_id($body['supplier_id'] ?? null, 'supplier_id');
    $rawPrice = $body['price'] ?? null;

    if ($name === '' || text_length($name) > 30) {
        api_response(422, ['error' => 'โปรดระบุชื่อสินค้าไม่เกิน 30 ตัวอักษร']);
    }
    if ($unit === '' || text_length($unit) > 30) {
        api_response(422, ['error' => 'โปรดระบุหน่วยนับไม่เกิน 30 ตัวอักษร']);
    }
    if (!is_numeric($rawPrice) || (float)$rawPrice < 0 || !is_finite((float)$rawPrice)) {
        api_response(422, ['error' => 'ราคาต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป']);
    }

    return [$name, $supplierId, $categoryId, $unit, (float)$rawPrice];
}

try {
    if (!$pdo instanceof PDO) {
        api_response(503, ['error' => 'เชื่อมต่อฐานข้อมูลไม่ได้ โปรดตรวจการตั้งค่าและนำเข้าไฟล์ฐานข้อมูล Northwind']);
    }

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $rawId = $_GET['id'] ?? null;

    if ($method === 'GET') {
        if ($rawId !== null) {
            $id = positive_id($rawId, 'id');
            $q = $pdo->prepare('SELECT p.i_ProductID AS id, p.c_ProductName AS product_name, p.i_SupplierID AS supplier_id, s.c_SupplierName AS supplier_name, p.i_CategoryID AS category_id, c.c_CategoryName AS category_name, p.c_Unit AS unit, p.i_Price AS price FROM tb_products p LEFT JOIN tb_categories c ON c.i_CategoryID=p.i_CategoryID LEFT JOIN tb_suppliers s ON s.i_SupplierID=p.i_SupplierID WHERE p.i_ProductID=?');
            $q->execute([$id]);
            $product = $q->fetch();
            if (!$product) api_response(404, ['error' => 'ไม่พบสินค้านี้']);
            api_response(200, ['data' => $product]);
        }

        $search = trim((string)($_GET['q'] ?? ''));
        $sql = 'SELECT p.i_ProductID AS id, p.c_ProductName AS product_name, p.i_SupplierID AS supplier_id, s.c_SupplierName AS supplier_name, p.i_CategoryID AS category_id, c.c_CategoryName AS category_name, p.c_Unit AS unit, p.i_Price AS price FROM tb_products p LEFT JOIN tb_categories c ON c.i_CategoryID=p.i_CategoryID LEFT JOIN tb_suppliers s ON s.i_SupplierID=p.i_SupplierID';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE p.c_ProductName LIKE ? OR c.c_CategoryName LIKE ? OR s.c_SupplierName LIKE ?';
            $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
        }
        $sql .= ' ORDER BY p.i_ProductID DESC LIMIT 250';
        $q = $pdo->prepare($sql);
        $q->execute($params);
        api_response(200, ['data' => $q->fetchAll()]);
    }

    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, DELETE');
        api_response(405, ['error' => 'ไม่อนุญาตให้ใช้วิธีการเรียกนี้']);
    }
    require_csrf();

    if ($method === 'POST' || $method === 'PUT') {
        $data = product_data(request_body());
        $references = $pdo->prepare('SELECT (SELECT COUNT(*) FROM tb_categories WHERE i_CategoryID=?) AS category_exists, (SELECT COUNT(*) FROM tb_suppliers WHERE i_SupplierID=?) AS supplier_exists');
        $references->execute([$data[2], $data[1]]);
        $validReferences = $references->fetch();
        if ((int)$validReferences['category_exists'] === 0) api_response(422, ['error' => 'โปรดเลือกหมวดหมู่ที่มีอยู่ในฐานข้อมูล']);
        if ((int)$validReferences['supplier_exists'] === 0) api_response(422, ['error' => 'โปรดเลือกผู้จัดจำหน่ายที่มีอยู่ในฐานข้อมูล']);

        if ($method === 'POST') {
            $q = $pdo->prepare('INSERT INTO tb_products (c_ProductName,i_SupplierID,i_CategoryID,c_Unit,i_Price) VALUES (?,?,?,?,?)');
            $q->execute($data);
            $id = (int)$pdo->lastInsertId();
            api_response(201, ['message' => 'เพิ่มสินค้าเรียบร้อยแล้ว', 'data' => ['id' => $id]]);
        }

        $id = positive_id($rawId, 'id');
        $exists = $pdo->prepare('SELECT COUNT(*) FROM tb_products WHERE i_ProductID=?');
        $exists->execute([$id]);
        if ((int)$exists->fetchColumn() === 0) api_response(404, ['error' => 'ไม่พบสินค้านี้']);
        $q = $pdo->prepare('UPDATE tb_products SET c_ProductName=?, i_SupplierID=?, i_CategoryID=?, c_Unit=?, i_Price=? WHERE i_ProductID=?');
        $q->execute([...$data, $id]);
        api_response(200, ['message' => 'แก้ไขสินค้าเรียบร้อยแล้ว', 'data' => ['id' => $id]]);
    }

    $id = positive_id($rawId, 'id');
    $exists = $pdo->prepare('SELECT COUNT(*) FROM tb_products WHERE i_ProductID=?');
    $exists->execute([$id]);
    if ((int)$exists->fetchColumn() === 0) api_response(404, ['error' => 'ไม่พบสินค้านี้']);
    $check = $pdo->prepare('SELECT COUNT(*) FROM tb_orderdetails WHERE i_ProductID=?');
    $check->execute([$id]);
    if ((int)$check->fetchColumn() > 0) api_response(409, ['error' => 'สินค้านี้อยู่ในประวัติคำสั่งซื้อ จึงไม่สามารถลบได้']);
    $q = $pdo->prepare('DELETE FROM tb_products WHERE i_ProductID=?');
    $q->execute([$id]);
    api_response(200, ['message' => 'ลบสินค้าเรียบร้อยแล้ว', 'data' => ['id' => $id]]);
} catch (PDOException $e) {
    error_log('Products API database error: ' . $e->getMessage());
    api_response(500, ['error' => 'ฐานข้อมูลดำเนินการไม่สำเร็จ โปรดตรวจสอบการตั้งค่าฐานข้อมูล Northwind']);
} catch (Throwable $e) {
    error_log('Products API error: ' . $e->getMessage());
    api_response(500, ['error' => 'เกิดข้อผิดพลาดที่ไม่คาดคิดบนเซิร์ฟเวอร์']);
}
