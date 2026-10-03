<?php
/**
 * Users API Endpoint
 */

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Simple API routing
switch ($method) {
    case 'GET':
        getUsers();
        break;
    case 'POST':
        createUser();
        break;
    case 'PUT':
        updateUser();
        break;
    case 'DELETE':
        deleteUser();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        break;
}

/**
 * Get all users or specific user
 */
function getUsers() {
    global $db;
    
    if (isset($_GET['id'])) {
        // Get specific user
        $user_id = intval($_GET['id']);
        $pdo = $db->getPDO();
        $sql = "SELECT user_id AS id, email, first_name, last_name FROM users WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode($row);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
    } else {
        // Get all users
        $sql = "SELECT user_id AS id, email, first_name, last_name FROM users LIMIT 50";
        $pdo = $db->getPDO();
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($users);
    }
}

/**
 * Create new user
 */
function createUser() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    // TODO: Hash password and validate email
    $email = $data['email'];
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $first_name = isset($data['first_name']) ? $data['first_name'] : '';
    $last_name = isset($data['last_name']) ? $data['last_name'] : '';
    $sql = "INSERT INTO users (email, password, first_name, last_name) 
            VALUES (?, ?, ?, ?)";
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([$email, $password, $first_name, $last_name]);

    if ($ok) {
        http_response_code(201);
        echo json_encode(['id' => $pdo->lastInsertId(), 'message' => 'User created successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to create user']);
    }
}

/**
 * Update user
 */
function updateUser() {
    // TODO: Implement update logic
    echo json_encode(['message' => 'Update not implemented yet']);
}

/**
 * Delete user
 */
function deleteUser() {
    // TODO: Implement delete logic
    echo json_encode(['message' => 'Delete not implemented yet']);
}
