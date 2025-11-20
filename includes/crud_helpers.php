<?php
/**
 * CRUD Helper Functions
 * Shared functions for common database operations to reduce code duplication
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Check if a unique field value already exists in a table
 * @param string $table Table name
 * @param string $field Field name to check
 * @param mixed $value Value to check
 * @param int|null $excludeId ID to exclude from check (for updates)
 * @return bool True if exists, false otherwise
 */
/**
 * Validate table and field names to prevent SQL injection
 */
function validateTableFieldName($name) {
    // Only allow alphanumeric and underscore
    return preg_match('/^[a-zA-Z0-9_]+$/', $name) && strlen($name) <= 64;
}

function checkUniqueFieldExists($table, $field, $value, $excludeId = null) {
    try {
        // Validate table and field names to prevent SQL injection
        if (!validateTableFieldName($table) || !validateTableFieldName($field)) {
            throw new Exception("Invalid table or field name");
        }
        
        $pdo = Database::getInstance()->getConnection();
        
        // Use backticks for identifiers and prepared statements for values
        $tableEscaped = "`{$table}`";
        $fieldEscaped = "`{$field}`";
        $idFieldEscaped = "`{$table}_id`";
        
        if ($excludeId !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableEscaped} WHERE {$fieldEscaped} = ? AND {$idFieldEscaped} != ?");
            $stmt->execute([$value, $excludeId]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableEscaped} WHERE {$fieldEscaped} = ?");
            $stmt->execute([$value]);
        }
        
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Error checking unique field: " . $e->getMessage());
        throw new Exception("Database error while checking unique field");
    }
}

/**
 * Get all records from a table
 * @param string $table Table name
 * @param string $orderBy Order by clause (default: primary key)
 * @param array $whereConditions Optional WHERE conditions ['field' => 'value']
 * @return array Array of records
 */
