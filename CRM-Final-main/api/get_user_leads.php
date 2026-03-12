<?php
/**
 * Retrieve Leads for a Specific User
 * 
 * Query leads assigned to a user or created by a user
 * 
 * Usage:
 * GET /api/get_user_leads.php?user_id=1
 * GET /api/get_user_leads.php?user_id=1&status=hot
 * GET /api/get_user_leads.php?user_id=1&format=csv
 * 
 * Headers:
 * X-CRM-API-KEY: your_api_key (for authentication)
 * 
 * Response: JSON array of leads
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

// Check for API key authentication
$api_key = $_SERVER['HTTP_X_CRM_API_KEY'] ?? null;
if (!$api_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing API key header (X-CRM-API-KEY)']);
    exit();
}

// Verify API key
$stored_key = getSetting('lead_api_key');
if ($api_key !== $stored_key) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid API key']);
    exit();
}

// Get parameters
$user_id = $_GET['user_id'] ?? null;
$status = $_GET['status'] ?? null;
$limit = (int)($_GET['limit'] ?? 100);
$offset = (int)($_GET['offset'] ?? 0);
$format = $_GET['format'] ?? 'json'; // 'json' or 'csv'

// Validate user_id
if (!$user_id || !is_numeric($user_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id is required and must be numeric']);
    exit();
}

// Fetch leads
$leads = getUserLeads($user_id, $status, $limit, $offset);

if ($format === 'csv') {
    // Output as CSV
    outputLeadsAsCSV($leads);
} else {
    // Output as JSON
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'count' => count($leads),
        'limit' => $limit,
        'offset' => $offset,
        'leads' => $leads
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Get leads for a specific user
 */
function getUserLeads($user_id, $status = null, $limit = 100, $offset = 0) {
    global $conn, $db_type;
    
    if (!$conn) {
        return [];
    }
    
    try {
        // Use EXISTS to include leads mapped in lead_assignments without multiplying rows
        $sql = "SELECT l.*, u1.full_name as assigned_to_name, u2.full_name as created_by_name 
                FROM leads l 
                LEFT JOIN users u1 ON l.assigned_to = u1.id 
                LEFT JOIN users u2 ON l.created_by = u2.id 
                WHERE (l.assigned_to = ? OR l.created_by = ? OR EXISTS (SELECT 1 FROM lead_assignments la WHERE la.lead_id = l.id AND la.user_id = ?))";

        $params = [$user_id, $user_id, $user_id];

        // Add status filter if provided
        if ($status) {
            $sql .= " AND l.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $leads = $stmt->fetchAll();
        } else {
            // mysqli: build types dynamically (s for status string, i for integers)
            $types = '';
            for ($i = 0; $i < count($params); $i++) {
                // last two params are limit and offset => integers
                if ($i >= count($params) - 2) { $types .= 'i'; continue; }
                // user_id params are integers
                if ($i <= 2) { $types .= 'i'; continue; }
                // status if present -> string
                $types .= 's';
            }
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log('prepare failed: ' . ($conn->error ?? 'unknown'));
                return [];
            }
            // bind params by reference
            $bind_names[] = $types;
            for ($i = 0; $i < count($params); $i++) {
                $bind_names[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_names);
            $stmt->execute();
            $result = $stmt->get_result();
            $leads = [];
            while ($row = $result->fetch_assoc()) {
                $leads[] = $row;
            }
        }
        
        return $leads;
        
    } catch (Exception $e) {
        error_log('Error fetching user leads: ' . $e->getMessage());
        return [];
    }
}

/**
 * Output leads as CSV
 */
function outputLeadsAsCSV($leads) {
    if (empty($leads)) {
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads.csv"');
        echo "No leads found";
        return;
    }
    
    http_response_code(200);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write header
    $headers = array_keys($leads[0]);
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($leads as $lead) {
        fputcsv($output, $lead);
    }
    
    fclose($output);
}
?>
