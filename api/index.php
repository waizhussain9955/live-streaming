<?php
// api/index.php

// 1. CORS Configuration (Global Headers)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Exception & Error Handler (Production Mode)
set_error_handler(function($severity, $message, $file, $line) {
    // Ignore deprecations and notices in production to prevent crashes
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED || $severity === E_NOTICE || $severity === E_USER_NOTICE) {
        return;
    }
    if (error_reporting() & $severity) {
        error_log("[PHP Error] $message in $file on line $line");
        sendError("Internal server error occurred", 500);
    }
});

set_exception_handler(function($exception) {
    error_log("[Unhandled Exception] " . $exception->getMessage() . "\n" . $exception->getTraceAsString());
    sendError("Internal server error occurred", 500);
});

// 3. Database Connection helper (Singleton PDO)
function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('SUPABASE_HOST');
    $port = getenv('SUPABASE_PORT') ?: '5432';
    $db   = getenv('SUPABASE_DB');
    $user = getenv('SUPABASE_USER');
    $pass = getenv('SUPABASE_PASS');

    if (!$host || !$db || !$user) {
        sendError("Database configuration is missing", 500);
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    try {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("[Database Connection Error] " . $e->getMessage());
        sendError("Database connection failed", 500);
    }
}

// 4. Utility Helper Functions
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sendError($message, $statusCode = 500) {
    sendResponse([
        'status' => 'error',
        'message' => $message
    ], $statusCode);
}

function getJsonInput() {
    // Limit request body size to 1MB
    $limitBytes = 1024 * 1024;
    $input = file_get_contents('php://input', false, null, 0, $limitBytes);
    
    if (empty($input)) {
        return null;
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError("Invalid JSON body", 400);
    }
    return $data;
}

function validateUrls($urls) {
    if (!is_array($urls) || empty($urls)) {
        return false;
    }
    foreach ($urls as $url) {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
    }
    return true;
}

// Formats a PHP array to a PostgreSQL array literal string: e.g. {"url1","url2"}
function toPgArray($array) {
    $escaped = array_map(function($el) {
        $el = str_replace('\\', '\\\\', $el);
        $el = str_replace('"', '\\"', $el);
        return '"' . $el . '"';
    }, $array);
    return '{' . implode(',', $escaped) . '}';
}

// Parses a PostgreSQL array literal string back to a PHP array
function parsePgArray($pgArrayString) {
    if (empty($pgArrayString) || $pgArrayString === '{}') {
        return [];
    }
    $trimmed = trim($pgArrayString, '{}');
    if (empty($trimmed)) {
        return [];
    }
    // Explicitly pass escape parameter as '\\' to satisfy PHP 8.4 deprecation requirements
    $array = str_getcsv($trimmed, ',', '"', '\\');
    return array_map('trim', $array);
}

// 5. REST Endpoint Routing
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// HEALTH CHECK ENDPOINT (GET /api/index.php?action=health)
if ($method === 'GET' && $action === 'health') {
    $dbStatus = 'connected';
    try {
        getDbConnection();
    } catch (Exception $e) {
        $dbStatus = 'disconnected';
    }
    sendResponse([
        'status' => 'ok',
        'database' => $dbStatus,
        'timestamp' => time()
    ]);
}

