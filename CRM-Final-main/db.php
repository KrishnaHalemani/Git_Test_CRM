<?php
// Database Configuration for CRM Pro
$host = 'localhost';
$username = 'root';          // Change this to your MySQL username
$password = '';              // Change this to your MySQL password
$database = 'crm_pro';
$port = 3306;

// Global database connection
$conn = null;
$pdo = null;
$db_type = null;
$db_error = null;

try {
    // Try PDO first (more widely available)
    if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $conn = $pdo;
        $db_type = 'pdo';
    } 
    // Fallback to mysqli if available
    elseif (extension_loaded('mysqli')) {
        $conn = new mysqli($host, $username, $password, $database, $port);
        $conn->set_charset("utf8mb4");
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        $db_type = 'mysqli';
    } 
    // No database extensions available
    else {
        throw new Exception("Neither PDO nor mysqli extensions are available. Please enable one of them in php.ini");
    }
    
} catch (Exception $e) {
    // Don't die immediately, let the setup page handle it
    $conn = null;
    $db_error = $e->getMessage();
}

// User authentication for login - DEFINED AFTER DATABASE CONNECTION
function authenticateUser($username, $password) {
    global $conn, $db_type;
    if (!$conn) return false;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("SELECT id, username, password_hash, role, full_name FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
        } else {
            $stmt = $conn->prepare("SELECT id, username, password_hash, role, full_name FROM users WHERE username = ? AND status = 'active'");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        }
        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            if ($db_type === 'pdo') {
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
            } else {
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
            }
            return $user;
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

function getLeads($user_id = null, $role = null) {
    global $conn, $db_type;
    
    if (!$conn) return [];
    
    try {
    // Build query. For users we restrict rows; avoid joining lead_assignments directly
    // because that can multiply rows and require GROUP BY. Use EXISTS instead which is safer.
    $sql_base = "FROM leads l 
        LEFT JOIN users u1 ON l.assigned_to = u1.id 
        LEFT JOIN users u2 ON l.created_by = u2.id";

    $params = [];
    if ($role === 'user' && $user_id) {
        $sql = "SELECT l.*, u1.full_name as assigned_to_name, u2.full_name as created_by_name " . $sql_base . " 
            WHERE (l.assigned_to = ? OR l.created_by = ? OR EXISTS (SELECT 1 FROM lead_assignments la WHERE la.lead_id = l.id AND la.user_id = ?)) 
            ORDER BY l.created_at DESC";
        $params = [$user_id, $user_id, $user_id];
    } else {
        $sql = "SELECT l.*, u1.full_name as assigned_to_name, u2.full_name as created_by_name " . $sql_base . " ORDER BY l.created_at DESC";
    }
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } else {
            if (!empty($params)) {
                $types = str_repeat('i', count($params));
                $stmt = $conn->prepare($sql);
                // Fix: bind_param requires references
                $bind_params = [$types];
                foreach ($params as $key => $value) {
                    $bind_params[] = &$params[$key];
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($sql);
                // Safety check: if query failed, return empty array
                if (!$result) {
                    return [];
                }
            }
            
            $leads = [];
            while ($row = $result->fetch_assoc()) {
                $leads[] = $row;
            }
            return $leads;
        }
    } catch (Exception $e) {
        return [];
    }
}

function addLead($lead_data) {
    global $conn, $db_type;
    
    if (!$conn) return false;
    
    try {
        $assigned_to = isset($lead_data['assigned_to']) ? $lead_data['assigned_to'] : 2;
        $created_by = isset($lead_data['created_by']) ? $lead_data['created_by'] : 2;
        $estimated_value = isset($lead_data['estimated_value']) ? $lead_data['estimated_value'] : 0.00;
        // Enforce: do not store estimated value for Service or Course leads
        $service_field = strtolower(trim((string)($lead_data['service'] ?? '')));
        $source_field = strtolower(trim((string)($lead_data['source'] ?? '')));
        if (in_array($service_field, ['service','course']) || in_array($source_field, ['service','course'])) {
            $estimated_value = 0.00;
        }
        $priority = isset($lead_data['priority']) ? $lead_data['priority'] : 'medium';
        $campaign_id = isset($lead_data['campaign_id']) && !empty($lead_data['campaign_id']) ? (int)$lead_data['campaign_id'] : null;

        // Backward compatibility: some installs do not have leads.campaign_id
        static $has_campaign_id_column = null;
        if ($has_campaign_id_column === null) {
            $has_campaign_id_column = false;
            try {
                if ($db_type === 'pdo') {
                    $colStmt = $conn->query("SHOW COLUMNS FROM leads LIKE 'campaign_id'");
                    $has_campaign_id_column = $colStmt && $colStmt->fetch() ? true : false;
                } else {
                    $colRes = $conn->query("SHOW COLUMNS FROM leads LIKE 'campaign_id'");
                    $has_campaign_id_column = $colRes && $colRes->num_rows > 0;
                }
            } catch (Exception $e) {
                $has_campaign_id_column = false;
            }
        }

        if ($has_campaign_id_column) {
            $sql = "INSERT INTO leads (name, email, phone, company, service, status, source, priority, assigned_to, created_by, notes, estimated_value, campaign_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        } else {
            $sql = "INSERT INTO leads (name, email, phone, company, service, status, source, priority, assigned_to, created_by, notes, estimated_value)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        }
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $params = [
                $lead_data['name'],
                $lead_data['email'],
                $lead_data['phone'],
                $lead_data['company'],
                $lead_data['service'],
                $lead_data['status'],
                $lead_data['source'],
                $priority,
                $assigned_to,
                $created_by,
                $lead_data['notes'],
                $estimated_value
            ];
            if ($has_campaign_id_column) {
                $params[] = $campaign_id;
            }
            $stmt->execute($params);
            $lead_id = $conn->lastInsertId();
            addLeadAssignment($lead_id, $assigned_to, $created_by);
            logLeadActivity($lead_id, $created_by, 'created', "New lead added: " . $lead_data['name']);
            return $lead_id;
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                // prepare failed — log and return false so caller can capture $conn->error
                @file_put_contents(__DIR__ . '/tools/import_errors.log', date('c') . " DB addLead mysqli prepare failed: " . ($conn->error ?? mysqli_error($conn)) . PHP_EOL, FILE_APPEND | LOCK_EX);
                return false;
            }

            // mysqli bind_param requires variables (passed by reference). Create locals.
            $name = $lead_data['name'];
            $email = $lead_data['email'];
            $phone = $lead_data['phone'];
            $company = $lead_data['company'];
            $service = $lead_data['service'];
            $status = $lead_data['status'];
            $source = $lead_data['source'];
            $prio = $priority;
            $assign = $assigned_to;
            $creator = $created_by;
            $notes = $lead_data['notes'];
            $est = $estimated_value;
            if ($has_campaign_id_column) {
                $camp_id = $campaign_id;
                $stmt->bind_param("ssssssssiisdi",
                    $name,
                    $email,
                    $phone,
                    $company,
                    $service,
                    $status,
                    $source,
                    $prio,
                    $assign,
                    $creator,
                    $notes,
                    $est,
                    $camp_id
                );
            } else {
                $stmt->bind_param("ssssssssiisd",
                    $name,
                    $email,
                    $phone,
                    $company,
                    $service,
                    $status,
                    $source,
                    $prio,
                    $assign,
                    $creator,
                    $notes,
                    $est
                );
            }

            if ($stmt->execute()) {
                $lead_id = $conn->insert_id;
                addLeadAssignment($lead_id, $assigned_to, $created_by);
                logLeadActivity($lead_id, $created_by, 'created', "New lead added: " . $lead_data['name']);
                return $lead_id;
            } else {
                // log statement error for diagnostics
                @file_put_contents(__DIR__ . '/tools/import_errors.log', date('c') . " DB addLead mysqli execute failed: " . ($stmt->error ?? '') . " | conn_error:" . ($conn->error ?? mysqli_error($conn)) . PHP_EOL, FILE_APPEND | LOCK_EX);
                return false;
            }
        }
    } catch (Exception $e) {
        // Log PDO or other exceptions for diagnostics
        @file_put_contents(__DIR__ . '/tools/import_errors.log', date('c') . " DB addLead exception: " . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
        return false;
    }
    
    return false;
}

function getDashboardStats() {
    global $conn, $db_type;
    
    if (!$conn) {
        return [
            'total_leads' => 0,
            'new_leads' => 0,
            'hot_leads' => 0,
            'converted_leads' => 0,
            'monthly_data' => []
        ];
    }
    
    try {
        $stats = [];
        
        if ($db_type === 'pdo') {
            $stmt = $conn->query("SELECT COUNT(*) as total FROM leads");
            $stats['total_leads'] = $stmt->fetch()['total'];
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM leads WHERE status = 'new'");
            $stats['new_leads'] = $stmt->fetch()['total'];
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stats['recent_leads'] = $stmt->fetch()['total'];
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM leads WHERE status = 'hot'");
            $stats['hot_leads'] = $stmt->fetch()['total'];
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM leads WHERE status = 'converted'");
            $stats['converted_leads'] = $stmt->fetch()['total'];
        } else {
            $result = $conn->query("SELECT COUNT(*) as total FROM leads");
            $stats['total_leads'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM leads WHERE status = 'new'");
            $stats['new_leads'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stats['recent_leads'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM leads WHERE status = 'hot'");
            $stats['hot_leads'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM leads WHERE status = 'converted'");
            $stats['converted_leads'] = $result->fetch_assoc()['total'];
        }
        
        // Monthly data: leads created and converted per month for last 12 months
        $stats['monthly_data'] = [];
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $months[] = $month;
        }
        foreach ($months as $month) {
            $start = $month . '-01';
            $end = date('Y-m-t', strtotime($start));
            if ($db_type === 'pdo') {
                $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(estimated_value) as revenue FROM leads WHERE created_at BETWEEN ? AND ?");
                $stmt->execute([$start, $end]);
                $row = $stmt->fetch();
                $created = $row['total'];
                $revenue = $row['revenue'] ?? 0;
                
                $stmt = $conn->prepare("SELECT COUNT(*) as converted FROM leads WHERE status = 'converted' AND created_at BETWEEN ? AND ?");
                $stmt->execute([$start, $end]);
                $converted = $stmt->fetch()['converted'];
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(estimated_value) as revenue FROM leads WHERE created_at BETWEEN ? AND ?");
                $stmt->bind_param("ss", $start, $end);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $created = $row['total'];
                $revenue = $row['revenue'] ?? 0;
                
                $stmt = $conn->prepare("SELECT COUNT(*) as converted FROM leads WHERE status = 'converted' AND created_at BETWEEN ? AND ?");
                $stmt->bind_param("ss", $start, $end);
                $stmt->execute();
                $converted = $stmt->get_result()->fetch_assoc()['converted'];
            }
            $stats['monthly_data'][] = [
                'month' => $month,
                'created' => (int)$created,
                'converted' => (int)$converted,
                'revenue' => (float)$revenue
            ];
        }
        return $stats;
        
    } catch (Exception $e) {
        return [
            'total_leads' => 0,
            'new_leads' => 0,
            'hot_leads' => 0,
            'converted_leads' => 0,
            'monthly_data' => []
        ];
    }
}

// Legacy placeholders removed — full implementations are defined later in this file.

// Check DB connection helper
function db_is_connected() {
    global $conn;
    return ($conn !== null);
}

// More robust user helper functions
function getUserById($id) {
    global $conn, $db_type;
    if (!$conn) return null;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        }
    } catch (Exception $e) {
        return null;
    }
}

function getUserIdByUsername($username) {
    global $conn, $db_type;
    if (!$conn) return null;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $r = $stmt->fetch();
            return $r ? $r['id'] : null;
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();
            $r = $res->fetch_assoc();
            return $r ? $r['id'] : null;
        }
    } catch (Exception $e) {
        return null;
    }
}

