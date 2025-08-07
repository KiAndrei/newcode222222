<?php
session_start();
if (!isset($_SESSION['client_name']) || $_SESSION['user_type'] !== 'client') {
    header('Location: login_form.php');
    exit();
}
require_once 'config.php';
$client_id = $_SESSION['user_id'];
$res = $conn->query("SELECT profile_image FROM user_form WHERE id=$client_id");
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
    $profile_image = 'assets/images/client-avatar.png';
}
// Fetch all scheduled events for this client
$events = [];
$res = $conn->query("SELECT cs.*, ac.title as case_title, uf.name as attorney_name FROM case_schedules cs LEFT JOIN attorney_cases ac ON cs.case_id = ac.id LEFT JOIN user_form uf ON ac.attorney_id = uf.id WHERE cs.client_id=$client_id ORDER BY cs.date, cs.time");
while ($row = $res->fetch_assoc()) $events[] = $row;

// Fetch client cases with next_hearing dates
$client_cases = [];
$res = $conn->query("SELECT cc.*, uf.name as attorney_name FROM client_cases cc LEFT JOIN user_form uf ON cc.attorney_id = uf.id WHERE cc.client_id=$client_id AND cc.next_hearing IS NOT NULL AND cc.next_hearing != '' ORDER BY cc.next_hearing");
while ($row = $res->fetch_assoc()) $client_cases[] = $row;

// Fetch attorney cases assigned to this client with next_hearing dates
$attorney_cases = [];
$res = $conn->query("SELECT ac.*, uf.name as attorney_name FROM attorney_cases ac LEFT JOIN user_form uf ON ac.attorney_id = uf.id WHERE ac.client_id=$client_id AND ac.next_hearing IS NOT NULL AND ac.next_hearing != '' ORDER BY ac.next_hearing");
while ($row = $res->fetch_assoc()) $attorney_cases[] = $row;

