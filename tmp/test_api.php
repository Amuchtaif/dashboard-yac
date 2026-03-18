<?php
$url = 'http://localhost/dashboard-yac/api/boarding/submit_attendance.php';
$data = [
    'room_id' => 1,
    'date' => date('Y-m-d'),
    'created_by' => 1,
    'attendance' => [
        [
            'student_id' => 1,
            'status' => 'Hadir',
            'notes' => 'Test'
        ]
    ]
];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo "Response headers: " . print_r($http_response_header, true) . "\n";
echo "Response body: " . $result . "\n";