function getAdmins() {
    global $conn, $db_type;
    if (!$conn) return [];
    try {
        $sql = "SELECT * FROM users WHERE role IN ('admin', 'superadmin') ORDER BY created_at DESC";
        if ($db_type === 'pdo') {
            $stmt = $conn->query($sql);
            return $stmt->fetchAll();
        } else {
            $result = $conn->query($sql);
            $rows = [];
            while ($r = $result->fetch_assoc()) $rows[] = $r;
            return $rows;
        }
    } catch (Exception $e) { return []; }
}

function getAllUsers($limit = 1000) {
    global $conn, $db_type;
    if (!$conn) return [];
    try {
        // Some PDO/MySQL drivers do not allow binding LIMIT as a parameter when
        // native prepares are enabled. Use an integer-cast interpolation which
        // is safe because we cast to int.
        $limit_int = intval($limit);
        $sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT {$limit_int}";
        if ($db_type === 'pdo') {
            $stmt = $conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $result = $conn->query($sql);
            $rows = [];
            while ($r = $result->fetch_assoc()) $rows[] = $r;
            return $rows;
        }
    } catch (Exception $e) { return []; }
}

/**
 * Create an assignment mapping between a lead and a user.
 * This makes it possible to assign a single lead to many users without duplicating rows.
 */
