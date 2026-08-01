# PrintPro E-Commerce System - Database & API Documentation

## 📋 Table of Contents
- [Overview](#overview)
- [Database Setup](#database-setup)
- [File Structure](#file-structure)
- [API Endpoints](#api-endpoints)
- [Configuration](#configuration)
- [Installation Guide](#installation-guide)
- [Usage Examples](#usage-examples)
- [Database Schema](#database-schema)
- [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

PrintPro is a full-featured e-commerce system for printing services. This documentation covers the backend database structure and API endpoints used for order processing, management, and analytics.

### Key Features
- ✅ Order Management System
- ✅ Product Catalog
- ✅ Customer Management
- ✅ Payment Processing
- ✅ Sales Analytics & Reports
- ✅ Order Tracking
- ✅ Inventory Management

---

## 💾 Database Setup

### Prerequisites
- MySQL 5.7+ or MariaDB
- PHP 7.2+
- Web server (Apache/Nginx)

### Step 1: Create Database

```bash
# Option 1: Using MySQL command line
mysql -u root -p < database/setup.sql

# Option 2: Using phpMyAdmin
1. Open phpMyAdmin
2. Click "Import"
3. Select database/setup.sql
4. Click "Go"
```

### Step 2: Configure Database Connection

Edit `api/config.php`:

```php
define('DB_HOST', 'localhost');      // Your database host
define('DB_USER', 'root');           // Your database user
define('DB_PASSWORD', '');           // Your database password
define('DB_NAME', 'printpro_db');    // Database name
```

### Step 3: Verify Installation

Create a test file `test-connection.php`:

```php
<?php
require_once 'api/config.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully!";
?>
```

Access in browser: `http://localhost/test-connection.php`

---

## 📁 File Structure

```
Printing-system/
├── api/
│   ├── config.php              # Database configuration
│   ├── checkout.php            # Order creation & processing
│   ├── orders.php              # Retrieve order data
│   ├── order-management.php    # Update & manage orders
│   ├── analytics.php           # Sales analytics & reports
│   ├── get-products.php        # Product retrieval (existing)
│   ├── get-categories.php      # Category retrieval (existing)
│   └── get-product.php         # Single product details (existing)
├── database/
│   ├── setup.sql               # Database schema & sample data
│   └── queries.sql             # Useful SQL queries
├── cart.html                   # Shopping cart page
├── index.html                  # Home page
├── products.html               # Products page
└── styles.css                  # Stylesheet
```

---

## 🔌 API Endpoints

### 1. Checkout API (`api/checkout.php`)

**Create Order**

**Method:** POST

**Parameters:**
```
- action: "create_order" (required)
- fullName: string (required)
- email: string (required)
- phone: string (required)
- address: string (required)
- city: string (required)
- state: string (required)
- paymentMethod: string (required)
- cart: JSON array (required)
- subtotal: float (required)
- tax: float (required)
- shipping: float (required)
- total: float (required)
```

**Payment Methods:**
- `bank_transfer` - Bank transfer
- `card` - Credit/Debit Card
- `ussd` - USSD payment
- `cash_on_delivery` - Cash on delivery

**Response:**
```json
{
  "success": true,
  "message": "Order placed successfully",
  "order_id": "ORD-1717531200-ABC123DEF",
  "timestamp": "2026-06-04 14:53:20"
}
```

**Example:**
```javascript
const formData = new FormData();
formData.append('action', 'create_order');
formData.append('fullName', 'John Doe');
formData.append('email', 'john@example.com');
formData.append('phone', '+234 123 456 789');
formData.append('address', '123 Main Street');
formData.append('city', 'Lagos');
formData.append('state', 'Lagos');
formData.append('paymentMethod', 'bank_transfer');
formData.append('cart', JSON.stringify([
  { id: 1, name: 'Business Cards', price: 5000, quantity: 2 }
]));
formData.append('subtotal', 15000);
formData.append('tax', 750);
formData.append('shipping', 500);
formData.append('total', 16250);

fetch('api/checkout.php', { method: 'POST', body: formData })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      console.log('Order ID:', data.order_id);
    }
  });
```

---

### 2. Orders API (`api/orders.php`)

**Get Single Order**

**Method:** GET

**Parameters:**
- `action`: "get_order" (required)
- `order_id`: string (required)

**Response:**
```json
{
  "success": true,
  "message": "Order retrieved successfully",
  "order": {
    "id": 1,
    "order_id": "ORD-1717531200-ABC123DEF",
    "customer_name": "John Doe",
    "email": "john@example.com",
    "phone": "+234 123 456 789",
    "address": "123 Main Street",
    "city": "Lagos",
    "state": "Lagos",
    "payment_method": "bank_transfer",
    "subtotal": 15000.00,
    "tax": 750.00,
    "shipping": 500.00,
    "total": 16250.00,
    "status": "pending",
    "created_at": "2026-06-04 14:53:20",
    "items": [
      {
        "id": 1,
        "product_name": "Business Cards",
        "price": 5000.00,
        "quantity": 2,
        "subtotal": 10000.00
      }
    ]
  }
}
```

**URL:**
```
api/orders.php?action=get_order&order_id=ORD-1717531200-ABC123DEF
```

---

**Get Orders by Customer Email**

**Method:** GET

**Parameters:**
- `action`: "get_orders_by_email" (required)
- `email`: string (required)

**URL:**
```
api/orders.php?action=get_orders_by_email&email=john@example.com
```

**Response:**
```json
{
  "success": true,
  "message": "Orders retrieved successfully",
  "orders": [
    {
      "order_id": "ORD-1717531200-ABC123DEF",
      "customer_name": "John Doe",
      "total": 16250.00,
      "status": "pending",
      "item_count": 2,
      "created_at": "2026-06-04 14:53:20"
    }
  ]
}
```

---

**Get All Orders (Paginated)**

**Method:** GET

**Parameters:**
- `action`: "get_all_orders" (required)
- `page`: integer (optional, default: 1)
- `limit`: integer (optional, default: 20)

**URL:**
```
api/orders.php?action=get_all_orders&page=1&limit=20
```

**Response:**
```json
{
  "success": true,
  "orders": [...],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_orders": 95,
    "orders_per_page": 20
  }
}
```

---

**Get Orders by Status**

**Method:** GET

**Parameters:**
- `action`: "get_orders_by_status" (required)
- `status`: string (required)

**Valid Statuses:**
- `pending` - Order received, awaiting processing
- `processing` - Order is being prepared
- `shipped` - Order sent to customer
- `delivered` - Order received by customer
- `cancelled` - Order cancelled

**URL:**
```
api/orders.php?action=get_orders_by_status&status=pending
```

**Response:**
```json
{
  "success": true,
  "message": "Orders retrieved successfully",
  "status": "pending",
  "orders": [...],
  "count": 15
}
```

---

**Get Order Items**

**Method:** GET

**Parameters:**
- `action`: "get_order_items" (required)
- `order_id`: string (required)

**URL:**
```
api/orders.php?action=get_order_items&order_id=ORD-1717531200-ABC123DEF
```

---

### 3. Order Management API (`api/order-management.php`)

**Update Order Status**

**Method:** POST

**Parameters:**
- `action`: "update_status" (required)
- `order_id`: string (required)
- `status`: string (required - pending|processing|shipped|delivered|cancelled)

**Example:**
```javascript
const formData = new URLSearchParams({
  action: 'update_status',
  order_id: 'ORD-1717531200-ABC123DEF',
  status: 'shipped'
});

fetch('api/order-management.php', {
  method: 'POST',
  body: formData
})
.then(res => res.json())
.then(data => console.log(data));
```

**Response:**
```json
{
  "success": true,
  "message": "Order status updated successfully",
  "order_id": "ORD-1717531200-ABC123DEF",
  "new_status": "shipped",
  "timestamp": "2026-06-04 15:00:00"
}
```

---

**Update Order Notes**

**Method:** POST

**Parameters:**
- `action`: "update_notes" (required)
- `order_id`: string (required)
- `notes`: string (required)

**Example:**
```javascript
fetch('api/order-management.php', {
  method: 'POST',
  body: new URLSearchParams({
    action: 'update_notes',
    order_id: 'ORD-1717531200-ABC123DEF',
    notes: 'Customer requested express delivery'
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

---

**Cancel Order**

**Method:** POST

**Parameters:**
- `action`: "cancel_order" (required)
- `order_id`: string (required)

**Note:** Only non-cancelled and non-delivered orders can be cancelled.

**Example:**
```javascript
fetch('api/order-management.php', {
  method: 'POST',
  body: new URLSearchParams({
    action: 'cancel_order',
    order_id: 'ORD-1717531200-ABC123DEF'
  })
})
```

---

**Delete Order**

**Method:** POST

**Parameters:**
- `action`: "delete_order" (required)
- `order_id`: string (required)

**Note:** Only pending orders can be deleted.

---

### 4. Analytics API (`api/analytics.php`)

**Get Sales Summary**

**Method:** GET

**URL:**
```
api/analytics.php?action=sales_summary
```

**Response:**
```json
{
  "success": true,
  "message": "Sales summary retrieved",
  "summary": {
    "total_orders": 95,
    "total_revenue": 1523000.00,
    "average_order_value": 16031.58,
    "unique_customers": 45,
    "latest_order_date": "2026-06-04 14:53:20"
  }
}
```

---

**Get Sales by Month**

**Method:** GET

**Parameters:**
- `action`: "sales_by_month" (required)
- `months`: integer (optional, default: 12)

**URL:**
```
api/analytics.php?action=sales_by_month&months=12
```

**Response:**
```json
{
  "success": true,
  "sales_by_month": [
    {
      "month": "2026-06-01",
      "order_count": 8,
      "revenue": 130000.00
    }
  ]
}
```

---

**Get Top Products**

**Method:** GET

**Parameters:**
- `action`: "top_products" (required)
- `limit`: integer (optional, default: 10)

**URL:**
```
api/analytics.php?action=top_products&limit=10
```

**Response:**
```json
{
  "success": true,
  "products": [
    {
      "product_name": "Business Cards",
      "product_id": 1,
      "total_quantity": 150,
      "total_revenue": 750000.00,
      "order_count": 45,
      "average_price": 5000.00
    }
  ]
}
```

---

**Get Revenue by Payment Method**

**Method:** GET

**URL:**
```
api/analytics.php?action=revenue_by_payment
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "payment_method": "bank_transfer",
      "order_count": 35,
      "total_revenue": 560000.00,
      "average_order_value": 16000.00,
      "minimum_order": 5000.00,
      "maximum_order": 50000.00
    }
  ]
}
```

---

**Get Order Status Summary**

**Method:** GET

**URL:**
```
api/analytics.php?action=order_status_summary
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "status": "delivered",
      "order_count": 60,
      "total_value": 960000.00,
      "average_value": 16000.00
    },
    {
      "status": "pending",
      "order_count": 20,
      "total_value": 320000.00,
      "average_value": 16000.00
    }
  ]
}
```

---

**Get Customer Analytics**

**Method:** GET

**Parameters:**
- `action`: "customer_analytics" (required)
- `limit`: integer (optional, default: 10)

**URL:**
```
api/analytics.php?action=customer_analytics&limit=10
```

**Response:**
```json
{
  "success": true,
  "customers": [
    {
      "email": "john@example.com",
      "customer_name": "John Doe",
      "order_count": 5,
      "lifetime_value": 80000.00,
      "average_order_value": 16000.00,
      "first_order_date": "2026-02-15",
      "last_order_date": "2026-06-04"
    }
  ]
}
```

---

## ⚙️ Configuration

### Database Configuration (`api/config.php`)

```php
<?php
/**
 * Database Configuration
 * Update these with your actual credentials
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'printpro_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]));
}

// Set charset
$conn->set_charset("utf8");
?>
```

### Email Configuration

To enable email notifications in `api/checkout.php`, uncomment line 232:

```php
// Send confirmation email
mail($email, $subject, $message, $headers);
```

For production, use PHPMailer or similar library.

---

## 📦 Installation Guide

### Step 1: Import Database

```bash
mysql -u root -p printpro_db < database/setup.sql
```

### Step 2: Configure Credentials

Edit `api/config.php` with your database credentials.

### Step 3: Set File Permissions

```bash
chmod 644 api/config.php
chmod 755 api/
chmod 755 database/
```

### Step 4: Test Connection

```php
<?php
require_once 'api/config.php';
echo $conn->connect_error ? "Failed" : "Connected!";
?>
```

### Step 5: Verify API Endpoints

```bash
# Test checkout
curl -X POST http://localhost/api/checkout.php \
  -d "action=create_order&fullName=Test&email=test@example.com&phone=+234..."

# Test orders
curl "http://localhost/api/orders.php?action=get_all_orders"

# Test analytics
curl "http://localhost/api/analytics.php?action=sales_summary"
```

---

## 💡 Usage Examples

### JavaScript - Get Customer Orders

```javascript
async function getCustomerOrders(email) {
  try {
    const response = await fetch(
      `api/orders.php?action=get_orders_by_email&email=${email}`
    );
    const data = await response.json();
    
    if (data.success) {
      console.log('Customer Orders:', data.orders);
      data.orders.forEach(order => {
        console.log(`Order: ${order.order_id} - Status: ${order.status}`);
      });
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('Fetch error:', error);
  }
}
```

### JavaScript - Create Order via AJAX

```javascript
async function submitOrder(customerData, cartItems) {
  const formData = new FormData();
  
  formData.append('action', 'create_order');
  formData.append('fullName', customerData.name);
  formData.append('email', customerData.email);
  formData.append('phone', customerData.phone);
  formData.append('address', customerData.address);
  formData.append('city', customerData.city);
  formData.append('state', customerData.state);
  formData.append('paymentMethod', customerData.paymentMethod);
  formData.append('cart', JSON.stringify(cartItems));
  formData.append('subtotal', 15000);
  formData.append('tax', 750);
  formData.append('shipping', 500);
  formData.append('total', 16250);

  try {
    const response = await fetch('api/checkout.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    
    if (data.success) {
      alert(`Order created! Order ID: ${data.order_id}`);
      return data.order_id;
    } else {
      alert(`Error: ${data.message}`);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Network error. Please try again.');
  }
}
```

### JavaScript - Get Sales Analytics

```javascript
async function getSalesAnalytics() {
  try {
    const response = await fetch('api/analytics.php?action=sales_summary');
    const data = await response.json();
    
    if (data.success) {
      const summary = data.summary;
      console.log(`Total Orders: ${summary.total_orders}`);
      console.log(`Total Revenue: ₦${summary.total_revenue}`);
      console.log(`Average Order: ₦${summary.average_order_value}`);
      console.log(`Unique Customers: ${summary.unique_customers}`);
    }
  } catch (error) {
    console.error('Error:', error);
  }
}
```

### PHP - Fetch Top Products

```php
<?php
$curl = curl_init('http://localhost/api/analytics.php?action=top_products&limit=5');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

if ($data['success']) {
    foreach ($data['products'] as $product) {
        echo $product['product_name'] . ': ₦' . $product['total_revenue'] . "\n";
    }
}
?>
```

---

## 📊 Database Schema

### Orders Table
```sql
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(100) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax DECIMAL(10, 2) NOT NULL,
    shipping DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled'),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Order Items Table
```sql
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(100) NOT NULL,
    product_id INT,
    product_name VARCHAR(200) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);
```

### Products Table
```sql
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    stock_quantity INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 🐛 Troubleshooting

### "Database connection failed"
- Check database credentials in `api/config.php`
- Verify MySQL is running
- Test: `mysql -u root -p -e "SELECT 1"`

### "Order not found"
- Verify order_id format
- Check database: `SELECT * FROM orders WHERE order_id = 'ID';`

### Orders not saving
- Check file permissions
- Enable PHP error logging
- Verify form data is sent

### Email not sending
- Check PHP mail configuration
- Consider using PHPMailer
- Check server logs

### Slow queries
- Add database indexes
- Archive old orders
- Check query optimization

---

## 📞 Support

- **Email:** info@printpro.com
- **Phone:** +234 (0) 123 456 789
- **Documentation:** See `API_DOCUMENTATION.md`

---

## 📝 Version Info

- **Last Updated:** June 4, 2026
- **PHP Required:** 7.2+
- **MySQL Required:** 5.7+
- **Database Version:** 1.0

---

## 📄 License

Copyright © 2026 PrintPro. All rights reserved.