// Fetch recent cases for notification (last 10)
$case_notifications = [];
$notif_stmt = $conn->prepare("SELECT id, title, created_at FROM attorney_cases WHERE client_id=? ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param('i', $client_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
while ($row = $notif_result->fetch_assoc()) {
    $case_notifications[] = $row;
}

// Fetch recent messages for notification (last 10)
$message_notifications = [];
$msg_stmt = $conn->prepare("SELECT id, message, sent_at FROM client_messages WHERE client_id=? ORDER BY sent_at DESC LIMIT 10");
$msg_stmt->bind_param('i', $client_id);
$msg_stmt->execute();
$msg_result = $msg_stmt->get_result();
while ($row = $msg_result->fetch_assoc()) {
    $message_notifications[] = $row;
}

// Combine notifications and sort by date
$all_notifications = [];
foreach ($case_notifications as $case) {
    $all_notifications[] = [
        'type' => 'case',
        'id' => $case['id'],
        'title' => $case['title'],
        'date' => $case['created_at'],
        'text' => 'New case: ' . $case['title']
    ];
}
foreach ($message_notifications as $msg) {
    $all_notifications[] = [
        'type' => 'message',
        'id' => $msg['id'],
        'title' => 'New Message',
        'date' => $msg['sent_at'],
        'text' => 'New message received'
    ];
}

// Sort by date (newest first)
usort($all_notifications, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Limit to 10 total notifications
$all_notifications = array_slice($all_notifications, 0, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <style>
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 18px;
            min-height: 56px;
        }
        .notification-btn {
            background: none;
            border: none;
            font-size: 1.4rem;
            color: #a94442;
            cursor: pointer;
            position: relative;
            padding: 0 6px;
        }
        .notif-badge {
            position: absolute;
            top: -6px;
            right: -2px;
            background: #a94442;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 50%;
            padding: 2px 6px;
            min-width: 18px;
            text-align: center;
        }
        .notification-list-dropdown {
            position: absolute;
            right: 0;
            top: 36px;
            background: #fff;
            min-width: 260px;
            max-width: 90vw;
            box-shadow: 0 4px 16px rgba(44,0,0,0.13);
            border-radius: 10px;
            z-index: 2000;
            padding: 0;
            display: none;
        }
        .notification-list-dropdown ul {
            list-style: none;
            margin: 0;
            padding: 0 0 6px 0;
            max-height: 260px;
            overflow-y: auto;
        }
        .notification-list-dropdown li {
            padding: 10px 18px 6px 18px;
            font-size: 0.97rem;
            border-bottom: 1px solid #f0eaea;
            color: #800000;
        }
        .notification-list-dropdown li:last-child { border-bottom: none; }
        .notif-dropdown-header {
            font-weight: 700;
            color: #a94442;
            padding: 10px 18px 6px 18px;
            border-bottom: 1px solid #f0eaea;
            background: #faf9f6;
            border-radius: 10px 10px 0 0;
        }
        .notif-empty { color: #888; text-align: center; padding: 18px 0; }
        .notif-date { color: #b8860b; font-size: 0.85em; margin-right: 6px; }
        .notif-title { color: #800000; }
        
        /* Enhanced notification styling */
        .notification-item {
            cursor: pointer;
            transition: background-color 0.2s ease;
            padding: 12px 18px;
            border-bottom: 1px solid #f0eaea;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notif-content {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .notif-icon {
            color: #800000;
            font-size: 1.1em;
            margin-top: 2px;
        }
        .notif-text {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .notif-text .notif-title {
            font-weight: 500;
            color: #800000;
            font-size: 0.95em;
        }
        .notif-text .notif-date {
            color: #666;
            font-size: 0.8em;
        }
        @media (max-width: 900px) { .header { flex-direction: column; align-items: flex-start; gap: 10px; } }
        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        #calendar {
            height: 600px; /* Increased calendar height for better visibility */
        }

        .calendar-views {
            display: flex;
            gap: 10px;
        }

        .calendar-views .btn {
            padding: 8px 15px;
        }

        .calendar-views .btn.active {
            background: var(--primary-color);
            color: white;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-upcoming {
            background: #17a2b8;
            color: white;
        }

        .event-info {
            display: grid;
            gap: 20px;
        }

        .info-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        .info-group h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .label {
            color: #666;
            font-weight: 500;
        }

        .value {
            color: #333;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .calendar-views {
                flex-wrap: wrap;
            }

            .search-box {
                width: 100%;
            }

            .info-item {
                flex-direction: column;
                gap: 5px;
            }
        }
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }
        .profile-dropdown-btn {
            border: none;
            background: none;
            padding: 0;
            cursor: pointer;
        }
        .profile-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: #fff;
            min-width: 160px;
            box-shadow: 0 4px 16px rgba(44,0,0,0.13);
            z-index: 100;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 8px;
        }
        .profile-dropdown.show .profile-dropdown-content {
            display: block;
        }
        .profile-dropdown-content a {
            color: #800000;
            padding: 12px 18px;
            text-decoration: none;
            display: block;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
        }
        .profile-dropdown-content a:hover {
            background: #faf9f6;
            color: #a94442;
        }
        .fc .fc-toolbar-title { 
            font-size: 1.3em; 
            color: #800000; 
            font-weight: 600; 
        }
        
        /* Calendar navigation buttons - maroon theme */
        .fc .fc-button-primary {
            background-color: #800000 !important;
            border-color: #800000 !important;
            color: white !important;
        }
        .fc .fc-button-primary:hover {
            background-color: #a94442 !important;
            border-color: #a94442 !important;
        }
        .fc .fc-button-primary:focus {
            background-color: #800000 !important;
            border-color: #800000 !important;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25) !important;
        }
        .fc .fc-button-primary:not(:disabled):active {
            background-color: #6b0000 !important;
            border-color: #6b0000 !important;
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active {
            background-color: #a94442 !important;
            border-color: #a94442 !important;
        }
        
        .fc-event {
            border-radius: 8px;
            border: none;
            font-size: 0.9em;
            padding: 3px 8px;
            box-shadow: 0 2px 6px rgba(128,0,0,0.1);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .fc-event:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(128,0,0,0.15);
        }
        .fc-event.hearing {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: #fff;
            font-weight: 600;
        }
        .fc-event.appointment {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
            font-weight: 500;
        }
        .fc-event.case {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: #fff;
            font-weight: 500;
        }
        .fc-event .fc-icon {
            margin-right: 4px;
            font-size: 1em;
        }
        .fc-event.hearing {
            background: linear-gradient(90deg, #ffeaea 0%, #ffd6d6 100%);
            color: #b71c1c;
            font-weight: 600;
        }
        .fc-event .fc-icon {
            margin-right: 4px;
            font-size: 1.1em;
        }
        .calendar-legend {
            display: flex;
            gap: 18px;
            margin: 12px 0 0 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9em;
            color: #666;
        }
        .legend-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
        }
        .legend-dot.hearing { background: #dc3545; }
        .legend-dot.appointment { background: #28a745; }
        .legend-dot.case { background: #007bff; }
        
        /* Professional Modal Styling */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: #fff;
            border-radius: 16px;
            max-width: 600px;
            margin: 40px auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease-out;
        }
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .modal-header {
            background: linear-gradient(135deg, #800000 0%, #a94442 100%);
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
        }
        .close-modal:hover {
            background: rgba(255,255,255,0.2);
        }
        .modal-body {
            padding: 24px;
        }
        .modal-footer {
            padding: 16px 24px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .event-details {
            display: grid;
            gap: 20px;
        }
        .detail-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #800000;
        }
        .detail-section h3 {
            margin: 0 0 16px 0;
            color: #800000;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
        }
        .detail-value {
            color: #212529;
            text-align: right;
            flex: 1;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #800000;
            color: white;
        }
        .btn-primary:hover {
            background: #a94442;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
        <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="client_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="client_documents.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="client_cases.php"><i class="fas fa-gavel"></i><span>My Cases</span></a></li>
            <li><a href="client_schedule.php" class="active"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="client_messages.php"><i class="fas fa-envelope"></i><span>Messages</span></a></li>
        </ul>
        <div class="sidebar-logout">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header" style="padding: 10px 20px; min-height: 56px;">
            <div class="header-title">
                <h1 style="font-size: 1.3rem; margin-bottom: 2px;">My Schedule</h1>
                <p style="font-size: 0.95rem; margin-bottom: 0;">View your upcoming appointments and court hearings</p>
            </div>
            <div style="display: flex; align-items: center; gap: 18px;">
                <div class="notification-dropdown" style="position: relative;">
                    <button class="notification-btn" onclick="toggleNotifDropdown(event)">
                        <i class="fas fa-bell"></i>
                        <?php if (count($all_notifications) > 0): ?>
                        <span class="notif-badge"><?= count($all_notifications) ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-list-dropdown" id="notifDropdown" style="display:none;">
                        <div class="notif-dropdown-header">Notifications</div>
                        <ul>
                        <?php if (count($all_notifications) === 0): ?>
                            <li class="notif-empty">No notifications yet.</li>
                        <?php else: ?>
                            <?php foreach ($all_notifications as $notif): ?>
                                <li class="notification-item" 
                                    data-type="<?= htmlspecialchars($notif['type']) ?>" 
                                    data-id="<?= htmlspecialchars($notif['id']) ?>"
                                    onclick="handleNotificationClick('<?= htmlspecialchars($notif['type']) ?>', <?= htmlspecialchars($notif['id']) ?>)">
                                    <div class="notif-content">
                                        <div class="notif-icon">
                                            <?php if ($notif['type'] === 'case'): ?>
                                                <i class="fas fa-gavel"></i>
                                            <?php else: ?>
                                                <i class="fas fa-envelope"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notif-text">
                                            <span class="notif-title"><?= htmlspecialchars($notif['text']) ?></span>
                                            <span class="notif-date"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($notif['date']))) ?></span>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="user-info">
                    <div class="profile-dropdown" id="profileDropdown">
                        <button class="profile-dropdown-btn" onclick="toggleProfileDropdown(event)" style="background:none;border:none;padding:0;">
                            <img src="<?= htmlspecialchars($profile_image) ?>" alt="Client" style="object-fit:cover;width:44px;height:44px;border-radius:50%;border:2px solid #1976d2;box-shadow:0 2px 8px rgba(44,0,0,0.10);transition:box-shadow 0.2s;">
                        </button>
                        <div class="profile-dropdown-content" id="profileDropdownContent" style="right:0;min-width:160px;box-shadow:0 4px 16px rgba(44,0,0,0.13);border-radius:10px;overflow:hidden;background:#fff;">
                            <a href="#" onclick="document.getElementById('profileUpload').click(); return false;" style="padding:12px 18px;display:block;color:#800000;font-weight:500;text-decoration:none;transition:background 0.2s;">Update Profile</a>
                            <a href="logout.php" style="padding:12px 18px;display:block;color:#800000;font-weight:500;text-decoration:none;transition:background 0.2s;">Logout</a>
                        </div>
                        <form action="upload_profile_image.php" method="POST" enctype="multipart/form-data" style="display:none;" id="profileUploadForm">
                            <input type="file" id="profileUpload" name="profile_image" onchange="document.getElementById('profileUploadForm').submit()">
                        </form>
                    </div>
                    <div class="user-details">
                        <h3 style="font-size: 1.05rem; margin-bottom: 2px;"><?php echo $_SESSION['client_name']; ?></h3>
                        <p style="font-size: 0.85rem; color: #888; margin-bottom: 0;">Client</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <div class="calendar-views">
                <button class="btn btn-secondary active" data-view="month">
                    <i class="fas fa-calendar"></i> Month
                </button>
                <button class="btn btn-secondary" data-view="week">
                    <i class="fas fa-calendar-week"></i> Week
                </button>
            </div>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search appointments...">
            </div>
        </div>

        <!-- Calendar Container -->
        <div class="calendar-container">
            <div id="calendar"></div>
            <div class="calendar-legend">
                <div class="legend-item"><span class="legend-dot hearing"></span> Court Hearing</div>
                <div class="legend-item"><span class="legend-dot appointment"></span> Appointment</div>
                <div class="legend-item"><span class="legend-dot case"></span> Case Update</div>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">Upcoming Appointments</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Case</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $i => $ev): ?>
                    <tr>
                        <td><?= htmlspecialchars($ev['date']) ?></td>
                        <td><?= htmlspecialchars(date('h:i A', strtotime($ev['time']))) ?></td>
                        <td><?= htmlspecialchars($ev['type']) ?></td>
                        <td><?= htmlspecialchars($ev['location']) ?></td>
                        <td><?= htmlspecialchars($ev['case_title'] ?? '-') ?></td>
                        <td>
                            <span class="status-badge status-upcoming"><?= htmlspecialchars($ev['status']) ?></span>
                            <button class="btn btn-info btn-xs view-info-btn" 
                                data-type="<?= htmlspecialchars($ev['type']) ?>"
                                data-date="<?= htmlspecialchars($ev['date']) ?>"
                                data-time="<?= htmlspecialchars($ev['time']) ?>"
                                data-location="<?= htmlspecialchars($ev['location']) ?>"
                                data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"
                                data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"
                                data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"
                                style="margin-left:8px; font-size:0.95em; padding:3px 10px; border-radius:6px; background:#1976d2; color:#fff; border:none; cursor:pointer;">View Info</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Attorney Cases with Next Hearing -->
        <?php if (!empty($attorney_cases)): ?>
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">Attorney Cases with Next Hearing</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Case Title</th>
                        <th>Attorney</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attorney_cases as $ac): ?>
                    <tr>
                        <td><?= htmlspecialchars($ac['next_hearing']) ?></td>
                        <td><?= htmlspecialchars($ac['title']) ?></td>
                        <td><?= htmlspecialchars($ac['attorney_name'] ?? '-') ?></td>
                        <td>
                            <span class="status-badge status-upcoming"><?= htmlspecialchars($ac['status'] ?? 'Active') ?></span>
                        </td>
                        <td>
                            <button class="btn btn-primary btn-xs view-info-btn" 
                                data-type="Hearing"
                                data-date="<?= htmlspecialchars($ac['next_hearing']) ?>"
                                data-time=""
                                data-location="Court Hearing"
                                data-case="<?= htmlspecialchars($ac['title']) ?>"
                                data-attorney="<?= htmlspecialchars($ac['attorney_name'] ?? '-') ?>"
                                data-description="<?= htmlspecialchars($ac['description'] ?? '-') ?>"
                                data-title="Attorney Case: <?= htmlspecialchars($ac['title']) ?>"
                                style="font-size:0.9em; padding:6px 12px; border-radius:6px; background:#800000; color:#fff; border:none; cursor:pointer;">View Details</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Professional Event Details Modal -->
        <div class="modal" id="eventModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-calendar-check"></i> Event Details</h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="event-details">
                        <div class="detail-section">
                            <h3><i class="fas fa-info-circle"></i> Event Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Event Type:</span>
                                <span class="detail-value" id="modalType"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Title:</span>
                                <span class="detail-value" id="modalTitle"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date:</span>
                                <span class="detail-value" id="modalDate"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Time:</span>
                                <span class="detail-value" id="modalTime"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Location:</span>
                                <span class="detail-value" id="modalLocation"></span>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h3><i class="fas fa-gavel"></i> Case Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Case Title:</span>
                                <span class="detail-value" id="modalCase"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Attorney:</span>
                                <span class="detail-value" id="modalAttorney"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Description:</span>
                                <span class="detail-value" id="modalDescription"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeEventModal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleNotifDropdown(e) {
            e.stopPropagation();
            var dropdown = document.getElementById('notifDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            document.addEventListener('click', function handler(event) {
                if (!dropdown.contains(event.target) && event.target !== e.target) {
                    dropdown.style.display = 'none';
                    document.removeEventListener('click', handler);
                }
            });
        }
        
        function handleNotificationClick(type, id) {
            // Close the dropdown
            document.getElementById('notifDropdown').style.display = 'none';
            
            // Navigate based on notification type
            if (type === 'case') {
                // Navigate to client cases page
                window.location.href = 'client_cases.php';
            } else if (type === 'message') {
                // Navigate to messages page
                window.location.href = 'client_messages.php';
            }
        }
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            var dropdown = document.getElementById('profileDropdownContent');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            document.addEventListener('click', function handler(event) {
                if (!dropdown.contains(event.target) && event.target !== e.target) {
                    dropdown.style.display = 'none';
                    document.removeEventListener('click', handler);
                }
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            // Prepare events data for calendar
            var calendarEvents = [];
            
            // Add scheduled events
            <?php foreach ($events as $ev): ?>
            calendarEvents.push({
                title: '<?= addslashes($ev['type']) ?>: <?= addslashes($ev['title']) ?>',
                start: '<?= $ev['date'] . 'T' . $ev['time'] ?>',
                description: '<?= addslashes($ev['description'] ?? '') ?>',
                location: '<?= addslashes($ev['location'] ?? '') ?>',
                case: '<?= addslashes($ev['case_title'] ?? '') ?>',
                attorney: '<?= addslashes($ev['attorney_name'] ?? '') ?>',
                type: '<?= addslashes($ev['type']) ?>',
                eventType: 'scheduled',
                className: '<?= strtolower($ev['type']) ?>'
            });
            <?php endforeach; ?>
            
            // Add client cases with next_hearing dates
            <?php foreach ($client_cases as $cc): ?>
            calendarEvents.push({
                title: 'Case: <?= addslashes($cc['title']) ?>',
                start: '<?= $cc['next_hearing'] ?>',
                description: '<?= addslashes($cc['description'] ?? '') ?>',
                location: 'Court Hearing',
                case: '<?= addslashes($cc['title']) ?>',
                attorney: '<?= addslashes($cc['attorney_name'] ?? '') ?>',
                type: 'Hearing',
                eventType: 'case',
                className: 'case'
            });
            <?php endforeach; ?>
            
            // Add attorney cases assigned to this client with next_hearing dates
            <?php foreach ($attorney_cases as $ac): ?>
            calendarEvents.push({
                title: 'Attorney Case: <?= addslashes($ac['title']) ?>',
                start: '<?= $ac['next_hearing'] ?>',
                description: '<?= addslashes($ac['description'] ?? '') ?>',
                location: 'Court Hearing',
                case: '<?= addslashes($ac['title']) ?>',
                attorney: '<?= addslashes($ac['attorney_name'] ?? '') ?>',
                type: 'Hearing',
                eventType: 'attorney_case',
                className: 'case'
            });
            <?php endforeach; ?>

            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                eventClassNames: function(arg) {
                    return [arg.event.extendedProps.className || 'appointment'];
                },
                eventContent: function(arg) {
                    let icon = '';
                    if (arg.event.extendedProps.type === 'Hearing') {
                        icon = '<i class="fas fa-gavel fc-icon"></i>';
                    } else if (arg.event.extendedProps.eventType === 'case' || arg.event.extendedProps.eventType === 'attorney_case') {
                        icon = '<i class="fas fa-balance-scale fc-icon"></i>';
                    } else {
                        icon = '<i class="fas fa-calendar-check fc-icon"></i>';
                    }
                    return { html: icon + '<span>' + arg.event.title + '</span>' };
                },
                events: calendarEvents,
                eventClick: function(info) {
                    // Fill modal with event details
                    document.getElementById('modalType').innerText = info.event.extendedProps.type || '';
                    document.getElementById('modalTitle').innerText = info.event.title || '';
                    document.getElementById('modalDate').innerText = info.event.start ? info.event.start.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) : '';
                    document.getElementById('modalTime').innerText = info.event.start ? info.event.start.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : '';
                    document.getElementById('modalLocation').innerText = info.event.extendedProps.location || '';
                    document.getElementById('modalCase').innerText = info.event.extendedProps.case || '';
                    document.getElementById('modalAttorney').innerText = info.event.extendedProps.attorney || '';
                    document.getElementById('modalDescription').innerText = info.event.extendedProps.description || '';
                    document.getElementById('eventModal').style.display = "block";
                }
            });
            calendar.render();

            // Calendar view switching
            document.querySelectorAll('.calendar-views .btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.calendar-views .btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    const view = this.dataset.view;
                    calendar.changeView(view === 'month' ? 'dayGridMonth' : 'timeGridWeek');
                });
            });

            // Modal functionality
            const modal = document.getElementById('eventModal');
            const closeModal = document.querySelector('.close-modal');
            const closeEventModal = document.getElementById('closeEventModal');

            closeModal.onclick = function() {
                modal.style.display = "none";
            }

            closeEventModal.onclick = function() {
                modal.style.display = "none";
            }

            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }

            // Populate event table with PHP events
            document.querySelectorAll('.view-info-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    document.getElementById('modalType').innerText = this.dataset.type || '';
                    document.getElementById('modalDate').innerText = this.dataset.date || '';
                    document.getElementById('modalTime').innerText = this.dataset.time ? new Date('1970-01-01T' + this.dataset.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                    document.getElementById('modalLocation').innerText = this.dataset.location || '';
                    document.getElementById('modalCase').innerText = this.dataset.case || '';
                    document.getElementById('modalAttorney').innerText = this.dataset.attorney || '';
                    document.getElementById('modalDescription').innerText = this.dataset.description || '';
                    document.getElementById('eventModal').style.display = "block";
                });
            });
        });
    </script>
</body>
</html> 