function addLeadAssignment($lead_id, $user_id, $assigned_by = null) {
    global $conn, $db_type;
    if (!$conn) return false;
    try {
        // Ensure table exists (safe to run repeatedly)
        $create = "CREATE TABLE IF NOT EXISTS lead_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            user_id INT NOT NULL,
            assigned_by INT DEFAULT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_lead_user (lead_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->exec = $conn->exec ?? null;
        if ($db_type === 'pdo') {
            $conn->exec($create);
        } else {
            $conn->query($create);
        }

        // Insert mapping if not exists
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("INSERT IGNORE INTO lead_assignments (lead_id, user_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->execute([$lead_id, $user_id, $assigned_by]);
            return true;
        } else {
            $stmt = $conn->prepare("INSERT IGNORE INTO lead_assignments (lead_id, user_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $lead_id, $user_id, $assigned_by);
            return $stmt->execute();
        }
    } catch (Exception $e) {
        return false;
    }
}

function createUser($data) {
    global $conn, $db_type;
    if (!$conn) return false;
    try {
        $password_hash = isset($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
        // support optional phone and branch fields (migration must run first)
        $sql = "INSERT INTO users (username, email, password_hash, full_name, role, status, phone, branch) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $data['username'],
            $data['email'],
            $password_hash,
            $data['full_name'] ?? null,
            $data['role'] ?? 'user',
            $data['status'] ?? 'active',
            $data['phone'] ?? null,
            $data['branch'] ?? null
        ];
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $newId = $conn->lastInsertId();
            // Backfill lead_assignments so the new user can see historic leads
            try {
                $create = "CREATE TABLE IF NOT EXISTS lead_assignments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    lead_id INT NOT NULL,
                    user_id INT NOT NULL,
                    assigned_by INT DEFAULT NULL,
                    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY ux_lead_user (lead_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                if (method_exists($conn, 'exec')) $conn->exec($create);
                // Insert mapping rows
                $assignSql = "INSERT IGNORE INTO lead_assignments (lead_id, user_id, assigned_by) SELECT id, ?, NULL FROM leads";
                $a = $conn->prepare($assignSql);
                $a->execute([$newId]);
            } catch (Exception $e) {
                @file_put_contents(__DIR__ . '/tools/import_errors.log', date('c') . " createUser assignment failed (pdo): " . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
            logSystemActivity($_SESSION['user_id'] ?? 0, 'create_user', "Created new user: " . $data['username']);
            return $newId;
        } else {
            $stmt = $conn->prepare($sql);
            // bind 8 string params (some may be null)
            $stmt->bind_param("ssssssss", ...$params);
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                // Backfill lead_assignments for mysqli
                try {
                    $create = "CREATE TABLE IF NOT EXISTS lead_assignments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        lead_id INT NOT NULL,
                        user_id INT NOT NULL,
                        assigned_by INT DEFAULT NULL,
                        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY ux_lead_user (lead_id, user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $conn->query($create);
                    $assignSql = "INSERT IGNORE INTO lead_assignments (lead_id, user_id, assigned_by) SELECT id, " . intval($newId) . ", NULL FROM leads";
                    $conn->query($assignSql);
                } catch (Exception $e) {
                    @file_put_contents(__DIR__ . '/tools/import_errors.log', date('c') . " createUser assignment failed (mysqli): " . ($conn->error ?? $e->getMessage()) . PHP_EOL, FILE_APPEND | LOCK_EX);
                }
                logSystemActivity($_SESSION['user_id'] ?? 0, 'create_user', "Created new user: " . $data['username']);
                return $newId;
            }
        }
    } catch (Exception $e) { return false; }
    return false;
}

function updateUser($id, $data) {
    global $conn, $db_type;
    if (!$conn) return false;
    try {
        $fields = [];
        $params = [];
        if (isset($data['email'])) { $fields[] = 'email = ?'; $params[] = $data['email']; }
        if (isset($data['full_name'])) { $fields[] = 'full_name = ?'; $params[] = $data['full_name']; }
        if (isset($data['role'])) { $fields[] = 'role = ?'; $params[] = $data['role']; }
        if (isset($data['status'])) { $fields[] = 'status = ?'; $params[] = $data['status']; }
        if (isset($data['password'])) { $fields[] = 'password_hash = ?'; $params[] = password_hash($data['password'], PASSWORD_DEFAULT); }
        if (empty($fields)) return true;
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $res = $stmt->execute($params);
            if ($res) logSystemActivity($_SESSION['user_id'] ?? 0, 'update_user', "Updated user ID: " . $id);
            return $res;
        } else {
            // bind params dynamically (all strings)
            $types = str_repeat('s', count($params));
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $res = $stmt->execute();
            if ($res) logSystemActivity($_SESSION['user_id'] ?? 0, 'update_user', "Updated user ID: " . $id);
            return $res;
        }
    } catch (Exception $e) { return false; }
}

function deleteUser($id) {
    global $conn, $db_type;
    if (!$conn) return false;
    // prevent deleting last superadmin accidentally
    try {
        $user = getUserById($id);
        if ($user && $user['role'] === 'superadmin') {
            // disallow deleting a superadmin via this function
            return false;
        }
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $res = $stmt->execute([$id]);
            if ($res) logSystemActivity($_SESSION['user_id'] ?? 0, 'delete_user', "Deleted user ID: " . $id);
            return $res;
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $res = $stmt->execute();
            if ($res) logSystemActivity($_SESSION['user_id'] ?? 0, 'delete_user', "Deleted user ID: " . $id);
            return $res;
        }
    } catch (Exception $e) { return false; }
}

// Lead helpers
function getLeadById($id) {
    global $conn, $db_type;
    if (!$conn) return null;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } else {
            $stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            return $res->fetch_assoc();
        }
    } catch (Exception $e) { return null; }
}

function updateLead($id, $lead_data) {
    global $conn, $db_type;
    if (!$conn) return false;
    try {
        $fields = [];
        $params = [];
        foreach(['name','email','phone','company','service','status','source','priority','assigned_to','notes','estimated_value'] as $f) {
            if (isset($lead_data[$f])) { $fields[] = "$f = ?"; $params[] = $lead_data[$f]; }
        }
        if (empty($fields)) return true;
        $params[] = $id;
        $sql = "UPDATE leads SET " . implode(', ', $fields) . " WHERE id = ?";
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute($params);
            if ($result) {
                logLeadActivity($id, $_SESSION['user_id'] ?? 0, 'updated', "Lead details updated");
            }
            return $result;
        } else {
            // Build types string based on field names so numeric fields use proper types
            $allowed = ['name','email','phone','company','service','status','source','priority','assigned_to','notes','estimated_value'];
            $types = '';
            foreach ($allowed as $f) {
                if (isset($lead_data[$f])) {
                    if ($f === 'assigned_to') $types .= 'i';
                    elseif ($f === 'estimated_value') $types .= 'd';
                    else $types .= 's';
                }
            }
            // id param is integer
            $types .= 'i';

            $stmt = $conn->prepare($sql);
            if ($stmt === false) return false;
            // bind_param requires references
            $bind_params = array_merge([$types], $params);
            $refs = [];
            foreach ($bind_params as $key => $value) {
                $refs[$key] = &$bind_params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $result = $stmt->execute();
            if ($result) {
                logLeadActivity($id, $_SESSION['user_id'] ?? 0, 'updated', "Lead details updated");
            }
            return $result;
        }
    } catch (Exception $e) { return false; }
}

function deleteLead($id) {
    global $conn, $db_type;
    if (!$conn) return false;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("DELETE FROM leads WHERE id = ?");
            $result = $stmt->execute([$id]);
            if ($result) logLeadActivity($id, $_SESSION['user_id'] ?? 0, 'deleted', "Lead deleted");
            return $result;
        } else {
            $stmt = $conn->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->bind_param("i", $id);
            $result = $stmt->execute();
            if ($result) logLeadActivity($id, $_SESSION['user_id'] ?? 0, 'deleted', "Lead deleted");
            return $result;
        }
    } catch (Exception $e) { return false; }
}

// Activity helpers (uses lead_activities table for recent activity)
function getRecentActivities($limit = 20) {
    global $conn, $db_type;
    if (!$conn) return [];
    try {
        $sql = "SELECT la.*, u.full_name as user_name, l.name as lead_name FROM lead_activities la LEFT JOIN users u ON la.user_id = u.id LEFT JOIN leads l ON la.lead_id = l.id ORDER BY la.activity_date DESC LIMIT ?";
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            return $rows;
        }
    } catch (Exception $e) { return []; }
}

/**
 * Log lead activities for the dashboard feed
 */