switch ($method) {
    case 'GET':
        $pdo = getDbConnection();
        
        // GET SINGLE CHANNEL (GET /api/index.php?id=X)
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT id, name, logo, category, streams, priority, is_active FROM channels WHERE id = :id AND is_active = true");
            $stmt->execute(['id' => $id]);
            $channel = $stmt->fetch();
            
            if (!$channel) {
                sendError("Channel not found", 404);
            }
            
            $channel['id'] = (int)$channel['id'];
            $channel['priority'] = (int)$channel['priority'];
            $channel['is_active'] = (bool)$channel['is_active'];
            $channel['streams'] = parsePgArray($channel['streams']);
            
            sendResponse([
                'status' => 'success',
                'timestamp' => time(),
                'channel' => $channel
            ]);
        }
        
        // GET ALL CHANNELS (GET /api/index.php)
        $stmt = $pdo->query("SELECT id, name, logo, category, streams, priority, is_active FROM channels WHERE is_active = true ORDER BY priority DESC, name ASC");
        $channels = $stmt->fetchAll();
        
        foreach ($channels as &$channel) {
            $channel['id'] = (int)$channel['id'];
            $channel['priority'] = (int)$channel['priority'];
            $channel['is_active'] = (bool)$channel['is_active'];
            $channel['streams'] = parsePgArray($channel['streams']);
        }
        
        sendResponse([
            'status' => 'success',
            'timestamp' => time(),
            'count' => count($channels),
            'channels' => $channels
        ]);
        break;

    case 'POST':
        $input = getJsonInput();
        if (!$input) {
            sendError("JSON body is required", 400);
        }
        
        $name = isset($input['name']) ? trim($input['name']) : '';
        $logo = isset($input['logo']) ? trim($input['logo']) : '';
        $category = isset($input['category']) ? trim($input['category']) : 'football';
        $streams = isset($input['streams']) ? $input['streams'] : null;
        $priority = isset($input['priority']) ? (int)$input['priority'] : 0;
        
        // Validation
        if (empty($name)) {
            sendError("Channel name is required", 400);
        }
        if ($streams === null || !is_array($streams)) {
            sendError("Streams array is required", 400);
        }
        if (!validateUrls($streams)) {
            sendError("Invalid or empty stream URLs provided", 400);
        }
        if (!empty($logo) && filter_var($logo, FILTER_VALIDATE_URL) === false) {
            sendError("Invalid logo URL format", 400);
        }
        
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO channels (name, logo, category, streams, priority) VALUES (:name, :logo, :category, :streams, :priority) RETURNING id");
        
        try {
            $stmt->execute([
                'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                'logo' => $logo,
                'category' => htmlspecialchars($category, ENT_QUOTES, 'UTF-8'),
                'streams' => toPgArray($streams),
                'priority' => $priority
            ]);
            $result = $stmt->fetch();
            
            sendResponse([
                'status' => 'success',
                'message' => 'Channel created',
                'id' => (int)$result['id']
            ], 201);
        } catch (PDOException $e) {
            error_log("[Database Insert Error] " . $e->getMessage());
            sendError("Failed to save channel details", 500);
        }
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            sendError("Channel ID is required", 400);
        }
        $id = (int)$_GET['id'];
        
        $input = getJsonInput();
        if (!$input) {
            sendError("JSON body is required for updates", 400);
        }
        
        $pdo = getDbConnection();
        
        // Verify channel exists
        $checkStmt = $pdo->prepare("SELECT id FROM channels WHERE id = :id AND is_active = true");
        $checkStmt->execute(['id' => $id]);
        if (!$checkStmt->fetch()) {
            sendError("Channel not found", 404);
        }
        
        $fieldsToUpdate = [];
        $params = ['id' => $id];
        
        if (isset($input['name'])) {
            $name = trim($input['name']);
            if (empty($name)) {
                sendError("Channel name cannot be empty", 400);
            }
            $fieldsToUpdate[] = "name = :name";
            $params['name'] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        }
        
        if (isset($input['logo'])) {
            $logo = trim($input['logo']);
            if (!empty($logo) && filter_var($logo, FILTER_VALIDATE_URL) === false) {
                sendError("Invalid logo URL format", 400);
            }
            $fieldsToUpdate[] = "logo = :logo";
            $params['logo'] = $logo;
        }
        
        if (isset($input['category'])) {
            $category = trim($input['category']);
            $fieldsToUpdate[] = "category = :category";
            $params['category'] = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
        }
        
        if (isset($input['streams'])) {
            $streams = $input['streams'];
            if (!is_array($streams) || !validateUrls($streams)) {
                sendError("Invalid streams array format or invalid URLs", 400);
            }
            $fieldsToUpdate[] = "streams = :streams";
            $params['streams'] = toPgArray($streams);
        }
        
        if (isset($input['is_active'])) {
            $fieldsToUpdate[] = "is_active = :is_active";
            $params['is_active'] = $input['is_active'] ? 1 : 0;
        }
        
        if (isset($input['priority'])) {
            $fieldsToUpdate[] = "priority = :priority";
            $params['priority'] = (int)$input['priority'];
        }
        
        if (empty($fieldsToUpdate)) {
            sendError("No valid fields provided for update", 400);
        }
        
        // Append updated_at auto timestamp
        $fieldsToUpdate[] = "updated_at = NOW()";
        
        $sql = "UPDATE channels SET " . implode(", ", $fieldsToUpdate) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        
        try {
            $stmt->execute($params);
            sendResponse([
                'status' => 'success',
                'message' => 'Channel updated'
            ]);
        } catch (PDOException $e) {
            error_log("[Database Update Error] " . $e->getMessage());
            sendError("Failed to update channel details", 500);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            sendError("Channel ID is required", 400);
        }
        $id = (int)$_GET['id'];
        
        $pdo = getDbConnection();
        
        // Verify channel exists and is active
        $checkStmt = $pdo->prepare("SELECT id FROM channels WHERE id = :id AND is_active = true");
        $checkStmt->execute(['id' => $id]);
        if (!$checkStmt->fetch()) {
            sendError("Channel not found or already deleted", 404);
        }
        
        // Soft delete (setting is_active = false)
        $stmt = $pdo->prepare("UPDATE channels SET is_active = false, updated_at = NOW() WHERE id = :id");
        
        try {
            $stmt->execute(['id' => $id]);
            sendResponse([
                'status' => 'success',
                'message' => 'Channel deleted'
            ]);
        } catch (PDOException $e) {
            error_log("[Database Delete Error] " . $e->getMessage());
            sendError("Failed to delete channel", 500);
        }
        break;

    default:
        http_response_code(405);
        sendResponse([
            'status' => 'error',
            'message' => 'Method not allowed'
        ], 405);
        break;
}
