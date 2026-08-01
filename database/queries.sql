-- PrintPro Database - Order Management Queries
-- Useful queries for managing orders and analyzing sales

-- ========================
-- ORDER QUERIES
-- ========================

-- Get all orders with items
SELECT 
    o.id,
    o.order_id,
    o.customer_name,
    o.email,
    o.total,
    o.status,
    o.created_at,
    COUNT(oi.id) as item_count
FROM orders o
LEFT JOIN order_items oi ON o.order_id = oi.order_id
GROUP BY o.id
ORDER BY o.created_at DESC;

-- Get single order with all items
SELECT 
    oi.id,
    oi.product_name,
    oi.price,
    oi.quantity,
    oi.subtotal,
    o.customer_name,
    o.email,
    o.address,
    o.city,
    o.state
FROM order_items oi
JOIN orders o ON oi.order_id = o.order_id
WHERE o.order_id = 'ORDER_ID_HERE'
ORDER BY oi.created_at;

-- Get pending orders
SELECT 
    order_id,
    customer_name,
    email,
    phone,
    total,
    created_at
FROM orders
WHERE status = 'pending'
ORDER BY created_at ASC;

-- Update order status
UPDATE orders SET status = 'shipped' WHERE order_id = 'ORDER_ID_HERE';

-- Get orders by date range
SELECT 
    order_id,
    customer_name,
    total,
    status,
    created_at
FROM orders
WHERE DATE(created_at) BETWEEN '2026-01-01' AND '2026-12-31'
ORDER BY created_at DESC;

-- ========================
-- SALES ANALYTICS QUERIES
-- ========================

-- Total sales by month
SELECT 
    DATE_TRUNC(created_at, MONTH) as month,
    COUNT(*) as order_count,
    SUM(total) as total_revenue
FROM orders
WHERE status != 'cancelled'
GROUP BY DATE_TRUNC(created_at, MONTH)
ORDER BY month DESC;

-- Top selling products
SELECT 
    oi.product_name,
    SUM(oi.quantity) as total_quantity,
    SUM(oi.subtotal) as total_revenue,
    COUNT(DISTINCT oi.order_id) as order_count
FROM order_items oi
JOIN orders o ON oi.order_id = o.order_id
WHERE o.status != 'cancelled'
GROUP BY oi.product_name
ORDER BY total_revenue DESC
LIMIT 10;

-- Revenue by payment method
SELECT 
    payment_method,
    COUNT(*) as order_count,
    SUM(total) as total_revenue,
    AVG(total) as average_order_value
FROM orders
WHERE status != 'cancelled'
GROUP BY payment_method
ORDER BY total_revenue DESC;

-- Customer purchase statistics
SELECT 
    email,
    customer_name,
    COUNT(*) as order_count,
    SUM(total) as total_spent,
    AVG(total) as average_order_value,
    MAX(created_at) as last_order_date
FROM orders
WHERE status != 'cancelled'
GROUP BY email
ORDER BY total_spent DESC;

-- Orders by status
SELECT 
    status,
    COUNT(*) as order_count,
    SUM(total) as total_value,
    AVG(total) as average_value
FROM orders
GROUP BY status
ORDER BY order_count DESC;

-- ========================
-- INVENTORY QUERIES
-- ========================

-- Products with low stock
SELECT 
    id,
    product_name,
    stock_quantity,
    price
FROM products
WHERE stock_quantity < 20
AND status = 'active'
ORDER BY stock_quantity ASC;

-- Total inventory value
SELECT 
    SUM(stock_quantity * price) as total_inventory_value,
    SUM(stock_quantity) as total_items,
    COUNT(*) as total_products
FROM products
WHERE status = 'active';

-- ========================
-- CUSTOMER QUERIES
-- ========================

-- Most frequent customers
SELECT 
    email,
    customer_name,
    COUNT(*) as purchase_count,
    MAX(created_at) as last_purchase
FROM orders
WHERE status != 'cancelled'
GROUP BY email
ORDER BY purchase_count DESC
LIMIT 20;

-- Customer lifetime value
SELECT 
    email,
    customer_name,
    COUNT(*) as orders,
    SUM(total) as lifetime_value,
    MIN(created_at) as first_order,
    MAX(created_at) as last_order
FROM orders
WHERE status != 'cancelled'
GROUP BY email
HAVING lifetime_value > 0
ORDER BY lifetime_value DESC;

-- ========================
-- MAINTENANCE QUERIES
-- ========================

-- Delete old cancelled orders (older than 90 days)
DELETE FROM orders 
WHERE status = 'cancelled' 
AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Archive completed orders (older than 1 year)
-- Note: Create an archive table first if needed
SELECT * FROM orders 
WHERE status = 'delivered' 
AND created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Check database size
SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
FROM information_schema.tables
WHERE table_schema = 'printpro_db';

-- Optimize tables
OPTIMIZE TABLE orders;
OPTIMIZE TABLE order_items;
OPTIMIZE TABLE products;
OPTIMIZE TABLE categories;