function logLeadActivity($lead_id, $user_id, $type, $message) {
    global $conn, $db_type;
    
    // 1. Log to System Activity (Global Audit) - Prioritize this
    if (function_exists('logSystemActivity')) {
        logSystemActivity($user_id, 'lead_' . $type, $message . " (Lead ID: $lead_id)");
    }

    if (!$conn) return;
    try {
        // Ensure table exists
        $sql_create = "CREATE TABLE IF NOT EXISTS lead_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT,
            user_id INT,
            activity_type VARCHAR(50),
            title TEXT,
            activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($db_type === 'pdo') $conn->exec($sql_create);
        else $conn->query($sql_create);

        $sql = "INSERT INTO lead_activities (lead_id, user_id, activity_type, title) VALUES (?, ?, ?, ?)";
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$lead_id, $user_id, $type, $message]);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $lead_id, $user_id, $type, $message);
            $stmt->execute();
        }
    } catch (Exception $e) { }
}

/**
 * Increment daily performance metrics for a user
 */
function incrementDailyMetrics($user_id, $data) {
    global $conn, $db_type;
    if (!$conn) return false;
    
    $date = date('Y-m-d');
    $followups = isset($data['followups']) ? (int)$data['followups'] : 0;
    $conversions = isset($data['conversions']) ? (int)$data['conversions'] : 0;
    $walkins = isset($data['walkins']) ? (int)$data['walkins'] : 0;
    
    if ($followups === 0 && $conversions === 0 && $walkins === 0) return true;
    
    try {
        $sql_create = "CREATE TABLE IF NOT EXISTS daily_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            metric_date DATE NOT NULL,
            followups INT DEFAULT 0,
            conversions INT DEFAULT 0,
            walkins INT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_user_date (user_id, metric_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($db_type === 'pdo') $conn->exec($sql_create);
        else $conn->query($sql_create);
        
        // Upsert: Insert or Update (Increment)
        $sql = "INSERT INTO daily_metrics (user_id, metric_date, followups, conversions, walkins) 
                VALUES (?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                followups = followups + VALUES(followups), 
                conversions = conversions + VALUES(conversions), 
                walkins = walkins + VALUES(walkins)";
                
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            return $stmt->execute([$user_id, $date, $followups, $conversions, $walkins]);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isiii", $user_id, $date, $followups, $conversions, $walkins);
            return $stmt->execute();
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get aggregated daily metrics for analytics
 */
function getDailyMetricsHistory($user_id, $role, $days = 15) {
    global $conn, $db_type;
    if (!$conn) return [];
    
    $startDate = date('Y-m-d', strtotime("-$days days"));
    $data = [];
    
    try {
        $sql = "SELECT metric_date, SUM(followups) as followups, SUM(conversions) as conversions, SUM(walkins) as walkins 
                FROM daily_metrics 
                WHERE metric_date >= ?";
        $params = [$startDate];
        
        // If not superadmin, restrict to specific user (or team logic if added later)
        if ($role !== 'superadmin') {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $sql .= " GROUP BY metric_date";
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } else {
            $stmt = $conn->prepare($sql);
            if (count($params) > 1) $stmt->bind_param("si", $params[0], $params[1]);
            else $stmt->bind_param("s", $params[0]);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($r = $result->fetch_assoc()) $rows[] = $r;
        }
        
        foreach ($rows as $r) {
            $data[$r['metric_date']] = $r;
        }
        return $data;
    } catch (Exception $e) {
        return [];
    }
}

function formatLeadValue($amount, $type = '') {
    if (empty($amount)) return '₹0.00';
    if (stripos($type, 'franchise') !== false && $amount < 1000 && $amount > 0) {
        return '₹' . number_format((float)$amount, 2) . ' L';
    }
    return '₹' . number_format((float)$amount, 2);
}

function parseAmountToINR($value, $type = '') {
    $clean = preg_replace('/[^0-9.]/', '', (string)$value);
    $float = (float)$clean;
    if (stripos($type, 'franchise') !== false && $float < 1000 && $float > 0) {
        return $float * 100000;
    }
    return $float;
}

/**
 * Log any system-wide activity for Super Admin audit
 */
function logSystemActivity($user_id, $action, $description) {
    global $conn, $db_type;
    if (!$conn) return;
    try {
        $sql_create = "CREATE TABLE IF NOT EXISTS system_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(50),
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        if ($db_type === 'pdo') $conn->exec($sql_create);
        else $conn->query($sql_create);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $sql = "INSERT INTO system_activities (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $action, $description, $ip]);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $user_id, $action, $description, $ip);
            $stmt->execute();
        }
    } catch (Exception $e) {
        // Log error to file for debugging
        @file_put_contents(__DIR__ . '/tools/import_errors.log', date('c') . " logSystemActivity error: " . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function getDashboardCounts() {
    global $conn, $db_type;
    if (!$conn) return ['total_admins'=>0,'total_users'=>0,'total_leads'=>0,'total_branches'=>0];
    try {
        if ($db_type === 'pdo') {
            $admins = $conn->query("SELECT COUNT(*) as c FROM users WHERE role IN ('admin','superadmin')")->fetch()['c'];
            $users = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'user'")->fetch()['c'];
            $leads = $conn->query("SELECT COUNT(*) as c FROM leads")->fetch()['c'];
            // branches: use companies table as branches
            $branches = $conn->query("SELECT COUNT(*) as c FROM companies")->fetch()['c'];
        } else {
            $admins = $conn->query("SELECT COUNT(*) as c FROM users WHERE role IN ('admin','superadmin')")->fetch_assoc()['c'];
            $users = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'user'")->fetch_assoc()['c'];
            $leads = $conn->query("SELECT COUNT(*) as c FROM leads")->fetch_assoc()['c'];
            $branches = $conn->query("SELECT COUNT(*) as c FROM companies")->fetch_assoc()['c'];
        }
        return ['total_admins'=>$admins,'total_users'=>$users,'total_leads'=>$leads,'total_branches'=>$branches];
    } catch (Exception $e) {
        return ['total_admins'=>0,'total_users'=>0,'total_leads'=>0,'total_branches'=>0];
    }
}

// Action Planner helpers
function getActionPlannerStats($user_id) {
    global $conn, $db_type;
    if (!$conn) return ['yet_to_call'=>[], 'call_back'=>[], 'walk_in'=>[]];
    
    try {
        $sql = "SELECT * FROM leads WHERE (assigned_to = ? OR created_by = ?) AND status IN ('new','hot','warm') ORDER BY created_at DESC";
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $user_id]);
            $leads = $stmt->fetchAll();
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $leads = [];
            while ($r = $res->fetch_assoc()) $leads[] = $r;
        }
        
        // Categorize leads
        $yet_to_call = array_filter($leads, function($l) { return ($l['notes'] ?? '') && strpos(strtolower($l['notes']), 'yet to call') !== false; });
        $call_back = array_filter($leads, function($l) { return ($l['notes'] ?? '') && strpos(strtolower($l['notes']), 'call back') !== false; });
        $walk_in = array_filter($leads, function($l) { return ($l['source'] ?? '') === 'walk-in'; });
        
        return [
            'yet_to_call' => array_values($yet_to_call),
            'call_back' => array_values($call_back),
            'walk_in' => array_values($walk_in),
            'total_actions' => count($yet_to_call) + count($call_back) + count($walk_in)
        ];
    } catch (Exception $e) {
        return ['yet_to_call'=>[], 'call_back'=>[], 'walk_in'=>[], 'total_actions'=>0];
    }
}

function updateLeadActionCategory($lead_id, $category) {
    global $conn, $db_type;
    if (!$conn) return false;
    
    // category: 'yet_to_call', 'call_back', 'walk_in'
    $notes_prefix = match($category) {
        'yet_to_call' => '[Yet to Call] ',
        'call_back' => '[Call Back] ',
        'walk_in' => '[Walk-In] ',
        default => ''
    };
    
    try {
        $lead = getLeadById($lead_id);
        if (!$lead) return false;
        
        // Remove old category prefix if exists
        $new_notes = preg_replace('/^\[(Yet to Call|Call Back|Walk-In)\]\s*/i', '', $lead['notes'] ?? '');
        $new_notes = $notes_prefix . $new_notes;
        
        if ($category === 'walk_in') {
            // Also update source
            if ($db_type === 'pdo') {
                $stmt = $conn->prepare("UPDATE leads SET source = ?, notes = ? WHERE id = ?");
                $res = $stmt->execute(['walk-in', $new_notes, $lead_id]);
            } else {
                $stmt = $conn->prepare("UPDATE leads SET source = ?, notes = ? WHERE id = ?");
                $stmt->bind_param("ssi", $source, $new_notes, $lead_id);
                $source = 'walk-in';
                $res = $stmt->execute();
            }
        } else {
            // Just update notes
            if ($db_type === 'pdo') {
                $stmt = $conn->prepare("UPDATE leads SET notes = ? WHERE id = ?");
                $res = $stmt->execute([$new_notes, $lead_id]);
            } else {
                $stmt = $conn->prepare("UPDATE leads SET notes = ? WHERE id = ?");
                $stmt->bind_param("si", $new_notes, $lead_id);
                $res = $stmt->execute();
            }
        }
        
        if ($res && function_exists('logSystemActivity')) {
            logSystemActivity($_SESSION['user_id'] ?? 0, 'action_planner', "Updated action status to '$category' for Lead ID: $lead_id");
        }
        return $res;
    } catch (Exception $e) {
        return false;
    }
}

// Require role helper
function require_role($allowed_roles = []) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], (array)$allowed_roles)) {
        header('Location: login.php');
        exit();
    }
}

