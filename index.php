<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'products', 'product-form', 'customers', 'orders', 'categories', 'suppliers'];
if (!in_array($page, $allowed, true)) $page = 'dashboard';
$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
$thaiDate = date('d/m/') . ((int)date('Y') + 543);
$formatThaiDate = static function ($value): string {
    $timestamp = strtotime((string)$value);
    return $timestamp === false ? (string)$value : date('d/m/', $timestamp) . ((int)date('Y', $timestamp) + 543);
};

$stats = ['products' => 0, 'customers' => 0, 'orders' => 0, 'categories' => 0];
try {
    foreach ($stats as $table => $_) $stats[$table] = (int)$pdo->query('SELECT COUNT(*) FROM ' . ['products'=>'tb_products','customers'=>'tb_customers','orders'=>'tb_orders','categories'=>'tb_categories'][$table])->fetchColumn();
} catch (Throwable $e) { $error = 'เชื่อมต่อฐานข้อมูลไม่ได้ โปรดตรวจการตั้งค่าใน includes/db.php และนำเข้าไฟล์ฐานข้อมูล Northwind'; }

$search = trim($_GET['q'] ?? '');
$rows = [];
$tableTitles = ['products'=>'สินค้า','customers'=>'ลูกค้า','orders'=>'คำสั่งซื้อ','categories'=>'หมวดหมู่','suppliers'=>'ผู้จัดจำหน่าย'];
if ($page !== 'dashboard' && $page !== 'product-form') {
    try {
        if ($page === 'products') {
            $sql = 'SELECT p.i_ProductID AS id,p.c_ProductName AS name,p.c_Unit AS detail,p.i_Price AS price,c.c_CategoryName AS group_name,s.c_SupplierName AS secondary FROM tb_products p LEFT JOIN tb_categories c ON c.i_CategoryID=p.i_CategoryID LEFT JOIN tb_suppliers s ON s.i_SupplierID=p.i_SupplierID';
            $params = [];
            if ($search !== '') { $sql .= ' WHERE p.c_ProductName LIKE ? OR c.c_CategoryName LIKE ?'; $params = ["%$search%", "%$search%"]; }
            $sql .= ' ORDER BY p.i_ProductID DESC LIMIT 250';
            $q = $pdo->prepare($sql); $q->execute($params); $rows = $q->fetchAll();
        } elseif ($page === 'customers') {
            $sql='SELECT i_customerid AS id,c_customername AS name,c_contactname AS detail,CONCAT(c_city, ", ", c_country) AS group_name,c_country AS secondary FROM tb_customers'; $params=[];
            if ($search!=='') {$sql.=' WHERE c_customername LIKE ? OR c_contactname LIKE ? OR c_country LIKE ?';$params=["%$search%","%$search%","%$search%"];}
            $sql.=' ORDER BY c_customername LIMIT 250';$q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();
        } elseif ($page === 'orders') {
            $sql='SELECT o.i_OrderID AS id,c.c_customername AS name,CONCAT(e.c_FirstName," ",e.c_LastName) AS detail,o.c_OrderDate AS group_name,s.c_ShipperName AS secondary FROM tb_orders o LEFT JOIN tb_customers c ON c.i_customerid=o.i_CustomerID LEFT JOIN tb_employees e ON e.i_EmployeeID=o.i_EmployeeID LEFT JOIN tb_shippers s ON s.i_ShipperID=o.i_ShipperID'; $params=[];
            if ($search!=='') {$sql.=' WHERE CAST(o.i_OrderID AS CHAR) LIKE ? OR c.c_customername LIKE ?';$params=["%$search%","%$search%"];}
            $sql.=' ORDER BY o.i_OrderID DESC LIMIT 250';$q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();
        } elseif ($page === 'categories') {
            $sql='SELECT c.i_CategoryID AS id,c.c_CategoryName AS name,c.c_Description AS detail,COUNT(p.i_ProductID) AS group_name,"Product category" AS secondary FROM tb_categories c LEFT JOIN tb_products p ON p.i_CategoryID=c.i_CategoryID';$params=[];
            if ($search!=='') {$sql.=' WHERE c.c_CategoryName LIKE ?';$params=["%$search%"];}
            $sql.=' GROUP BY c.i_CategoryID ORDER BY c.i_CategoryName';$q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();
        } else {
            $sql='SELECT s.i_SupplierID AS id,s.c_SupplierName AS name,s.c_ContactName AS detail,CONCAT(s.c_City,", ",s.c_Country) AS group_name,s.c_Phone AS secondary FROM tb_suppliers s';$params=[];
            if ($search!=='') {$sql.=' WHERE s.c_SupplierName LIKE ? OR s.c_Country LIKE ?';$params=["%$search%","%$search%"];}
            $sql.=' ORDER BY s.c_SupplierName';$q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();
        }
    } catch (Throwable $e) { $error = 'โหลดข้อมูลไม่ได้ โปรดนำเข้าไฟล์ฐานข้อมูล Northwind ก่อน'; }
}
$recentOrders=[]; $topProducts=[];
if ($page==='dashboard' && !$error) {
    try {
        $recentOrders=$pdo->query('SELECT o.i_OrderID AS id,c.c_customername AS customer,o.c_OrderDate AS date FROM tb_orders o LEFT JOIN tb_customers c ON c.i_customerid=o.i_CustomerID ORDER BY o.i_OrderID DESC LIMIT 5')->fetchAll();
        $topProducts=$pdo->query('SELECT p.c_ProductName AS name,SUM(od.i_Quantity) AS units FROM tb_products p JOIN tb_orderdetails od ON od.i_ProductID=p.i_ProductID GROUP BY p.i_ProductID ORDER BY units DESC LIMIT 5')->fetchAll();
    } catch (Throwable $e) { $error='โปรดนำเข้าไฟล์ฐานข้อมูล Northwind เพื่อแสดงข้อมูลภาพรวม'; }
}
$editProductId = in_array($page, ['products', 'product-form'], true) ? max(0, (int)($_GET['edit'] ?? 0)) : 0;
$categories=[];$suppliers=[];
if (in_array($page, ['products', 'product-form'], true)) { try {$categories=$pdo->query('SELECT i_CategoryID,c_CategoryName FROM tb_categories ORDER BY c_CategoryName')->fetchAll();$suppliers=$pdo->query('SELECT i_SupplierID,c_SupplierName FROM tb_suppliers ORDER BY c_SupplierName')->fetchAll();}catch(Throwable $e){} }
if ($page === 'product-form'):
?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $editProductId > 0 ? 'แก้ไขข้อมูลสินค้า' : 'เพิ่มข้อมูลสินค้า' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/product-form.css" rel="stylesheet">
</head>
<body class="product-form-page">
  <main class="card product-card p-4 p-md-5">
    <div class="text-center mb-4">
      <h1 class="product-title"><?= $editProductId > 0 ? 'แก้ไขข้อมูลสินค้า' : 'เพิ่มข้อมูลสินค้า' ?></h1>
      <p class="text-muted mb-0">กรอกรายละเอียดสินค้า</p>
    </div>
    <div id="formFeedback" aria-live="polite">
      <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif ?>
    </div>
    <form method="post" id="productForm" class="product-entry-form">
      <?= csrf_field() ?>
      <input type="hidden" name="product_id" value="<?= $editProductId ?>">
      <div class="mb-3">
        <label for="productName" class="form-label">ชื่อสินค้า</label>
        <input id="productName" type="text" name="product_name" class="form-control" maxlength="30" placeholder="กรอกชื่อสินค้า" required>
      </div>
      <div class="mb-3">
        <label for="productCategory" class="form-label">หมวดหมู่สินค้า</label>
        <select id="productCategory" name="category_id" class="form-select" required>
          <option value="">-- เลือกหมวดหมู่ --</option>
          <?php foreach ($categories as $category): ?><option value="<?= (int)$category['i_CategoryID'] ?>"><?= h($category['c_CategoryName']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="mb-3">
        <label for="productSupplier" class="form-label">ผู้จัดจำหน่าย</label>
        <select id="productSupplier" name="supplier_id" class="form-select" required>
          <option value="">-- เลือกผู้จัดจำหน่าย --</option>
          <?php foreach ($suppliers as $supplier): ?><option value="<?= (int)$supplier['i_SupplierID'] ?>"><?= h($supplier['c_SupplierName']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="mb-3">
        <label for="productUnit" class="form-label">หน่วยนับ</label>
        <input id="productUnit" type="text" name="unit" class="form-control" maxlength="30" placeholder="เช่น 12 ขวด" required>
      </div>
      <div class="mb-4">
        <label for="productPrice" class="form-label">ราคา (ดอลลาร์สหรัฐ)</label>
        <input id="productPrice" type="number" name="price" class="form-control" step="0.01" min="0" placeholder="0.00" required>
      </div>
      <button type="submit" class="btn btn-primary w-100" id="saveProductButton">บันทึกสินค้า</button>
      <a class="btn btn-light w-100 mt-2" href="?page=products">กลับไปหน้ารายการสินค้า</a>
    </form>
  </main>
  <script src="assets/app.js"></script>
</body>
</html>
<?php exit; endif; ?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h($page==='dashboard'?'ภาพรวม':$tableTitles[$page]) ?> · Northstar</title><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"></head><body>
<aside class="sidebar"><a class="brand" href="?page=dashboard"><span class="brand-mark">N</span><span>northstar<small>พื้นที่จัดการร้านค้า</small></span></a><div class="nav-label">พื้นที่ทำงาน</div><nav><?php foreach(['dashboard'=>'ภาพรวม','products'=>'สินค้า','orders'=>'คำสั่งซื้อ','customers'=>'ลูกค้า','categories'=>'หมวดหมู่','suppliers'=>'ผู้จัดจำหน่าย'] as $key=>$label): ?><a class="nav-link <?= $page===$key?'active':'' ?>" href="?page=<?= $key ?>"><span class="nav-icon"><?= icon($key) ?></span><?= h($label) ?><?php if($key==='orders' && $stats['orders']): ?><span class="nav-count"><?= $stats['orders'] ?></span><?php endif ?></a><?php endforeach ?></nav><div class="sidebar-bottom"><div class="help-card"><div class="help-icon">✦</div><strong>Northwind โดยสรุป</strong><p>จัดการสินค้าและกิจกรรมของลูกค้าได้ในที่เดียว</p></div><div class="profile"><div class="avatar">WH</div><div><strong>วาริษรา หอมรื่น</strong><small>ผู้ดูแลระบบ</small></div><span class="dots">···</span></div></div></aside>
<main class="main"><header class="topbar"><div class="crumb">พื้นที่ทำงาน <span>/</span> <b><?= h($page==='dashboard'?'ภาพรวม':$tableTitles[$page]) ?></b></div><div class="top-actions"><span class="date-label"><?= $thaiDate ?></span><span class="top-avatar">W</span></div></header><div class="content">
<?php if($error): ?><div class="notice error"><?= h($error) ?></div><?php endif ?><?php if($success): ?><div class="notice success"><?= h($success) ?></div><?php endif ?>
<?php if($page==='dashboard'): ?>
<div class="page-heading"><div><div class="eyebrow">ภาพรวมธุรกิจ</div><h1>สวัสดีตอนเช้า วาริษรา <span class="wave">✦</span></h1><p>สรุปความเคลื่อนไหวในระบบ Northwind ของคุณ</p></div><a href="?page=product-form" class="button primary">＋ เพิ่มสินค้า</a></div>
<section class="stats-grid"><article class="stat-card"><div class="stat-top"><span>สินค้าทั้งหมด</span><span class="stat-icon blue">▤</span></div><strong><?= number_format($stats['products']) ?></strong><small><i class="live-dot"></i> สินค้าในแค็ตตาล็อก</small><div class="spark spark-blue"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></article><article class="stat-card"><div class="stat-top"><span>ลูกค้า</span><span class="stat-icon purple">♙</span></div><strong><?= number_format($stats['customers']) ?></strong><small>จากทุกพื้นที่</small><div class="spark spark-purple"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></article><article class="stat-card"><div class="stat-top"><span>คำสั่งซื้อที่ดำเนินการแล้ว</span><span class="stat-icon green">↗</span></div><strong><?= number_format($stats['orders']) ?></strong><small>บันทึกใน Northwind</small><div class="spark spark-green"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></article><article class="stat-card"><div class="stat-top"><span>หมวดหมู่</span><span class="stat-icon orange">▦</span></div><strong><?= number_format($stats['categories']) ?></strong><small>กลุ่มสินค้า</small><div class="spark spark-orange"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></article></section>
<section class="dashboard-grid"><article class="panel"><div class="panel-heading"><div><h2>คำสั่งซื้อล่าสุด</h2><p>รายการล่าสุดจากลูกค้า</p></div><a class="text-link" href="?page=orders">ดูทั้งหมด <span>↗</span></a></div><div class="table-wrap"><table><thead><tr><th>คำสั่งซื้อ</th><th>ลูกค้า</th><th>วันที่สั่งซื้อ</th><th>สถานะ</th></tr></thead><tbody><?php foreach($recentOrders as $order): ?><tr><td><a class="order-link" href="?page=orders">#<?= h($order['id']) ?></a></td><td><div class="customer-cell"><span class="mini-avatar"><?= h(initial($order['customer']??'?')) ?></span><?= h($order['customer']??'ไม่ทราบชื่อลูกค้า') ?></div></td><td><?= h($formatThaiDate($order['date'])) ?></td><td><span class="pill paid"><i></i>ดำเนินการแล้ว</span></td></tr><?php endforeach ?><?php if(!$recentOrders): ?><tr><td colspan="4" class="empty">ไม่พบคำสั่งซื้อ</td></tr><?php endif ?></tbody></table></div></article><article class="panel top-panel"><div class="panel-heading"><div><h2>สินค้ายอดนิยม</h2><p>เรียงตามจำนวนที่ขายได้</p></div><span class="tiny-pill">ทุกช่วงเวลา</span></div><div class="product-rank-list"><?php $max=(int)($topProducts[0]['units']??1); foreach($topProducts as $i=>$product): ?><div class="rank-item"><div class="rank-number">0<?= $i+1 ?></div><div class="rank-info"><div><strong><?= h($product['name']) ?></strong><span><?= number_format((int)$product['units']) ?> ชิ้น</span></div><div class="rank-track"><i style="width:<?= min(100,round((int)$product['units']/$max*100)) ?>%"></i></div></div></div><?php endforeach ?><?php if(!$topProducts): ?><div class="empty">รายละเอียดคำสั่งซื้อจะแสดงที่นี่</div><?php endif ?></div><a class="panel-foot-link" href="?page=products">ดูแค็ตตาล็อกสินค้า <span>→</span></a></article></section>
<div class="bottom-note"><span>✦</span><span><b>ระบบ Northwind ของคุณพร้อมใช้งานแล้ว</b> จัดการสินค้า ดูคำสั่งซื้อ และค้นหาข้อมูลลูกค้าได้สะดวก</span><a href="?page=products">เปิดแค็ตตาล็อกสินค้า →</a></div>
<?php else: ?><div class="page-heading"><div><div class="eyebrow">รายการข้อมูล Northwind</div><h1><?= h($tableTitles[$page]) ?></h1><p><?= ['products'=>'จัดการสินค้า ราคา และผู้จัดจำหน่าย','customers'=>'ดูบัญชีลูกค้าและข้อมูลติดต่อ','orders'=>'ตรวจสอบประวัติคำสั่งซื้อและการจัดส่ง','categories'=>'ดูสินค้าแยกตามหมวดหมู่','suppliers'=>'จัดการข้อมูลผู้จัดจำหน่ายและช่องทางติดต่อ'][$page] ?></p></div><?php if($page==='products'): ?><a href="?page=product-form" class="button primary">＋ เพิ่มสินค้า</a><?php endif ?></div>
<section class="panel directory-panel"><div class="directory-toolbar"><div class="result-label"><b data-result-count><?= count($rows) ?></b> รายการ <span>·</span> <?= h($tableTitles[$page]) ?></div><form class="search-form" method="get"><input type="hidden" name="page" value="<?= h($page) ?>"><span>⌕</span><input name="q" value="<?= h($search) ?>" placeholder="ค้นหา <?= h($tableTitles[$page]) ?>..."><button>ค้นหา</button></form></div><div class="table-wrap"><table class="directory-table"><thead><tr><th><?= $page==='orders'?'เลขที่คำสั่งซื้อ':($page==='categories'?'รหัสหมวดหมู่':['products'=>'รหัสสินค้า','customers'=>'รหัสลูกค้า','orders'=>'เลขที่คำสั่งซื้อ','categories'=>'รหัสหมวดหมู่','suppliers'=>'รหัสผู้จัดจำหน่าย'][$page]) ?></th><th><?= $page==='orders'?'ลูกค้า':($page==='products'?'สินค้า':($page==='categories'?'หมวดหมู่':($page==='suppliers'?'ผู้จัดจำหน่าย':'ลูกค้า'))) ?></th><th><?= $page==='orders'?'พนักงาน':($page==='products'?'หน่วยนับ':($page==='categories'?'คำอธิบาย':'ผู้ติดต่อ')) ?></th><th><?= $page==='orders'?'วันที่สั่งซื้อ':($page==='products'?'หมวดหมู่':'ที่ตั้ง') ?></th><th><?= $page==='products'?'ราคา':($page==='orders'?'จัดส่งโดย':($page==='categories'?'จำนวนสินค้า':'รายละเอียด')) ?></th><?php if($page==='products'): ?><th></th><?php endif ?></tr></thead><tbody<?= $page==='products'?' data-products-api':'' ?>><?php foreach($rows as $row): ?><tr><td><span class="record-id">#<?= h($row['id']) ?></span></td><td><div class="record-name"><span class="record-avatar hue-<?= (int)$row['id']%5 ?>"><?= h(initial($row['name']??'?',0,1)) ?></span><strong><?= h($row['name']) ?></strong></div></td><td><?= h($row['detail']??'—') ?></td><td><?= $page==='orders'?h($formatThaiDate($row['group_name'])):h($row['group_name']??'—') ?></td><td><?php if($page==='products'): ?><span class="price">$<?= number_format((float)$row['price'],2) ?></span><?php elseif($page==='categories'): ?><span class="pill neutral"><?= h($row['group_name']) ?> รายการ</span><?php else: ?><?= h($row['secondary']??'—') ?><?php endif ?></td><?php if($page==='products'): ?><td><div class="row-actions"><a href="?page=product-form&edit=<?= (int)$row['id'] ?>" title="แก้ไขสินค้า">✎</a><button type="button" class="delete-product" data-product-id="<?= (int)$row['id'] ?>" title="ลบสินค้า" aria-label="ลบสินค้า">⌫</button></div></td><?php endif ?></tr><?php endforeach ?><?php if(!$rows): ?><tr><td colspan="6" class="empty">ไม่พบรายการที่ตรงกัน</td></tr><?php endif ?></tbody></table></div><div class="table-footer">แสดง <b data-footer-count><?= count($rows) ?></b> รายการ <span>ข้อมูล Northwind</span></div></section>
<?php endif ?></div><footer class="footer">© <?= ((int)date('Y') + 543) ?> Northstar Commerce พื้นที่ทำงาน <span>เชื่อมต่อกับ Northwind</span></footer></main>
<?php if($page==='products'): ?><div class="modal-backdrop <?= $editProductId > 0 || $error?'show':'' ?>" id="productModal"><div class="modal"><div class="modal-head"><div><div class="eyebrow">สินค้า</div><h2><?= $editProductId > 0?'แก้ไขสินค้า':'เพิ่มสินค้า' ?></h2><p>กรอกรายละเอียดสินค้า</p></div><button class="close-button" onclick="closeProductModal()">×</button></div><form method="post" id="productForm" class="product-form"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $editProductId ?>"><label>ชื่อสินค้า<input name="product_name" required maxlength="30" placeholder="เช่น ชาสมุนไพร"></label><div class="form-row"><label>หมวดหมู่<select name="category_id" required><option value="">เลือกหมวดหมู่</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['i_CategoryID'] ?>"><?= h($cat['c_CategoryName']) ?></option><?php endforeach ?></select></label><label>ผู้จัดจำหน่าย<select name="supplier_id" required><option value="">เลือกผู้จัดจำหน่าย</option><?php foreach($suppliers as $sup): ?><option value="<?= (int)$sup['i_SupplierID'] ?>"><?= h($sup['c_SupplierName']) ?></option><?php endforeach ?></select></label></div><div class="form-row"><label>หน่วยนับ<input name="unit" required maxlength="30" placeholder="เช่น 12 ขวด"></label><label>ราคา (ดอลลาร์สหรัฐ)<div class="input-prefix"><span>$</span><input type="number" name="price" min="0" step="0.01" required placeholder="0.00"></div></label></div><div class="modal-actions"><button type="button" class="button secondary" onclick="closeProductModal()">ยกเลิก</button><button type="submit" class="button primary" id="saveProductButton">บันทึกสินค้า</button></div></form></div></div><?php endif ?><script src="assets/app.js"></script></body></html>
