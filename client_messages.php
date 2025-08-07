<?php
session_start();
if (!isset($_SESSION['client_name']) || $_SESSION['user_type'] !== 'client') {
    header('Location: login_form.php');
    exit();
}
require_once 'config.php';
$client_id = $_SESSION['user_id'];
// Fetch all attorneys with profile images
$attorneys = [];
$res = $conn->query("SELECT id, name, profile_image FROM user_form WHERE user_type='attorney'");
while ($row = $res->fetch_assoc()) {
    $img = $row['profile_image'];
    if (!$img || !file_exists($img)) $img = 'assets/images/attorney-avatar.png';
    $row['profile_image'] = $img;
    $attorneys[] = $row;
}
// Fetch recent cases for notification (last 10)
$case_notifications = [];
$notif_stmt = $conn->prepare("SELECT title, created_at FROM attorney_cases WHERE client_id=? ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param('i', $client_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
while ($row = $notif_result->fetch_assoc()) {
    $case_notifications[] = $row;
}
// Handle AJAX fetch messages
if (isset($_POST['action']) && $_POST['action'] === 'fetch_messages') {
    $attorney_id = intval($_POST['attorney_id']);
    $msgs = [];
    // Fetch client profile image
    $client_img = '';
    $res = $conn->query("SELECT profile_image FROM user_form WHERE id=$client_id");
    if ($res && $row = $res->fetch_assoc()) $client_img = $row['profile_image'];
    if (!$client_img || !file_exists($client_img)) $client_img = 'assets/images/client-avatar.png';
    // Fetch attorney profile image
    $attorney_img = '';
    $res = $conn->query("SELECT profile_image FROM user_form WHERE id=$attorney_id");
    if ($res && $row = $res->fetch_assoc()) $attorney_img = $row['profile_image'];
    if (!$attorney_img || !file_exists($attorney_img)) $attorney_img = 'assets/images/attorney-avatar.png';
    // Fetch client to attorney
    $stmt1 = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM client_messages WHERE client_id=? AND recipient_id=?");
    $stmt1->bind_param('ii', $client_id, $attorney_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    while ($row = $result1->fetch_assoc()) {
        $row['profile_image'] = $client_img;
        $msgs[] = $row;
    }
    // Fetch attorney to client
    $stmt2 = $conn->prepare("SELECT message, sent_at, 'attorney' as sender FROM attorney_messages WHERE attorney_id=? AND recipient_id=?");
    $stmt2->bind_param('ii', $attorney_id, $client_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $row['profile_image'] = $attorney_img;
        $msgs[] = $row;
    }
    // Sort by sent_at
    usort($msgs, function($a, $b) { return strtotime($a['sent_at']) - strtotime($b['sent_at']); });
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit();
}
// Handle AJAX send message
if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $attorney_id = intval($_POST['attorney_id']);
    $msg = $_POST['message'];
    $stmt = $conn->prepare("INSERT INTO client_messages (client_id, recipient_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $client_id, $attorney_id, $msg);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
// Handle AJAX create case
if (isset($_POST['action']) && $_POST['action'] === 'create_case_from_chat') {
    $attorney_id = intval($_POST['attorney_id']);
    $title = $_POST['case_title'];
    $description = $_POST['summary'];
    $stmt = $conn->prepare("INSERT INTO client_cases (title, description, client_id) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $title, $description, $client_id);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
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
            <li><a href="client_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="client_messages.php" class="active"><i class="fas fa-envelope"></i><span>Messages</span></a></li>
        </ul>
        <div class="sidebar-logout">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header" style="padding: 10px 20px; min-height: 56px;">
            <div class="header-title">
                <h1 style="font-size: 1.3rem; margin-bottom: 2px;">Messages</h1>
                <p style="font-size: 0.95rem; margin-bottom: 0;">View and send messages to your attorney</p>
            </div>
            <div style="display: flex; align-items: center; gap: 18px;">
                <div class="notification-dropdown" style="position: relative;">
                    <button class="notification-btn" onclick="toggleNotifDropdown(event)">
                        <i class="fas fa-bell"></i>
                        <?php if (count($case_notifications) > 0): ?>
                        <span class="notif-badge"><?= count($case_notifications) ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-list-dropdown" id="notifDropdown" style="display:none;">
                        <div class="notif-dropdown-header">Notifications</div>
                        <ul>
                        <?php if (count($case_notifications) === 0): ?>
                            <li class="notif-empty">No notifications yet.</li>
                        <?php else: ?>
                            <?php foreach ($case_notifications as $notif): ?>
                                <li>
                                    <span class="notif-date"><?= htmlspecialchars($notif['created_at']) ?>:</span>
                                    <span class="notif-title">New case: <b><?= htmlspecialchars($notif['title']) ?></b></span>
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
        <div class="chat-container">
            <!-- Attorney List -->
            <div class="attorney-list">
                <h3>Attorneys</h3>
                <ul id="attorneyList">
                    <?php foreach ($attorneys as $a): ?>
                    <li class="attorney-item" data-id="<?= $a['id'] ?>" onclick="selectAttorney(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>')">
                        <img src='<?= htmlspecialchars($a['profile_image']) ?>' alt='Attorney' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'><span><?= htmlspecialchars($a['name']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <!-- Chat Area -->
            <div class="chat-area">
                <div class="chat-header">
                    <h2 id="selectedAttorney">Select an attorney</h2>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <p style="color:#888;text-align:center;">Select an attorney to start conversation.</p>
                </div>
                <div class="chat-compose" id="chatCompose" style="display:none;">
                    <textarea id="messageInput" placeholder="Type your message..."></textarea>
                    <button class="btn btn-primary" onclick="sendMessage()">Send</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal" id="createCaseModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Case from Conversation</h2>
                <button class="close-modal" onclick="closeCreateCaseModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createCaseForm">
                    <div class="form-group">
                        <label>Attorney Name</label>
                        <input type="text" name="attorney_name" id="caseAttorneyName" readonly>
                    </div>
                    <div class="form-group">
                        <label>Case Title</label>
                        <input type="text" name="case_title" required>
                    </div>
                    <div class="form-group">
                        <label>Summary</label>
                        <textarea name="summary" rows="3"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeCreateCaseModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Case</button>
                    </div>
                </form>
                <div id="caseSuccessMsg" style="display:none; color:green; margin-top:10px;">Case created successfully!</div>
            </div>
        </div>
    </div>
    <style>
        .chat-container { 
            display: flex; 
            height: 80vh; 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid rgba(128, 0, 0, 0.1);
            position: relative;
            z-index: 1;
        }
        
        .attorney-list { 
            width: 280px; 
            background: linear-gradient(180deg, rgba(128, 0, 0, 0.9) 0%, rgba(169, 68, 66, 0.9) 100%);
            border-right: 2px solid rgba(255, 255, 255, 0.2);
            padding: 20px 0;
            position: relative;
            background-image: url('images/atty2.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .attorney-list::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(128, 0, 0, 0.8) 0%, rgba(169, 68, 66, 0.8) 100%);
            opacity: 1;
        }
        
        .attorney-list h3 { 
            text-align: center; 
            margin-bottom: 25px; 
            color: #fff0f0;
            font-size: 1.4rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .attorney-list ul { 
            list-style: none; 
            padding: 0; 
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .attorney-item { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            padding: 15px 25px; 
            cursor: pointer; 
            border-radius: 12px; 
            margin: 0 15px 8px 15px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .attorney-item.active, .attorney-item:hover { 
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .attorney-item img { 
            width: 45px; 
            height: 45px; 
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .attorney-item span {
            color: #fff0f0;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .chat-area { 
            flex: 1; 
            display: flex; 
            flex-direction: column;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            position: relative;
            background-image: url('images/atty3.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .chat-area::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 249, 250, 0.9) 100%);
            opacity: 1;
        }
        
        .chat-header { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 20px 30px; 
            border-bottom: 2px solid rgba(128, 0, 0, 0.1);
            background: linear-gradient(90deg, rgba(128, 0, 0, 0.9) 0%, rgba(169, 68, 66, 0.9) 100%);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
        }
        
        .chat-header h2 { 
            margin: 0; 
            font-size: 1.3rem; 
            color: #fff0f0;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .chat-messages { 
            flex: 1; 
            padding: 25px 30px; 
            overflow-y: auto; 
            position: relative;
            z-index: 1;
        }
        
        .message-bubble { 
            max-width: 75%; 
            margin-bottom: 20px; 
            padding: 15px 20px; 
            border-radius: 20px; 
            font-size: 1rem; 
            position: relative;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .message-bubble.sent { 
            background: linear-gradient(135deg, rgba(128, 0, 0, 0.9) 0%, rgba(169, 68, 66, 0.9) 100%);
            color: #fff0f0;
            margin-left: auto;
            border-bottom-right-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .message-bubble.received { 
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(128, 0, 0, 0.2);
            color: #2c3e50;
            border-bottom-left-radius: 5px;
            backdrop-filter: blur(10px);
        }
        
        .message-meta { 
            font-size: 0.8em; 
            color: rgba(255, 255, 255, 0.8); 
            margin-top: 8px; 
            text-align: right;
        }
        
        .message-bubble.received .message-meta {
            color: rgba(44, 62, 80, 0.6);
        }
        
        .chat-compose { 
            display: flex; 
            gap: 15px; 
            padding: 20px 30px; 
            border-top: 2px solid rgba(128, 0, 0, 0.1);
            background: linear-gradient(90deg, rgba(128, 0, 0, 0.9) 0%, rgba(169, 68, 66, 0.9) 100%);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
        }
        
        .chat-compose textarea { 
            flex: 1; 
            border-radius: 25px; 
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 15px 20px; 
            resize: none; 
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        
        .chat-compose textarea:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
        }
        
        .chat-compose button { 
            padding: 15px 30px; 
            border-radius: 25px; 
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #fff; 
            border: none; 
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .chat-compose button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        /* Custom scrollbar */
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: rgba(128, 0, 0, 0.1);
            border-radius: 10px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #800000 0%, #a94442 100%);
            border-radius: 10px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #a94442 0%, #800000 100%);
        }
        
        @media (max-width: 900px) { 
            .chat-container { 
                flex-direction: column; 
                height: auto; 
            } 
            .attorney-list { 
                width: 100%; 
                border-right: none; 
                border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
            } 
        }
        
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
        
        .notification-list-dropdown li:last-child { 
            border-bottom: none; 
        }
        
        .notif-dropdown-header {
            font-weight: 700;
            color: #a94442;
            padding: 10px 18px 6px 18px;
            border-bottom: 1px solid #f0eaea;
            background: #faf9f6;
            border-radius: 10px 10px 0 0;
        }
        
        .notif-empty { 
            color: #888; 
            text-align: center; 
            padding: 18px 0; 
        }
        
        .notif-date { 
            color: #b8860b; 
            font-size: 0.85em; 
            margin-right: 6px; 
        }
        
        .notif-title { 
            color: #800000; 
        }
        
        @media (max-width: 900px) { 
            .header { 
                flex-direction: column; 
                align-items: flex-start; 
                gap: 15px; 
            }
        }
        
        .profile-dropdown {
            position: relative;
        }
        
        .profile-dropdown-btn {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .profile-dropdown-btn:hover {
            transform: scale(1.05);
        }
        
        .profile-dropdown-btn img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 3px solid #800000;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .profile-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            min-width: 180px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            z-index: 100;
            border-radius: 15px;
            overflow: hidden;
            margin-top: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .profile-dropdown.show .profile-dropdown-content {
            display: block;
        }
        
        .profile-dropdown-content a {
            color: #2c3e50;
            padding: 15px 20px;
            text-decoration: none;
            display: block;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(128, 0, 0, 0.1);
        }
        
        .profile-dropdown-content a:last-child {
            border-bottom: none;
        }
        
        .profile-dropdown-content a:hover {
            background: linear-gradient(135deg, rgba(128, 0, 0, 0.1) 0%, rgba(169, 68, 66, 0.1) 100%);
            color: #800000;
            transform: translateX(5px);
        }
    </style>
    <script>
        let selectedAttorneyId = null;
        function selectAttorney(id, name) {
            selectedAttorneyId = id;
            document.getElementById('selectedAttorney').innerText = name;
            document.getElementById('chatCompose').style.display = 'flex';
            fetchMessages();
        }
        function sendMessage() {
            const input = document.getElementById('messageInput');
            if (!input.value.trim() || !selectedAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('attorney_id', selectedAttorneyId);
            fd.append('message', input.value);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        input.value = '';
                        fetchMessages();
                    } else {
                        alert('Error sending message.');
                    }
                });
        }
        function openCreateCaseModal() {
            if (selectedAttorneyId !== null) {
                const attorneyName = document.querySelector('.attorney-item[data-id="'+selectedAttorneyId+'"] span').innerText;
                document.getElementById('caseAttorneyName').value = attorneyName;
                document.getElementById('createCaseModal').style.display = 'block';
            }
        }
        function closeCreateCaseModal() {
            document.getElementById('createCaseModal').style.display = 'none';
        }
        document.getElementById('createCaseForm').onsubmit = function(e) {
            e.preventDefault();
            if (!selectedAttorneyId) return;
            const fd = new FormData(this);
            fd.append('action', 'create_case_from_chat');
            fd.append('attorney_id', selectedAttorneyId);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        document.getElementById('caseSuccessMsg').style.display = 'block';
                        setTimeout(() => {
                            closeCreateCaseModal();
                            document.getElementById('caseSuccessMsg').style.display = 'none';
                        }, 1000);
                    } else {
                        alert('Error creating case.');
                    }
                });
        };
    </script>
    <script>
        function fetchMessages() {
            if (!selectedAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('attorney_id', selectedAttorneyId);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('chatMessages');
                    chat.innerHTML = '';
                    msgs.forEach(m => {
                        const sent = m.sender === 'client';
                        chat.innerHTML += `
                <div class='message-bubble ${sent ? 'sent' : 'received'}' style='display:flex;align-items:flex-end;gap:10px;'>
                    ${sent ? '' : `<img src='${m.profile_image}' alt='Attorney' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>`}
                    <div style='flex:1;'>
                        <div class='message-text'><p>${m.message}</p></div>
                        <div class='message-meta'><span class='message-time'>${m.sent_at}</span></div>
                    </div>
                    ${sent ? `<img src='${m.profile_image}' alt='Client' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>` : ''}
                </div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                });
        }
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
    </script>
</body>
</html> 