function getAllRecords($table, $orderBy = null, $whereConditions = []) {
    try {
        // Validate table name
        if (!validateTableFieldName($table)) {
            throw new Exception("Invalid table name");
        }
        
        $pdo = Database::getInstance()->getConnection();
        
        $tableEscaped = "`{$table}`";
        $sql = "SELECT * FROM {$tableEscaped}";
        $params = [];
        
        if (!empty($whereConditions)) {
            $whereParts = [];
            foreach ($whereConditions as $field => $value) {
                if (!validateTableFieldName($field)) {
                    throw new Exception("Invalid field name: {$field}");
                }
                $fieldEscaped = "`{$field}`";
                $whereParts[] = "{$fieldEscaped} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $whereParts);
        }
        
        if ($orderBy) {
            // Validate orderBy field name
            if (!validateTableFieldName(str_replace(' ', '', $orderBy))) {
                throw new Exception("Invalid order by field");
            }
            $orderByEscaped = "`{$orderBy}`";
            $sql .= " ORDER BY {$orderByEscaped}";
        } else {
            // Default to primary key
            $idFieldEscaped = "`{$table}_id`";
            $sql .= " ORDER BY {$idFieldEscaped}";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching records from {$table}: " . $e->getMessage());
        throw new Exception("Database error while fetching records");
    }
}

/**
 * Get a single record by ID
 * @param string $table Table name
 * @param int $id Record ID
 * @return array|null Record data or null if not found
 */
function getRecordById($table, $id) {
    try {
        // Validate table name
        if (!validateTableFieldName($table)) {
            throw new Exception("Invalid table name");
        }
        
        $pdo = Database::getInstance()->getConnection();
        $tableEscaped = "`{$table}`";
        $idFieldEscaped = "`{$table}_id`";
        $stmt = $pdo->prepare("SELECT * FROM {$tableEscaped} WHERE {$idFieldEscaped} = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record ?: null;
    } catch (Exception $e) {
        error_log("Error fetching record from {$table} with ID {$id}: " . $e->getMessage());
        throw new Exception("Database error while fetching record");
    }
}

/**
 * Insert a new record
 * @param string $table Table name
 * @param array $data Associative array of field => value
 * @return int New record ID
 */
function insertRecord($table, $data) {
    try {
        // Validate table name
        if (!validateTableFieldName($table)) {
            throw new Exception("Invalid table name");
        }
        
        $pdo = Database::getInstance()->getConnection();
        
        $fields = array_keys($data);
        // Validate all field names
        foreach ($fields as $field) {
            if (!validateTableFieldName($field)) {
                throw new Exception("Invalid field name: {$field}");
            }
        }
        
        $placeholders = array_fill(0, count($fields), '?');
        $values = array_values($data);
        
        // Escape table and field names
        $tableEscaped = "`{$table}`";
        $fieldsEscaped = array_map(function($f) { return "`{$f}`"; }, $fields);
        
        $sql = "INSERT INTO {$tableEscaped} (" . implode(', ', $fieldsEscaped) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error inserting record into {$table}: " . $e->getMessage());
        throw new Exception("Database error while inserting record: " . ($e->getCode() == 23000 ? "Duplicate entry or constraint violation" : "Insert failed"));
    }
}

/**
 * Update a record by ID
 * @param string $table Table name
 * @param int $id Record ID
 * @param array $data Associative array of field => value
 * @return bool True if successful
 */
function updateRecord($table, $id, $data) {
    try {
        // Validate table name
        if (!validateTableFieldName($table)) {
            throw new Exception("Invalid table name");
        }
        
        $pdo = Database::getInstance()->getConnection();
        
        $fields = array_keys($data);
        // Validate all field names
        foreach ($fields as $field) {
            if (!validateTableFieldName($field)) {
                throw new Exception("Invalid field name: {$field}");
            }
        }
        
        // Escape table and field names
        $tableEscaped = "`{$table}`";
        $idFieldEscaped = "`{$table}_id`";
        $setParts = array_map(function($field) {
            $fieldEscaped = "`{$field}`";
            return "{$fieldEscaped} = ?";
        }, $fields);
        $values = array_values($data);
        $values[] = $id; // Add ID for WHERE clause
        
        $sql = "UPDATE {$tableEscaped} SET " . implode(', ', $setParts) . " WHERE {$idFieldEscaped} = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error updating record in {$table} with ID {$id}: " . $e->getMessage());
        throw new Exception("Database error while updating record: " . ($e->getCode() == 23000 ? "Duplicate entry or constraint violation" : "Update failed"));
    }
}

/**
 * Delete a record by ID
 * @param string $table Table name
 * @param int $id Record ID
 * @return bool True if successful
 */
function deleteRecord($table, $id) {
    try {
        // Validate table name
        if (!validateTableFieldName($table)) {
            throw new Exception("Invalid table name");
        }
        
        $pdo = Database::getInstance()->getConnection();
        $tableEscaped = "`{$table}`";
        $idFieldEscaped = "`{$table}_id`";
        $stmt = $pdo->prepare("DELETE FROM {$tableEscaped} WHERE {$idFieldEscaped} = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        // Check for foreign key constraint violations
        if ($e->getCode() == 23000) {
            error_log("Cannot delete record from {$table} with ID {$id}: Foreign key constraint");
            throw new Exception("Cannot delete this record because it is referenced by other records");
        }
        error_log("Error deleting record from {$table} with ID {$id}: " . $e->getMessage());
        throw new Exception("Database error while deleting record");
    }
}

/**
 * Handle form submission for create/update operations
 * @param string $table Table name
 * @param array $fields Array of field names to process from $_POST
 * @param string $uniqueField Field name to check for uniqueness
 * @param string $successMessage Success message
 * @param callable|null $beforeInsert Optional callback before insert: function($data) { return $data; }
 * @param callable|null $beforeUpdate Optional callback before update: function($data, $id) { return $data; }
 * @param callable|null $afterInsert Optional callback after insert: function($newId) { }
 * @param callable|null $afterUpdate Optional callback after update: function($id) { }
 * @return array ['success' => bool, 'message' => string, 'id' => int|null]
 */
function handleFormSubmission($table, $fields, $uniqueField = null, $successMessage = 'Record saved successfully', $beforeInsert = null, $beforeUpdate = null, $afterInsert = null, $afterUpdate = null) {
    try {
        // Validate table name
        if (!validateTableFieldName($table)) {
            throw new Exception("Invalid table name");
        }
        
        // Validate all field names
        foreach ($fields as $field) {
            if (!validateTableFieldName($field)) {
                throw new Exception("Invalid field name: {$field}");
            }
        }
        
        if ($uniqueField && !validateTableFieldName($uniqueField)) {
            throw new Exception("Invalid unique field name: {$uniqueField}");
        }
        
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();
        
        $idField = $table . '_id';
        $isUpdate = isset($_POST[$idField]) && !empty($_POST[$idField]);
        $id = $isUpdate ? (int)$_POST[$idField] : null;
        
        // Collect data from POST
        $data = [];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                // Convert empty strings to null for optional fields
                $data[$field] = ($value === '') ? null : $value;
            }
        }
        
        // Check unique field if provided
        if ($uniqueField && isset($data[$uniqueField])) {
            if (checkUniqueFieldExists($table, $uniqueField, $data[$uniqueField], $id)) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => ucfirst(str_replace('_', ' ', $uniqueField)) . ' already exists',
                    'id' => null
                ];
            }
        }
        
        // Call before hooks
        if ($isUpdate && $beforeUpdate) {
            $data = call_user_func($beforeUpdate, $data, $id);
        } elseif (!$isUpdate && $beforeInsert) {
            $data = call_user_func($beforeInsert, $data);
        }
        
        // Perform insert or update
        if ($isUpdate) {
            updateRecord($table, $id, $data);
            if ($afterUpdate) {
                call_user_func($afterUpdate, $id);
            }
        } else {
            $newId = insertRecord($table, $data);
            if ($afterInsert) {
                call_user_func($afterInsert, $newId);
            }
            $id = $newId;
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => $successMessage,
            'id' => $id
        ];
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Form submission error for {$table}: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'id' => null
        ];
    }
}