// Settings helpers
function getSetting($key, $default = null) {
    global $conn, $db_type;
    if (!$conn) return $default;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $r = $stmt->fetch();
            if (!$r) return $default;
            return $r['setting_value'];
        } else {
            $stmt = $conn->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $res = $stmt->get_result();
            $r = $res->fetch_assoc();
            if (!$r) return $default;
            return $r['setting_value'];
        }
    } catch (Exception $e) { return $default; }
}

function setSetting($key, $value, $type = 'string') {
    global $conn, $db_type;
    if (!$conn) return false;
    try {
        // Upsert behavior
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $r = $stmt->fetch();
            if ($r) {
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ?, setting_type = ? WHERE setting_key = ?");
                return $stmt->execute([$value, $type, $key]);
            } else {
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)");
                return $stmt->execute([$key, $value, $type]);
            }
        } else {
            $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $res = $stmt->get_result();
            $r = $res->fetch_assoc();
            if ($r) {
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ?, setting_type = ? WHERE setting_key = ?");
                $stmt->bind_param('sss', $value, $type, $key);
                return $stmt->execute();
            } else {
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $key, $value, $type);
                return $stmt->execute();
            }
        }
    } catch (Exception $e) { return false; }
}

// Currency formatting helper
function formatCurrency($amount) {
    // Format as INR with ₹ symbol
    return '₹' . number_format((float)$amount, 2, '.', ',');
}

// Get currency symbol
function getCurrencySymbol() {
    return '₹';
}

// Get currency name
function getCurrencyName() {
    return 'INR';
}

// Duplicate checking helpers
function checkEmailExists($email, $exclude_lead_id = null) {
    global $conn, $db_type;
    if (!$conn || !$email) return false;
    
    try {
        $sql = "SELECT id FROM leads WHERE email = ?";
        $params = [$email];
        
        if ($exclude_lead_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_lead_id;
        }
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } else {
            $stmt = $conn->prepare($sql);
            if ($exclude_lead_id) {
                $stmt->bind_param("si", $email, $exclude_lead_id);
            } else {
                $stmt->bind_param("s", $email);
            }
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        }
    } catch (Exception $e) {
        return false;
    }
}

function checkPhoneExists($phone, $exclude_lead_id = null) {
    global $conn, $db_type;
    if (!$conn || !$phone) return false;
    
    try {
        $sql = "SELECT id FROM leads WHERE phone = ?";
        $params = [$phone];
        
        if ($exclude_lead_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_lead_id;
        }
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } else {
            $stmt = $conn->prepare($sql);
            if ($exclude_lead_id) {
                $stmt->bind_param("si", $phone, $exclude_lead_id);
            } else {
                $stmt->bind_param("s", $phone);
            }
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        }
    } catch (Exception $e) {
        return false;
    }
}

function getExistingLeadByEmailOrPhone($email = null, $phone = null) {
    global $conn, $db_type;
    if (!$conn) return null;
    
    try {
        $sql = "SELECT id, name, email, phone FROM leads WHERE";
        $params = [];
        $conditions = [];
        
        if ($email) {
            $conditions[] = "email = ?";
            $params[] = $email;
        }
        if ($phone) {
            $conditions[] = "phone = ?";
            $params[] = $phone;
        }
        
        if (empty($conditions)) return null;
        
        $sql .= " " . implode(" OR ", $conditions) . " LIMIT 1";
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } else {
            $stmt = $conn->prepare($sql);
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        }
    } catch (Exception $e) {
        return null;
    }
}

// ============================================
// TASK MANAGER FUNCTIONS
// ============================================

/**
 * Create a new task
 * Returns task ID on success, false on failure
 */
