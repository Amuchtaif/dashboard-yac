<?php
/**
 * Submit Meeting Attendance API
 * Endpoint: POST /api/submit_meeting_attendance.php
 * 
 * This API records a user's attendance for a specific meeting.
 * 
 * Request Body (JSON):
 * {
 *   "meeting_id": 1,
 *   "user_id": 5,
 *   "attended_at": "2026-02-06T16:45:00+07:00"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Absensi berhasil dicatat!",
 *   "data": {
 *     "attendance_id": 1,
 *     "meeting_id": 1,
 *     "user_id": 5,
 *     "attended_at": "2026-02-06 16:45:00"
 *   }
 * }
 */

// Enable Error Reporting for Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

// Database configuration - adjust these values
$host = 'localhost';
$db_name = 'attendance_db';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

// Get and decode request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (empty($data['meeting_id']) || empty($data['user_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'meeting_id and user_id are required.'
    ]);
    exit();
}

$meeting_id = intval($data['meeting_id']);
$user_id = intval($data['user_id']);
$attended_at = isset($data['attended_at']) 
    ? date('Y-m-d H:i:s', strtotime($data['attended_at'])) 
    : date('Y-m-d H:i:s');

try {
    // Check if meeting exists
    $checkMeeting = $conn->prepare("SELECT id, title FROM meetings WHERE id = ?");
    $checkMeeting->execute([$meeting_id]);
    $meeting = $checkMeeting->fetch(PDO::FETCH_ASSOC);
    
    if (!$meeting) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Rapat tidak ditemukan.'
        ]);
        exit();
    }

    // Check if user is a participant
    $checkParticipant = $conn->prepare(
        "SELECT id, status FROM meeting_participants WHERE meeting_id = ? AND employee_id = ?"
    );
    $checkParticipant->execute([$meeting_id, $user_id]);
    $participant = $checkParticipant->fetch(PDO::FETCH_ASSOC);
    
    if (!$participant) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Anda bukan peserta rapat ini.'
        ]);
        exit();
    }

    // Check if user has already attended (status is present)
    if ($participant['status'] === 'present') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Anda sudah melakukan absensi untuk rapat ini.'
        ]);
        exit();
    }

    // Update attendance record in meeting_participants
    $stmt = $conn->prepare(
        "UPDATE meeting_participants 
         SET status = 'present', attendance_time = ? 
         WHERE meeting_id = ? AND employee_id = ?"
    );
    $stmt->execute([$attended_at, $meeting_id, $user_id]);
    
    // Use the participant ID as the reference ID
    $attendance_id = $participant['id'];

    echo json_encode([
        'success' => true,
        'message' => 'Absensi berhasil dicatat untuk rapat "' . $meeting['title'] . '"!',
        'data' => [
            'attendance_id' => intval($attendance_id),
            'meeting_id' => $meeting_id,
            'user_id' => $user_id,
            'attended_at' => $attended_at
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
