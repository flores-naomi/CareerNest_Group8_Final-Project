<?php
require_once __DIR__ . '/../config/db.php';

// Get user name
function getUserName() {
    return $_SESSION['name'] ?? '';
}

// Get user ID
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Database connection
function getDbConnection() {
    global $pdo;
    return $pdo;
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Get user details
function getUserDetails($userId) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
        
        return [
            'success' => true,
            'data' => $result
        ];
    } catch (PDOException $e) {
        return handleError($e, 'getUserDetails');
    }
}

// Get company details
function getCompanyDetails($companyId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    return $stmt->fetch();
}

// Get job details
function getJobDetails($jobId) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT jl.*, c.company_name, c.location as company_location
            FROM job_listings jl
            JOIN companies c ON jl.company_id = c.id
            WHERE jl.id = ?
        ");
        $stmt->execute([$jobId]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Job not found'
            ];
        }
        
        return [
            'success' => true,
            'data' => $result
        ];
    } catch (PDOException $e) {
        return handleError($e, 'getJobDetails');
    }
}

// Check if user has applied for a job
function hasAppliedForJob($userId, $jobId) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT id FROM job_applications 
            WHERE user_id = ? AND job_id = ?
        ");
        $stmt->execute([$userId, $jobId]);
        return [
            'success' => true,
            'hasApplied' => $stmt->fetch() ? true : false
        ];
    } catch (PDOException $e) {
        return handleError($e, 'hasAppliedForJob');
    }
}

// Get user's applications
function getUserApplications($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ja.*, jl.title as job_title, c.company_name
        FROM job_applications ja
        JOIN job_listings jl ON ja.job_id = jl.id
        JOIN companies c ON jl.company_id = c.id
        WHERE ja.user_id = ?
        ORDER BY ja.applied_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// Get company's job listings
function getCompanyJobs($companyId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT jl.*, COUNT(ja.id) as application_count
        FROM job_listings jl
        LEFT JOIN job_applications ja ON jl.id = ja.job_id
        WHERE jl.company_id = ?
        GROUP BY jl.id
        ORDER BY jl.date_posted DESC
    ");
    $stmt->execute([$companyId]);
    return $stmt->fetchAll();
}

// Get job applications for a company
function getJobApplications($jobId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ja.*, u.name as applicant_name, u.email as applicant_email
        FROM job_applications ja
        JOIN users u ON ja.user_id = u.id
        WHERE ja.job_id = ?
        ORDER BY ja.applied_at DESC
    ");
    $stmt->execute([$jobId]);
    return $stmt->fetchAll();
}

// Create notification
function createNotification($userId, $title, $type, $message, $link = null) {
    return executeInTransaction(function() use ($userId, $title, $type, $message, $link) {
        global $pdo;
        try {
            // Check if similar notification exists within last 5 minutes
            $stmt = $pdo->prepare("
                SELECT id FROM notifications 
                WHERE user_id = ? 
                AND title = ? 
                AND type = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute([$userId, $title, $type]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Similar notification already exists'
                ];
            }

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, type, message, link, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $title, $type, $message, $link]);
            
            return [
                'success' => true,
                'message' => 'Notification created successfully'
            ];
        } catch (PDOException $e) {
            throw $e;
        }
    });
}

// Get user notifications
function getUserNotifications($userId, $limit = 10) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

// Mark notification as read
function markNotificationAsRead($notificationId) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    return $stmt->execute([$notificationId]);
}

// Get unread notification count
function getUnreadNotificationCount($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'];
}

// Check if user has permission
function hasPermission($requiredRole) {
    $userRole = getUserRole();
    if ($requiredRole === 'admin') {
        return $userRole === 'admin';
    } elseif ($requiredRole === 'company') {
        return $userRole === 'admin' || $userRole === 'company';
    }
    return true;
}

// Redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit();
}

// Validate file upload
function validateFileUpload($file, $allowedTypes, $maxSize) {
    try {
        $errors = [];
        
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid file parameters');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "File size exceeds the maximum limit.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = "File was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = "No file was uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = "Missing a temporary folder.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errors[] = "Failed to write file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errors[] = "A PHP extension stopped the file upload.";
                break;
            default:
                $errors[] = "Unknown upload error.";
                break;
        }
        
        if ($file['size'] > $maxSize) {
            $errors[] = "File size exceeds the maximum limit of " . formatFileSize($maxSize);
        }
        
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowedTypes);
        }
        
        // Additional security check
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, array_map(function($ext) {
            return 'application/' . $ext;
        }, $allowedTypes))) {
            $errors[] = "Invalid file type detected.";
        }
        
        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    } catch (Exception $e) {
        return handleError($e, 'validateFileUpload');
    }
}

// Helper function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Generate unique filename
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Get time ago
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date('M d, Y', $time);
    }
}

// Get all interview schedules for a company
function getCompanySchedules($companyId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT s.*, ja.job_id, jl.title as job_title, u.name as applicant_name, 
               s.status as schedule_status, s.interview_date, s.interview_time, 
               COALESCE(i.interview_mode, s.interview_type) as interview_mode,
               COALESCE(i.interview_location, s.location) as location,
               i.interview_link,
               i.status as interview_status
        FROM schedules s
        JOIN job_applications ja ON s.application_id = ja.id
        JOIN job_listings jl ON ja.job_id = jl.id
        JOIN users u ON ja.user_id = u.id
        LEFT JOIN interviews i ON s.application_id = i.application_id 
            AND s.interview_date = i.interview_date
        WHERE jl.company_id = ?
        ORDER BY s.interview_date DESC, s.interview_time DESC
    ");
    $stmt->execute([$companyId]);
    return $stmt->fetchAll();
}

// Get all interview schedules for admin (no duplicates)
function getAllSchedules() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.*, ja.job_id, jl.title as job_title, u.name as applicant_name, c.company_name, s.status as schedule_status, s.interview_date, s.interview_time, s.interview_type, s.location
        FROM schedules s
        JOIN job_applications ja ON s.application_id = ja.id
        JOIN job_listings jl ON ja.job_id = jl.id
        JOIN users u ON ja.user_id = u.id
        JOIN companies c ON jl.company_id = c.id
        ORDER BY s.interview_date DESC, s.interview_time DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Helper function for status badge colors
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'proposed':
            return 'warning';
        case 'admin_modified':
            return 'info';
        case 'company_confirmed':
            return 'primary';
        case 'finalized':
            return 'success';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

// Check if interview slot is available
function isSlotAvailable($pdo, $interview_date, $interview_time, $company_id, $application_id = null) {
    // First check if there's already a schedule for this application
    if ($application_id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM schedules s
            WHERE s.application_id = ? 
            AND s.status NOT IN ('cancelled', 'rejected')
        ");
        $stmt->execute([$application_id]);
        if ($stmt->fetchColumn() > 0) {
            return false; // Already has a schedule
        }
    }

    // Then check for time slot availability
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM schedules s
        JOIN job_listings jl ON s.job_id = jl.id
        WHERE s.interview_date = ? 
        AND s.interview_time = ? 
        AND jl.company_id = ?
        AND s.status NOT IN ('cancelled', 'rejected')
    ");
    $stmt->execute([$interview_date, $interview_time, $company_id]);
    return $stmt->fetchColumn() == 0;
}

// Error handling function
function handleError($error, $context = '') {
    error_log("Error in $context: " . $error->getMessage());
    return [
        'success' => false,
        'message' => 'An error occurred. Please try again later.',
        'error' => $error->getMessage()
    ];
}

// Transaction wrapper function
function executeInTransaction($callback) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        $result = $callback();
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        return handleError($e, 'Transaction');
    }
}
?>