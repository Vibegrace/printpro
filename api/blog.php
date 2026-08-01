<?php
/**
 * Blog API
 * Returns products as blog posts
 */

header('Content-Type: application/json');
require_once '../config.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_post':
        getPost();
        break;
    case 'get_posts_by_category':
        getPostsByCategory();
        break;
    default:
        getPosts();
        break;
}

function getPosts()
{
    global $conn;

    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $sql = "SELECT p.*, c.name as category_name FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }

    $sqlCount = "SELECT COUNT(*) as total FROM products";
    $countResult = $conn->query($sqlCount);
    $total = $countResult->fetch_assoc()['total'];

    sendResponse(true, 'Posts retrieved', [
        'posts' => $posts,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);

    $stmt->close();
}

function getPost()
{
    global $conn;

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        sendResponse(false, 'Product ID is required');
        return;
    }

    $sql = "SELECT p.*, c.name as category_name FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'Post not found');
        return;
    }

    $post = $result->fetch_assoc();
    sendResponse(true, 'Post retrieved', ['post' => $post]);
    $stmt->close();
}

function getPostsByCategory()
{
    global $conn;

    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

    if ($categoryId <= 0) {
        sendResponse(false, 'Category ID is required');
        return;
    }

    $sql = "SELECT p.*, c.name as category_name FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.category_id = ? ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();

    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }

    sendResponse(true, 'Posts retrieved', ['posts' => $posts]);
    $stmt->close();
}

function sendResponse($success, $message, $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
?>