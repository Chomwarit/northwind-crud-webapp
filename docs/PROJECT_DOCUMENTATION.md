# เอกสารโครงการ: Northstar Commerce Workspace

เอกสารนี้จัดทำตามโจทย์ Course Project Overview Web Application Project และเตรียมไว้สำหรับคัดลอกไปจัดรูปแบบเป็น Google Docs

## ข้อมูลสำหรับส่งงาน

- ชื่อโครงการ: Northstar Commerce Workspace
- ชื่อสมาชิกกลุ่ม: [กรอกชื่อสมาชิกทุกคน]
- URL เว็บแอป Railway: https://northwind-crud-webapp-production.up.railway.app
- URL GitHub repository: https://github.com/Chomwarit/northwind-crud-webapp
- URL Google Drive ที่เก็บ Source Code: [กรอกหากรายวิชากำหนดให้ส่งผ่าน Google Drive]
- URL เอกสารฉบับ Google Docs: [เติมหลังสร้างหรือคัดลอกเอกสารนี้ไป Google Docs]
- ฐานข้อมูล: Northwind จากไฟล์ `dbNorthwind.sql` ที่รายวิชาจัดให้
- เทคโนโลยี: PHP 8, MySQL, PDO, HTML, CSS, JavaScript, JSON API

## 1. ขอบเขตตามโจทย์

เว็บแอปแสดงภาพรวมข้อมูล Northwind และ directory สำหรับ Products, Customers, Orders, Categories และ Suppliers ผู้ใช้ค้นหาข้อมูลสินค้า เพิ่มสินค้า แก้ไขสินค้า และลบสินค้าได้ ระบบตรวจสอบข้อมูลและแจ้งผลการทำรายการ

แอปเตรียม Docker deployment สำหรับ Railway และรองรับการเชื่อม Railway MySQL ผ่านตัวแปรสภาพแวดล้อม

## 2. สถาปัตยกรรม API และ CRUD

```text
Browser (HTML / CSS / JavaScript)
        │ JSON + Fetch
        ▼
api/products.php (GET / POST / PUT / DELETE)
        │ PDO prepared statements
        ▼
Northwind MySQL (tb_products, tb_categories, tb_suppliers, tb_orderdetails)
```

| HTTP method | Endpoint | การทำงาน |
| --- | --- | --- |
| GET | `/api/products.php?q=tea` | ค้นหา/อ่านรายการสินค้า |
| GET | `/api/products.php?id=1` | อ่านสินค้ารายการเดียว |
| POST | `/api/products.php` | เพิ่มสินค้าใหม่ |
| PUT | `/api/products.php?id=1` | แก้ไขสินค้า |
| DELETE | `/api/products.php?id=1` | ลบสินค้าเมื่อไม่มีประวัติคำสั่งซื้อ |

การเขียนข้อมูลส่ง JSON และใช้ session CSRF token ใน `X-CSRF-Token` ทุกการเปลี่ยนแปลงข้อมูลใช้ prepared statements การตรวจสอบฝั่ง PHP ครอบคลุมชื่อสินค้า หน่วยสินค้า ราคา หมวดหมู่ และผู้จำหน่าย การลบสินค้าที่มีอยู่ใน `tb_orderdetails` จะถูกปฏิเสธ

ตัวอย่าง JSON สำหรับเพิ่มหรือแก้ไขสินค้า:

```json
{
  "product_name": "Artisan tea",
  "category_id": 1,
  "supplier_id": 1,
  "unit": "12 bottles",
  "price": 18.5
}
```

API ตอบกลับเป็น JSON โดยส่งสถานะ HTTP ที่เหมาะสม เช่น `201` เมื่อเพิ่มสำเร็จ, `404` เมื่อไม่พบสินค้า, `409` เมื่อลบสินค้าที่มีประวัติไม่ได้ และ `422` เมื่อข้อมูลไม่ผ่าน validation

## 3. การเตรียมฐานข้อมูล Northwind

1. ไฟล์ฐานข้อมูลจากรายวิชาถูกเก็บไว้ที่ `database/dbNorthwind.sql` ใน Source Code ชุดนี้
2. สำหรับ MAMP ให้นำเข้าไฟล์ผ่าน phpMyAdmin และกำหนดชื่อฐานข้อมูลให้ตรงกับ `DB_NAME` ใน `includes/db.php` หรือค่าตัวแปรสภาพแวดล้อม
3. สำหรับ Railway ให้สร้าง MySQL service ใน project เดียวกับแอป เมื่อเว็บเริ่มทำงานครั้งแรก สคริปต์ `scripts/seed-railway-db.sh` จะนำเข้าข้อมูล Northwind ลงในฐานข้อมูลที่กำหนดไว้ หากยังไม่มีตาราง `tb_products`
4. ไฟล์นี้มีคำสั่ง `CREATE DATABASE IF NOT EXISTS db_northwind` และ `USE db_northwind` ซึ่งตรงกับค่าเริ่มต้นของแอป ส่วนบรรทัด comment ที่ระบุ `db_northwind_cpe2204` เป็นเพียงข้อมูลกำกับ ไม่ใช่คำสั่ง SQL

