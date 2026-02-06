<?php
include_once '../../config/db_mysqli.php';

$id = $_GET['id'] ?? null;
if (!$id)
    die("ID Required");

// Fetch existing data
$stmt = $mysqli->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();

if (!$meeting)
    die("Meeting not found");

// Fetch divisions
$resDiv = $mysqli->query("SELECT * FROM divisions ORDER BY name ASC");
$divisions = [];
while ($row = $resDiv->fetch_assoc())
    $divisions[] = $row;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Meeting</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800">

    <div class="max-w-3xl mx-auto px-4 py-10">
        <div class="bg-white rounded-xl shadow-lg p-8 border border-slate-100">
            <h1 class="text-2xl font-bold text-slate-900 mb-6 border-b pb-4">Edit Meeting</h1>

            <form action="../../logic/meetings/update.php" method="POST" class="space-y-6">
                <input type="hidden" name="id" value="<?= $meeting['id'] ?>">

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-slate-700">Meeting Title</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($meeting['title']) ?>"
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-slate-700">Description</label>
                    <textarea name="description" rows="3"
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?= htmlspecialchars($meeting['description']) ?></textarea>
                </div>

                <!-- Date & Division -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Date</label>
                        <input type="date" name="meeting_date" required value="<?= $meeting['meeting_date'] ?>"
                            class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Division</label>
                        <select name="division_id" required
                            class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?= $div['id'] ?>" <?= $meeting['division_id'] == $div['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($div['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Time -->
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Start Time</label>
                        <input type="time" name="start_time" required value="<?= $meeting['start_time'] ?>"
                            class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">End Time</label>
                        <input type="time" name="end_time" required value="<?= $meeting['end_time'] ?>"
                            class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <!-- Type & Location -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Type</label>
                        <select name="type" required
                            class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="offline" <?= $meeting['type'] == 'offline' ? 'selected' : '' ?>>Offline</option>
                            <option value="online" <?= $meeting['type'] == 'online' ? 'selected' : '' ?>>Online</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Location</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($meeting['location']) ?>"
                            placeholder="e.g. Room 101 or Zoom Link"
                            class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-3 pt-4">
                    <a href="index.php"
                        class="px-4 py-2 bg-white border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
                    <button type="submit"
                        class="px-6 py-2 bg-indigo-600 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>