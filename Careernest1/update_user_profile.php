<?php
require_once 'config/db.php';
require_once 'includes/session.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a regular user
if (!isLoggedIn() || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $location = trim($_POST['location']);
    $bio = trim($_POST['bio']);
    $skills = trim($_POST['skills']);
    $education = trim($_POST['education']);
    $experience = trim($_POST['experience']);
    
    try {
        $pdo->beginTransaction();
        
        // Update users table
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $_SESSION['user_id']]);
        
        // Check if user profile exists
        $stmt = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile_exists = $stmt->fetch();
        
        // Handle resume upload first
        $resume_path = null;
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            // Debug information
            error_log("File upload started");
            error_log("File details: " . print_r($_FILES['resume'], true));
            
            $upload_dir = 'uploads/resumes/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                error_log("Creating directory: " . $upload_dir);
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create upload directory");
                }
                // Set directory permissions
                chmod($upload_dir, 0777);
            }
            
            // Validate file type
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $file_type = mime_content_type($_FILES['resume']['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only PDF and Word documents are allowed.");
            }
            
            // Get file extension
            $file_extension = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            error_log("File extension: " . $file_extension);
            
            // Generate unique filename
            $new_filename = 'resume_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            error_log("Upload path: " . $upload_path);
            
            // Try to move the file
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_path)) {
                error_log("File successfully moved to: " . $upload_path);
                $resume_path = $upload_path;
                // Set file permissions
                chmod($upload_path, 0644);
            } else {
                $upload_error = error_get_last();
                error_log("Failed to move uploaded file. Error: " . ($upload_error ? $upload_error['message'] : 'Unknown error'));
                throw new Exception("Failed to upload resume file. Please try again.");
            }
        } else if (isset($_FILES['resume'])) {
            $error_message = "File upload error: ";
            switch ($_FILES['resume']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error_message .= "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message .= "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message .= "The uploaded file was only partially uploaded";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message .= "No file was uploaded";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message .= "Missing a temporary folder";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message .= "Failed to write file to disk";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message .= "A PHP extension stopped the file upload";
                    break;
                default:
                    $error_message .= "Unknown upload error";
            }
            error_log($error_message);
            throw new Exception($error_message);
        }
        
        if ($profile_exists) {
            // Update existing profile
            $stmt = $pdo->prepare("
                UPDATE user_profiles 
                SET phone = ?, 
                    location = ?, 
                    bio = ?, 
                    skills = ?, 
                    education = ?, 
                    experience = ?,
                    resume_path = COALESCE(?, resume_path)
                WHERE user_id = ?
            ");
            $stmt->execute([
                $phone, 
                $location, 
                $bio, 
                $skills,
                $education, 
                $experience,
                $resume_path,
                $_SESSION['user_id']
            ]);
            error_log("Profile updated with resume path: " . $resume_path);
        } else {
            // Create new profile
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles 
                (user_id, phone, location, bio, skills, education, experience, resume_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], 
                $phone, 
                $location, 
                $bio,
                $skills, 
                $education, 
                $experience,
                $resume_path
            ]);
            error_log("New profile created with resume path: " . $resume_path);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Profile updated successfully!";
        header('Location: user_dashboard.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in update_user_profile.php: " . $e->getMessage());
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
        header('Location: user_profile.php');
        exit();
    }
} else {
    // If not a POST request, redirect to profile page
    header('Location: user_profile.php');
    exit();
} 