/**
 * Improved error handler for better error messages
 * @param Exception $e Exception object
 * @param string $context Context description
 * @param bool $showDetails Whether to show detailed error (for development)
 * @return string User-friendly error message
 */
function getErrorMessage($e, $context = 'Operation', $showDetails = false) {
    // In production, don't expose database details
    $isDevelopment = defined('APP_ENV') && APP_ENV === 'development';
    $showDetails = $showDetails || $isDevelopment;
    
    if ($e instanceof PDOException) {
        $code = $e->getCode();
        
        // Handle specific error codes
        switch ($code) {
            case 23000: // Integrity constraint violation
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    return 'This record already exists. Please check for duplicates.';
                }
                if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                    return 'Cannot perform this operation because related records exist.';
                }
                return 'Data integrity violation. Please check your input.';
                
            case 42000: // Syntax error
                return 'Database query error. Please contact support.';
                
            case 'HY000': // General error
                if (strpos($e->getMessage(), 'Connection') !== false) {
                    return 'Database connection failed. Please try again later.';
                }
                break;
        }
        
        // Generic database error
        if ($showDetails) {
            return "Database error: " . $e->getMessage();
        }
        return "{$context} failed due to a database error. Please try again.";
    }
    
    // Generic exception
    if ($showDetails) {
        return "Error: " . $e->getMessage();
    }
    return "{$context} failed. Please try again.";
}

/**
 * Log error with context
 * @param Exception $e Exception object
 * @param string $context Context description
 * @param array $additionalData Additional data to log
 */
if (!function_exists('logError')) {
    function logError($e, $context = 'Error', $additionalData = []) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $user = isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_user_id'] ?? 'Admin') : 'Guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'context' => $context,
            'user' => $user,
            'ip' => $ip,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString()
        ];
        
        if (!empty($additionalData)) {
            $logEntry['additional'] = $additionalData;
        }
        
        $logLine = json_encode($logEntry) . PHP_EOL;
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log
        error_log("{$context}: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    }
}
?>

