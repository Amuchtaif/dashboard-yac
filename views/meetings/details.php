<?php
include_once '../../config/db_mysqli.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Invalid ID");
}

// Fetch Meeting Details
$stmt = $mysqli->prepare("SELECT m.*, d.name as division_name, e.full_name as creator_name 
                          FROM meetings m 
                          LEFT JOIN divisions d ON m.division_id = d.id 
                          LEFT JOIN employees e ON m.created_by = e.id 
                          WHERE m.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();

if (!$meeting) {
    die("Meeting not found");
}

// Fetch Participants
$sqlPart = "SELECT mp.*, e.full_name
            FROM meeting_participants mp 
            JOIN employees e ON mp.employee_id = e.id 
            WHERE mp.meeting_id = ?";
$stmtPart = $mysqli->prepare($sqlPart);
$stmtPart->bind_param("i", $id);
$stmtPart->execute();
$resPart = $stmtPart->get_result();
$participants = [];
while ($row = $resPart->fetch_assoc()) {
    $participants[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-indigo-50 text-slate-800">

    <div class="max-w-4xl mx-auto px-4 py-8">
        <a href="index.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center mb-4">
            &larr; Back to Dashboard
        </a>

        <!-- Top Card: Info & QR -->
        <div class="bg-white rounded-2xl shadow-lg border border-indigo-100 overflow-hidden mb-6">
            <div class="md:flex">
                <!-- Details -->
                <div class="p-8 md:w-2/3">
                    <div class="uppercase tracking-wide text-sm text-indigo-500 font-bold">
                        <?= htmlspecialchars($meeting['division_name']) ?>
                    </div>
                    <h1 class="block mt-1 text-2xl leading-tight font-extrabold text-slate-900">
                        <?= htmlspecialchars($meeting['title']) ?>
                    </h1>
                    <p class="mt-2 text-slate-500 text-sm">
                        <?= htmlspecialchars($meeting['description']) ?>
                    </p>

                    <div class="mt-6 border-t border-slate-100 pt-4 grid grid-cols-2 gap-4">
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase">Date</span>
                            <span class="text-slate-800 font-medium">
                                <?= date('l, d F Y', strtotime($meeting['meeting_date'])) ?>
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase">Time</span>
                            <span class="text-slate-800 font-medium">
                                <?= substr($meeting['start_time'], 0, 5) ?> -
                                <?= substr($meeting['end_time'], 0, 5) ?>
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase">Location</span>
                            <span class="text-slate-800 font-medium">
                                <?= htmlspecialchars($meeting['location']) ?>
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase">Creator</span>
                            <span class="text-slate-800 font-medium">
                                <?= htmlspecialchars($meeting['creator_name']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- QR Code Section -->
                <div
                    class="md:w-1/3 bg-slate-50 flex flex-col items-center justify-center p-8 border-l border-slate-100">
                    <div class="bg-white p-2 rounded-lg shadow-sm border">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= $meeting['qr_token'] ?>"
                            alt="QR Code" class="w-32 h-32 object-contain">
                    </div>
                    <p class="mt-4 text-xs text-slate-400 text-center font-mono select-all">
                        <?= $meeting['qr_token'] ?>
                    </p>
                    <div class="mt-2 px-3 py-1 bg-indigo-100 text-indigo-700 text-xs rounded-full font-bold">
                        Scan for Attendance
                    </div>
                </div>
            </div>
        </div>

        <!-- Participants Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Participants (
                <?= count($participants) ?>)
            </h2>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-semibold border-b">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($participants as $p): ?>
                            <tr id="row-<?= $p['id'] ?>" class="group hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-800">
                                        <?= htmlspecialchars($p['full_name']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span id="badge-<?= $p['id'] ?>"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $p['status'] === 'present' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                    <?php if ($p['attendance_time']): ?>
                                        <div class="text-[10px] text-slate-400 mt-1">
                                            <?= date('H:i', strtotime($p['attendance_time'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button onclick="toggleStatus(<?= $p['id'] ?>, '<?= $p['status'] ?>')"
                                        class="text-indigo-600 hover:text-indigo-900 font-medium text-xs border border-indigo-200 hover:bg-indigo-50 px-3 py-1.5 rounded transition">
                                        Toggle Presence
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        async function toggleStatus(id, currentStatus) {
            try {
                const btn = document.activeElement;
                const originalText = btn.innerText;
                btn.innerText = 'Updating...';
                btn.disabled = true;

                const response = await fetch('../../logic/meetings/toggle_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, current_status: currentStatus })
                });

                const data = await response.json();

                if (data.success) {
                    // Reload page to reflect changes simply
                    location.reload();
                } else {
                    alert('Failed: ' + (data.message || 'Unknown error'));
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            } catch (e) {
                console.error(e);
                alert('Error connecting to server');
            }
        }
    </script>

</body>

</html>