## 4. ขั้นตอน deploy เว็บและฐานข้อมูลบน Railway

### 4.1 เตรียม Source Code

1. เพิ่มไฟล์ใน workspace นี้ไปยัง GitHub repository ของกลุ่ม โดยไม่เพิ่ม `.env` หรือรหัสผ่านลง repository
2. ตรวจว่า `Dockerfile`, `railway.json`, `health.php`, `api/`, `includes/`, `assets/`, `database/dbNorthwind.sql` และ `index.php` อยู่ใน repository

### 4.2 สร้าง Railway project และ MySQL

1. เข้าสู่ระบบ Railway และสร้าง project
2. เพิ่ม MySQL service จากเมนูสร้าง service
3. เชื่อมต่อ Railway service ที่ deploy จาก GitHub เข้ากับตัวแปรของ MySQL service โดยตั้งค่าใน Variables ของเว็บ service ดังนี้ (เปลี่ยน `MySQL` ให้ตรงกับชื่อ service จริง):

   ```text
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_NAME=${{MySQL.MYSQLDATABASE}}
   DB_USER=${{MySQL.MYSQLUSER}}
   DB_PASS=${{MySQL.MYSQLPASSWORD}}
   ```

4. ตรวจชื่อฐานข้อมูลที่ไฟล์ dump สร้าง/เลือก และปรับ `DB_NAME` ให้ตรงกับฐานข้อมูลที่นำเข้า

### 4.3 Import ไฟล์ Northwind

ใน Railway เว็บจะรอจน MySQL พร้อม แล้วตรวจว่ามีตาราง `tb_products` หรือยัง หากยังไม่มี จะนำเข้า `database/dbNorthwind.sql` ไปยังฐานข้อมูลที่ `DB_NAME` ระบุ โดยตัดคำสั่งสร้างและเลือกฐาน `db_northwind` ในไฟล์ dump ออก เพื่อใช้ฐานข้อมูลเริ่มต้นของ Railway ได้ จากนั้น `/health.php` จะตรวจการเชื่อมต่อด้วย `SELECT 1`

### 4.4 Deploy เว็บแอป

1. สร้าง Web Service จาก GitHub repository ของกลุ่ม
2. Railway จะใช้ `Dockerfile` ที่ root เพื่อ build PHP 8 พร้อม PDO MySQL และรัน PHP server บนพอร์ตที่ Railway กำหนด
3. ตั้งค่า health check path เป็น `/health.php` หาก Railway ยังไม่อ่านจาก `railway.json`
4. รอจน deployment ผ่าน health check แล้วไปที่ Settings → Networking → Public Networking → Generate Domain เพื่อสร้าง URL สาธารณะ
5. บันทึก URL ที่ Railway สร้างลงส่วนข้อมูลสำหรับส่งงานด้านบนและใน Google Docs

## 5. วิธีตรวจสอบการทำงานหลัง deploy

1. เปิด URL ของเว็บแอปและตรวจ dashboard กับจำนวนข้อมูลจาก Northwind
2. เปิด Products แล้วค้นหาด้วยชื่อสินค้า หมวดหมู่ หรือผู้จำหน่าย
3. เพิ่มสินค้าใหม่ ตรวจว่าได้รับข้อความ `Product added.` และสินค้าแสดงในรายการ
4. แก้ไขสินค้าที่เพิ่ม ตรวจข้อความ `Product updated.` และข้อมูลใหม่
5. ลบสินค้าที่เพิ่ม ตรวจข้อความ `Product deleted.`
6. ลองส่งข้อมูลที่ไม่ถูกต้อง เช่น ราคาเป็นลบ หรือเว้นชื่อสินค้า ระบบต้องแจ้ง validation และไม่บันทึกข้อมูล
7. ลองลบสินค้าที่อยู่ในประวัติ order ระบบต้องแจ้งว่าไม่สามารถลบได้
8. ตรวจ endpoint อ่านข้อมูล เช่น `https://<RAILWAY_DOMAIN>/api/products.php` และตรวจ JSON response

## 6. การส่งงาน

- Source Code: อัปโหลดไฟล์โครงการนี้ไปยัง Google Drive ของกลุ่ม หรือสร้าง ZIP โดยไม่รวม `.env` จากนั้นเปิดสิทธิ์ตามที่ผู้สอนกำหนดและใส่ลิงก์ไว้ด้านบน
- Process Documentation: คัดลอกเอกสารนี้ไป Google Docs เพิ่มชื่อสมาชิก ภาพหน้าจอขั้นตอน deploy, ผลการทดสอบ CRUD, URL แอป และลิงก์ Source Code
- Live Application: ใส่ Railway public domain ที่ใช้งานได้จริง
- สมาชิกกลุ่ม: เติมรายชื่อสมาชิกตามจริงก่อนส่ง

## 7. แหล่งอ้างอิง

- [Railway MySQL](https://docs.railway.com/databases/mysql)
- [Railway Dockerfiles](https://docs.railway.com/builds/dockerfiles)
- [Railway TCP Proxy](https://docs.railway.com/networking/tcp-proxy)
- [Railway Public Domains](https://docs.railway.com/networking/domains/working-with-domains)