function createTask($title, $description, $created_by, $assigned_to, $due_date = null, $priority = 'medium', $related_type = 'general', $related_id = null) {
    global $conn, $db_type;
    if (!$conn || !$title || !$created_by || !$assigned_to) return false;
    
    try {
        if ($db_type === 'pdo') {
            $sql = "INSERT INTO tasks (title, description, created_by, assigned_to, due_date, priority, related_type, related_id, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$title, $description, $created_by, $assigned_to, $due_date, $priority, $related_type, $related_id]);
            return $conn->lastInsertId();
        } else {
            $sql = "INSERT INTO tasks (title, description, created_by, assigned_to, due_date, priority, related_type, related_id, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiissi", $title, $description, $created_by, $assigned_to, $due_date, $priority, $related_type, $related_id);
            $stmt->execute();
            return $conn->insert_id;
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get tasks based on user role
 * SuperAdmin: all tasks
 * Admin: tasks they created or assigned to them
 * User: tasks assigned to them
 */
function getTasksByRole($user_id, $role, $filters = []) {
    global $conn, $db_type;
    if (!$conn) return [];
    
    try {
        $sql = "SELECT t.*, u1.username as created_by_name, u2.username as assigned_to_name 
                FROM tasks t
                LEFT JOIN users u1 ON t.created_by = u1.id
                LEFT JOIN users u2 ON t.assigned_to = u2.id
                WHERE 1=1";
        $params = [];
        
        // Role-based access control
        if ($role === 'superadmin') {
            // SuperAdmin sees all tasks
        } elseif ($role === 'admin') {
            $sql .= " AND (t.created_by = ? OR t.assigned_to = ?)";
            $params[] = $user_id;
            $params[] = $user_id;
        } else { // user role
            $sql .= " AND t.assigned_to = ?";
            $params[] = $user_id;
        }
        
        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['created_by'])) {
            $sql .= " AND t.created_by = ?";
            $params[] = $filters['created_by'];
        }
        if (!empty($filters['related_type'])) {
            $sql .= " AND t.related_type = ?";
            $params[] = $filters['related_type'];
        }
        if (!empty($filters['related_id'])) {
            $sql .= " AND t.related_id = ?";
            $params[] = $filters['related_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Add sorting
        $sort_by = $filters['sort_by'] ?? 'due_date';
        $sort_order = $filters['sort_order'] ?? 'ASC';
        $valid_sort_columns = ['due_date', 'priority', 'created_at', 'status'];
        if (in_array($sort_by, $valid_sort_columns)) {
            $sql .= " ORDER BY t." . $sort_by . " " . ($sort_order === 'DESC' ? 'DESC' : 'ASC');
        } else {
            $sql .= " ORDER BY t.due_date ASC";
        }
        
        // Add pagination
        if (!empty($filters['limit'])) {
            $limit = (int)$filters['limit'];
            $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) return []; // Prevent crash if table missing
            if (!empty($params)) {
                $types = '';
                foreach ($params as $param) {
                    $types .= is_int($param) ? 'i' : 's';
                }
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
            return $tasks;
        }
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get a single task by ID
 */
function getTaskById($task_id) {
    global $conn, $db_type;
    if (!$conn || !$task_id) return null;
    
    try {
        $sql = "SELECT t.*, u1.username as created_by_name, u2.username as assigned_to_name 
                FROM tasks t
                LEFT JOIN users u1 ON t.created_by = u1.id
                LEFT JOIN users u2 ON t.assigned_to = u2.id
                WHERE t.id = ?";
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$task_id]);
            return $stmt->fetch();
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        }
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Update task status
 */
function updateTaskStatus($task_id, $status) {
    global $conn, $db_type;
    if (!$conn || !$task_id) return false;
    
    $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) return false;
    
    try {
        $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        
        if ($db_type === 'pdo') {
            $sql = "UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            return $stmt->execute([$status, $completed_at, $task_id]);
        } else {
            $sql = "UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $status, $completed_at, $task_id);
            return $stmt->execute();
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update task details
 */
function updateTask($task_id, $data) {
    global $conn, $db_type;
    if (!$conn || !$task_id || empty($data)) return false;
    
    try {
        $fields = [];
        $params = [];
        
        $allowed_fields = ['title', 'description', 'status', 'priority', 'assigned_to', 'due_date', 'start_date'];
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $task_id;
        $sql = "UPDATE tasks SET " . implode(", ", $fields) . " WHERE id = ?";
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            return $stmt->execute($params);
        } else {
            $stmt = $conn->prepare($sql);
            $types = '';
            foreach ($params as $param) {
                $types .= is_int($param) ? 'i' : 's';
            }
            $stmt->bind_param($types, ...$params);
            return $stmt->execute();
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Assign task to user (can only be done by creator or superadmin)
 */
function assignTask($task_id, $assigned_to, $assigned_by_role = 'user') {
    global $conn, $db_type;
    if (!$conn || !$task_id || !$assigned_to) return false;
    
    try {
        if ($db_type === 'pdo') {
            $sql = "UPDATE tasks SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $conn->prepare($sql);
            return $stmt->execute([$assigned_to, $task_id]);
        } else {
            $sql = "UPDATE tasks SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $assigned_to, $task_id);
            return $stmt->execute();
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Delete task (only by creator, assigned user, or superadmin)
 */
function deleteTask($task_id) {
    global $conn, $db_type;
    if (!$conn || !$task_id) return false;
    
    try {
        if ($db_type === 'pdo') {
            $sql = "DELETE FROM tasks WHERE id = ?";
            $stmt = $conn->prepare($sql);
            return $stmt->execute([$task_id]);
        } else {
            $sql = "DELETE FROM tasks WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $task_id);
            return $stmt->execute();
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Add comment to task
 */
function addTaskComment($task_id, $user_id, $comment) {
    global $conn, $db_type;
    if (!$conn || !$task_id || !$user_id || !$comment) return false;
    
    try {
        if ($db_type === 'pdo') {
            $sql = "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$task_id, $user_id, $comment])) {
                return $conn->lastInsertId();
            }
            return false;
        } else {
            $sql = "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $task_id, $user_id, $comment);
            if ($stmt->execute()) {
                return $conn->insert_id;
            }
            return false;
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get comments for a task
 */
function getTaskComments($task_id) {
    global $conn, $db_type;
    if (!$conn || !$task_id) return [];
    
    try {
        $sql = "SELECT tc.*, u.username, u.email 
                FROM task_comments tc
                LEFT JOIN users u ON tc.user_id = u.id
                WHERE tc.task_id = ?
                ORDER BY tc.created_at DESC";
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$task_id]);
            return $stmt->fetchAll();
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $comments = [];
            while ($row = $result->fetch_assoc()) {
                $comments[] = $row;
            }
            return $comments;
        }
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Set a task reminder
 */
function setTaskReminder($task_id, $user_id, $reminder_time, $reminder_type = 'custom') {
    global $conn, $db_type;
    if (!$conn || !$task_id || !$user_id) return false;
    
    try {
        if ($db_type === 'pdo') {
            $sql = "INSERT INTO task_reminders (task_id, user_id, reminder_time, reminder_type) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$task_id, $user_id, $reminder_time, $reminder_type])) {
                return $conn->lastInsertId();
            }
            return false;
        } else {
            $sql = "INSERT INTO task_reminders (task_id, user_id, reminder_time, reminder_type) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $task_id, $user_id, $reminder_time, $reminder_type);
            return $stmt->execute();
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get pending reminders (not yet sent)
 */
function getPendingReminders() {
    global $conn, $db_type;
    if (!$conn) return [];
    
    try {
        $sql = "SELECT tr.*, t.title, t.due_date, u.email, u.username 
                FROM task_reminders tr
                LEFT JOIN tasks t ON tr.task_id = t.id
                LEFT JOIN users u ON tr.user_id = u.id
                WHERE tr.is_sent = FALSE AND tr.reminder_time <= NOW()
                ORDER BY tr.reminder_time ASC";
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $reminders = [];
            while ($row = $result->fetch_assoc()) {
                $reminders[] = $row;
            }
            return $reminders;
        }
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Mark reminder as sent
 */
function markReminderSent($reminder_id) {
    global $conn, $db_type;
    if (!$conn || !$reminder_id) return false;
    
    try {
        if ($db_type === 'pdo') {
            $sql = "UPDATE task_reminders SET is_sent = TRUE, sent_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $conn->prepare($sql);
            return $stmt->execute([$reminder_id]);
        } else {
            $sql = "UPDATE task_reminders SET is_sent = TRUE, sent_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $reminder_id);
            return $stmt->execute();
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get task statistics for dashboard
 */
function getTaskStats($user_id, $role) {
    global $conn, $db_type;
    if (!$conn) return [];
    
    try {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0,
            'due_today' => 0,
            'assigned_by_priority' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ]
        ];
        
        // Build role-based query
        $role_condition = '';
        if ($role === 'superadmin') {
            $role_condition = '';
        } elseif ($role === 'admin') {
            $role_condition = "WHERE (created_by = ? OR assigned_to = ?)";
        } else {
            $role_condition = "WHERE assigned_to = ?";
        }
        
        // Get all tasks
        $sql = "SELECT status, priority, due_date FROM tasks $role_condition";
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            if ($role === 'superadmin') {
                $stmt->execute();
            } else {
                $stmt->execute([$user_id, $role === 'admin' ? $user_id : null]);
            }
            $tasks = $stmt->fetchAll();
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) throw new Exception($conn->error);
            if ($role !== 'superadmin') {
                if ($role === 'admin') {
                    $stmt->bind_param("ii", $user_id, $user_id);
                } else {
                    $stmt->bind_param("i", $user_id);
                }
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        }
        
        $today = date('Y-m-d');
        foreach ($tasks as $task) {
            $stats['total']++;
            $stats[$task['status']]++;
            $stats['assigned_by_priority'][$task['priority']]++;
            
            if ($task['due_date']) {
                $due_date = date('Y-m-d', strtotime($task['due_date']));
                if ($due_date < $today && $task['status'] !== 'completed') {
                    $stats['overdue']++;
                } elseif ($due_date === $today) {
                    $stats['due_today']++;
                }
            }
        }
        
        return $stats;
    } catch (Throwable $e) {
        // Return default structure to prevent dashboard crash
        return [
            'total' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0,
            'due_today' => 0,
            'assigned_by_priority' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ]
        ];
    }
}

/**
 * Inserts custom field data for a given lead.
 *
 * @param int $lead_id The ID of the lead.
 * @param array $custom_data An array of custom data, each element being ['field_id' => id, 'value' => value].
 * @return bool True on success, false on failure.
 */
function addLeadCustomData($lead_id, $custom_data) {
    global $conn, $db_type;
    if (!$conn || empty($custom_data) || !$lead_id) {
        return false;
    }

    try {
        $sql = "INSERT INTO lead_custom_data (lead_id, campaign_field_id, value) VALUES (?, ?, ?)";
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            // Use a transaction for atomicity
            $conn->beginTransaction();
            foreach ($custom_data as $data) {
                if (isset($data['field_id']) && isset($data['value'])) {
                    $stmt->execute([$lead_id, $data['field_id'], $data['value']]);
                }
            }
            $conn->commit();
            return true;
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) return false;
            
            $conn->begin_transaction();
            foreach ($custom_data as $data) {
                if (isset($data['field_id']) && isset($data['value'])) {
                    $field_id = $data['field_id'];
                    $value = $data['value'];
                    $stmt->bind_param("iis", $lead_id, $field_id, $value);
                    $stmt->execute();
                }
            }
            $conn->commit();
            return true;
        }
    } catch (Exception $e) {
        if ($db_type === 'pdo') {
            if ($conn->inTransaction()) $conn->rollBack();
        } else {
            $conn->rollback();
        }
        // Log error
        @file_put_contents(__DIR__ . '/tools/import_errors.log', date('c') . " addLeadCustomData error: " . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
        return false;
    }
}
// ============================================
// CAMPAIGN MANAGER FUNCTIONS
// ============================================

function createCampaign($name, $userId) {
    global $conn, $db_type;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("INSERT INTO campaigns (name, created_by) VALUES (?, ?)");
            $stmt->execute([$name, $userId]);
            return $conn->lastInsertId();
        } else {
            $stmt = $conn->prepare("INSERT INTO campaigns (name, created_by) VALUES (?, ?)");
            $stmt->bind_param("si", $name, $userId);
            $stmt->execute();
            return $conn->insert_id;
        }
    } catch (Exception $e) {
        return false;
    }
}

function getCampaigns() {
    global $conn, $db_type;
    try {
        $sql = "SELECT c.*, u.full_name as creator_name FROM campaigns c JOIN users u ON c.created_by = u.id ORDER BY c.created_at DESC";
        if ($db_type === 'pdo') {
            $stmt = $conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $result = $conn->query($sql);
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        }
    } catch (Exception $e) {
        return [];
    }
}

function getCampaignById($id) {
    global $conn, $db_type;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("SELECT * FROM campaigns WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->prepare("SELECT * FROM campaigns WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        }
    } catch (Exception $e) {
        return false;
    }
}

function addCampaignField($campaignId, $fieldName, $fieldType) {
    global $conn, $db_type;
    $fieldKey = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $fieldName), '_'));
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("INSERT INTO campaign_fields (campaign_id, field_name, field_key, field_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$campaignId, $fieldName, $fieldKey, $fieldType]);
            return $conn->lastInsertId();
        } else {
            $stmt = $conn->prepare("INSERT INTO campaign_fields (campaign_id, field_name, field_key, field_type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $campaignId, $fieldName, $fieldKey, $fieldType);
            $stmt->execute();
            return $conn->insert_id;
        }
    } catch (Exception $e) {
        return false;
    }
}

function getCampaignFields($campaignId) {
    global $conn, $db_type;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("SELECT * FROM campaign_fields WHERE campaign_id = ? ORDER BY id ASC");
            $stmt->execute([$campaignId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->prepare("SELECT * FROM campaign_fields WHERE campaign_id = ? ORDER BY id ASC");
            $stmt->bind_param("i", $campaignId);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        }
    } catch (Exception $e) {
        return [];
    }
}

function deleteCampaignField($fieldId, $campaignId) {
    global $conn, $db_type;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("DELETE FROM campaign_fields WHERE id = ? AND campaign_id = ?");
            $stmt->execute([$fieldId, $campaignId]);
            return $stmt->rowCount() > 0;
        } else {
            $stmt = $conn->prepare("DELETE FROM campaign_fields WHERE id = ? AND campaign_id = ?");
            $stmt->bind_param("ii", $fieldId, $campaignId);
            $stmt->execute();
            return $stmt->affected_rows > 0;
        }
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Upserts custom field data for a given lead (Insert or Update).
 *
 * @param int $lead_id The ID of the lead.
 * @param array $custom_data An array of custom data, each element being ['field_id' => id, 'value' => value].
 * @return bool True on success, false on failure.
 */
function saveLeadCustomData($lead_id, $custom_data) {
    global $conn, $db_type;
    if (!$conn || empty($custom_data) || !$lead_id) {
        return false;
    }

    try {
        $sql = "INSERT INTO lead_custom_data (lead_id, campaign_field_id, value) VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE value = VALUES(value)";
        
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $conn->beginTransaction();
            foreach ($custom_data as $data) {
                if (isset($data['field_id'])) {
                    $stmt->execute([$lead_id, $data['field_id'], $data['value'] ?? '']);
                }
            }
            $conn->commit();
            return true;
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) return false;
            $conn->begin_transaction();
            foreach ($custom_data as $data) {
                if (isset($data['field_id'])) {
                    $val = $data['value'] ?? '';
                    $stmt->bind_param("iis", $lead_id, $data['field_id'], $val);
                    $stmt->execute();
                }
            }
            $conn->commit();
            return true;
        }
    } catch (Exception $e) {
        if ($db_type === 'pdo' && $conn->inTransaction()) $conn->rollBack();
        elseif ($db_type === 'mysqli') $conn->rollback();
        return false;
    }
}

// ============================================
// LEAD MANAGEMENT REDESIGN FUNCTIONS
// ============================================

/**
 * Link a lead to a campaign (Bridge Table)
 */
function ensureLeadCampaignsTable() {
    global $conn, $db_type;
    if (!$conn) return false;

    static $ready = null;
    if ($ready !== null) return $ready;

    try {
        $exists = false;
        if ($db_type === 'pdo') {
            $stmt = $conn->query("SHOW TABLES LIKE 'lead_campaigns'");
            $exists = $stmt && $stmt->fetch() ? true : false;
        } else {
            $res = $conn->query("SHOW TABLES LIKE 'lead_campaigns'");
            $exists = $res && $res->num_rows > 0;
        }

        if (!$exists) {
            $sql = "CREATE TABLE IF NOT EXISTS lead_campaigns (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        lead_id INT NOT NULL,
                        campaign_id INT NOT NULL,
                        imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY ux_lead_campaign (lead_id, campaign_id),
                        INDEX idx_campaign_id (campaign_id),
                        INDEX idx_lead_id (lead_id),
                        CONSTRAINT fk_lc_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
                        CONSTRAINT fk_lc_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            if ($db_type === 'pdo') {
                $conn->exec($sql);
            } else {
                $conn->query($sql);
            }
        }

        $ready = true;
        return true;
    } catch (Exception $e) {
        $ready = false;
        return false;
    }
}

function linkLeadToCampaign($leadId, $campaignId) {
    global $conn, $db_type;
    if (!$conn || !$leadId || !$campaignId) return false;
    if (!ensureLeadCampaignsTable()) return false;
    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare("INSERT IGNORE INTO lead_campaigns (lead_id, campaign_id) VALUES (?, ?)");
            return $stmt->execute([$leadId, $campaignId]);
        } else {
            $stmt = $conn->prepare("INSERT IGNORE INTO lead_campaigns (lead_id, campaign_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $leadId, $campaignId);
            return $stmt->execute();
        }
    } catch (Exception $e) { return false; }
}

/**
 * Sync Lead from Campaign Import
 * - Updates if exists (email/phone)
 * - Creates if new
 * - Links to campaign
 */
function syncCampaignLead($leadData, $campaignId) {
    $existing = getExistingLeadByEmailOrPhone($leadData['email'] ?? null, $leadData['phone'] ?? null);
    
    if ($existing) {
        $leadId = $existing['id'];
        // Update existing lead
        updateLead($leadId, $leadData);
    } else {
        // Create new lead
        $leadId = addLead($leadData);
    }

    if ($leadId && $campaignId) {
        linkLeadToCampaign($leadId, $campaignId);
    }

    return $leadId;
}

/**
 * Advanced Lead Fetching with Joins and Filters
 * Scalable query for dashboard
 */
function getLeadsAdvanced($user_id = null, $role = null, $filters = []) {
    global $conn, $db_type, $pdo;
    if (!$conn) return [];

    try {
        $has_lead_campaigns = ensureLeadCampaignsTable();
        static $has_campaign_id_column = null;
        if ($has_campaign_id_column === null) {
            $has_campaign_id_column = false;
            try {
                if ($db_type === 'pdo') {
                    $colStmt = $conn->query("SHOW COLUMNS FROM leads LIKE 'campaign_id'");
                    $has_campaign_id_column = $colStmt && $colStmt->fetch() ? true : false;
                } else {
                    $colRes = $conn->query("SHOW COLUMNS FROM leads LIKE 'campaign_id'");
                    $has_campaign_id_column = $colRes && $colRes->num_rows > 0;
                }
            } catch (Exception $e) {
                $has_campaign_id_column = false;
            }
        }

        $params = [];
        if ($has_lead_campaigns && $has_campaign_id_column) {
            $campaign_count_expr = "((SELECT COUNT(DISTINCT lc_count.campaign_id) FROM lead_campaigns lc_count WHERE lc_count.lead_id = l.id)
                                     + CASE
                                         WHEN l.campaign_id IS NOT NULL
                                              AND NOT EXISTS (
                                                  SELECT 1 FROM lead_campaigns lc_has
                                                  WHERE lc_has.lead_id = l.id AND lc_has.campaign_id = l.campaign_id
                                              )
                                         THEN 1 ELSE 0
                                       END)";
            $latest_campaign_expr = "COALESCE(
                                        (SELECT c.name
                                         FROM campaigns c
                                         JOIN lead_campaigns lc_latest ON c.id = lc_latest.campaign_id
                                         WHERE lc_latest.lead_id = l.id
                                         ORDER BY lc_latest.imported_at DESC
                                         LIMIT 1),
                                        (SELECT c2.name FROM campaigns c2 WHERE c2.id = l.campaign_id LIMIT 1)
                                     )";
            $latest_campaign_id_expr = "COALESCE(
                                        (SELECT lc_latest.campaign_id
                                         FROM lead_campaigns lc_latest
                                         WHERE lc_latest.lead_id = l.id
                                         ORDER BY lc_latest.imported_at DESC
                                         LIMIT 1),
                                        l.campaign_id
                                     )";
        } elseif ($has_lead_campaigns) {
            $campaign_count_expr = "(SELECT COUNT(DISTINCT lc_count.campaign_id) FROM lead_campaigns lc_count WHERE lc_count.lead_id = l.id)";
            $latest_campaign_expr = "(SELECT c.name FROM campaigns c JOIN lead_campaigns lc_latest ON c.id = lc_latest.campaign_id WHERE lc_latest.lead_id = l.id ORDER BY lc_latest.imported_at DESC LIMIT 1)";
            $latest_campaign_id_expr = "(SELECT lc_latest.campaign_id FROM lead_campaigns lc_latest WHERE lc_latest.lead_id = l.id ORDER BY lc_latest.imported_at DESC LIMIT 1)";
        } else {
            $campaign_count_expr = "0";
            $latest_campaign_expr = "NULL";
            $latest_campaign_id_expr = "NULL";
        }

        $sql = "SELECT l.*,
                       u1.full_name as assigned_to_name,
                       u2.full_name as created_by_name,
                          {$campaign_count_expr} as campaign_count,
                          {$latest_campaign_expr} as latest_campaign,
                          {$latest_campaign_id_expr} as latest_campaign_id
                FROM leads l
                LEFT JOIN users u1 ON l.assigned_to = u1.id 
                LEFT JOIN users u2 ON l.created_by = u2.id
                WHERE 1=1";

        // Role-based access
        if ($role === 'user' && $user_id) {
            $sql .= " AND (l.assigned_to = ? OR l.created_by = ?)";
            $params[] = (int)$user_id;
            $params[] = (int)$user_id;
        }

        // Filters
        if (!empty($filters['status'])) {
            $sql .= " AND l.status = ?";
            $params[] = (string)$filters['status'];
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND l.assigned_to = ?";
            $params[] = (int)$filters['assigned_to'];
        }
        if (!empty($filters['campaign_id'])) {
            // Support both bridge-table links and legacy direct campaign_id rows where available.
            if ($has_lead_campaigns && $has_campaign_id_column) {
                $sql .= " AND (EXISTS (SELECT 1 FROM lead_campaigns lc_filter WHERE lc_filter.lead_id = l.id AND lc_filter.campaign_id = ?) OR l.campaign_id = ?)";
                $params[] = (int)$filters['campaign_id'];
            } elseif ($has_lead_campaigns) {
                $sql .= " AND EXISTS (SELECT 1 FROM lead_campaigns lc_filter WHERE lc_filter.lead_id = l.id AND lc_filter.campaign_id = ?)";
            } elseif ($has_campaign_id_column) {
                $sql .= " AND l.campaign_id = ?";
            } else {
                // Campaign filtering is not possible without link table or leads.campaign_id
                return [];
            }
            $params[] = (int)$filters['campaign_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " GROUP BY l.id ORDER BY l.created_at DESC";

        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $types = '';
                foreach($params as $p) {
                    if(is_int($p)) $types .= 'i';
                    else $types .= 's';
                }
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) { return []; }
}
