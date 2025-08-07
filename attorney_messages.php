<?php
session_start();
if (!isset($_SESSION['attorney_name']) || $_SESSION['user_type'] !== 'attorney') {
    header('Location: login_form.php');
    exit();
}
require_once 'config.php';
$attorney_id = $_SESSION['user_id'];
// Fetch all clients with profile images
$clients = [];
$res = $conn->query("SELECT id, name, profile_image FROM user_form WHERE user_type='client'");
while ($row = $res->fetch_assoc()) {
    $img = $row['profile_image'];
    if (!$img || !file_exists($img)) $img = 'assets/images/client-avatar.png';
    $row['profile_image'] = $img;
    $clients[] = $row;
}
// Handle AJAX fetch messages
if (isset($_POST['action']) && $_POST['action'] === 'fetch_messages') {
    $client_id = intval($_POST['client_id']);
    $msgs = [];
    // Fetch attorney profile image
    $attorney_img = '';
    $res = $conn->query("SELECT profile_image FROM user_form WHERE id=$attorney_id");
    if ($res && $row = $res->fetch_assoc()) $attorney_img = $row['profile_image'];
    if (!$attorney_img || !file_exists($attorney_img)) $attorney_img = 'assets/images/attorney-avatar.png';
    // Fetch client profile image
    $client_img = '';
    $res = $conn->query("SELECT profile_image FROM user_form WHERE id=$client_id");
    if ($res && $row = $res->fetch_assoc()) $client_img = $row['profile_image'];
    if (!$client_img || !file_exists($client_img)) $client_img = 'assets/images/client-avatar.png';
    // Fetch attorney to client
    $stmt1 = $conn->prepare("SELECT message, sent_at, 'attorney' as sender FROM attorney_messages WHERE attorney_id=? AND recipient_id=?");
    $stmt1->bind_param('ii', $attorney_id, $client_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    while ($row = $result1->fetch_assoc()) {
        $row['profile_image'] = $attorney_img;
        $msgs[] = $row;
    }
    // Fetch client to attorney
    $stmt2 = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM client_messages WHERE client_id=? AND recipient_id=?");
    $stmt2->bind_param('ii', $client_id, $attorney_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $row['profile_image'] = $client_img;
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
    $client_id = intval($_POST['client_id']);
    $msg = $_POST['message'];
    $stmt = $conn->prepare("INSERT INTO attorney_messages (attorney_id, recipient_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $attorney_id, $client_id, $msg);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
// Handle AJAX create case from chat
if (isset($_POST['action']) && $_POST['action'] === 'create_case_from_chat') {
    $client_id = intval($_POST['client_id']);
    $title = $_POST['case_title'];
    $description = $_POST['summary'];
    $case_type = isset($_POST['case_type']) ? $_POST['case_type'] : null;
    $status = isset($_POST['status']) && $_POST['status'] ? $_POST['status'] : 'Active';
    $next_hearing = empty($_POST['next_hearing']) ? null : $_POST['next_hearing'];
    $stmt = $conn->prepare("INSERT INTO attorney_cases (title, description, attorney_id, client_id, case_type, status, next_hearing) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiisss', $title, $description, $attorney_id, $client_id, $case_type, $status, $next_hearing);
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
            <li><a href="attorney_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="attorney_cases.php" class="active"><i class="fas fa-gavel"></i><span>Manage Cases</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="attorney_messages.php"><i class="fas fa-envelope"></i><span>Messages</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
            <li><a href="attorney/attorney_audit.php"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="chat-container">
            <!-- Client List -->
            <div class="client-list">
                <h3>Clients</h3>
                <ul id="clientList">
                    <?php foreach ($clients as $c): ?>
                    <li class="client-item" data-id="<?= $c['id'] ?>" onclick="selectClient(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name']) ?>')">
                        <img src='<?= htmlspecialchars($c['profile_image']) ?>' alt='Client' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'><span><?= htmlspecialchars($c['name']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <!-- Chat Area -->
            <div class="chat-area">
                <div class="chat-header">
                    <h2 id="selectedClient">Select a client</h2>
                    <button class="btn btn-primary" id="createCaseBtn" style="display:none;" onclick="openCreateCaseModal()"><i class="fas fa-gavel"></i> Create Case</button>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <p style="color:#888;text-align:center;">Select a client to start conversation.</p>
                </div>
                <div class="chat-compose" id="chatCompose" style="display:none;">
                    <textarea id="messageInput" placeholder="Type your message..."></textarea>
                    <button class="btn btn-primary" onclick="sendMessage()">Send</button>
                </div>
            </div>
        </div>
        <!-- Create Case Modal -->
        <div class="modal" id="createCaseModal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Create Case from Conversation</h2>
                    <button class="close-modal" onclick="closeCreateCaseModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="createCaseForm">
                        <div class="form-group">
                            <label>Client Name</label>
                            <input type="text" name="client_name" id="caseClientName" readonly>
                        </div>
                        <div class="form-group">
                            <label>Case Title</label>
                            <input type="text" name="case_title" required>
                        </div>
                        <div class="form-group">
                            <label>Case Type</label>
                            <select name="case_type" required>
                                <option value="">Select Type</option>
                                <option value="criminal">Criminal</option>
                                <option value="civil">Civil</option>
                                <option value="family">Family</option>
                                <option value="corporate">Corporate</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Summary</label>
                            <textarea name="summary" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Next Hearing</label>
                            <input type="date" name="next_hearing">
                            <small style="color:#888;">(Optional. Leave blank if not yet scheduled.)</small>
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
        }
        
        .client-list { 
            width: 280px; 
            background: linear-gradient(180deg, #800000 0%, #a94442 100%);
            border-right: 2px solid rgba(255, 255, 255, 0.2);
            padding: 20px 0;
            position: relative;
        }
        
        .client-list::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.03)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.03)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.03)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .client-list h3 { 
            text-align: center; 
            margin-bottom: 25px; 
            color: #fff0f0;
            font-size: 1.4rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .client-list ul { 
            list-style: none; 
            padding: 0; 
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .client-item { 
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
        
        .client-item.active, .client-item:hover { 
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .client-item img { 
            width: 45px; 
            height: 45px; 
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .client-item span {
            color: #fff0f0;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .chat-area { 
            flex: 1; 
            display: flex; 
            flex-direction: column;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 249, 250, 0.9) 100%);
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><defs><linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:%23f8f9fa;stop-opacity:0.8"/><stop offset="100%" style="stop-color:%23e9ecef;stop-opacity:0.6"/></linearGradient><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="%23800000" stroke-width="0.5" opacity="0.1"/></pattern><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="%23800000" opacity="0.1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23bg)"/><rect width="100%" height="100%" fill="url(%23grid)"/><rect width="100%" height="100%" fill="url(%23dots)"/></svg>');
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .chat-area::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.7) 0%, rgba(248, 249, 250, 0.7) 100%);
            z-index: 0;
        }
        
        .chat-header { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 22px 30px; 
            border-bottom: 2px solid rgba(128, 0, 0, 0.15);
            background: linear-gradient(135deg, #800000 0%, #a94442 50%, #800000 100%);
            position: relative;
            z-index: 1;
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.2);
        }
        
        .chat-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .chat-header h2 { 
            margin: 0; 
            font-size: 1.4rem; 
            color: #fff0f0;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
        }
        
        .chat-header button { 
            margin-left: 15px;
            padding: 12px 24px;
            border-radius: 25px;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #fff;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            position: relative;
            z-index: 1;
        }
        
        .chat-header button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
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
            padding: 18px 22px; 
            border-radius: 22px; 
            font-size: 1rem; 
            position: relative;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .message-bubble:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .message-bubble.sent { 
            background: linear-gradient(135deg, #800000 0%, #a94442 100%);
            color: #fff0f0;
            margin-left: auto;
            border-bottom-right-radius: 8px;
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.2);
        }
        
        .message-bubble.sent::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            border-radius: 22px;
            border-bottom-right-radius: 8px;
            pointer-events: none;
        }
        
        .message-bubble.received { 
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(128, 0, 0, 0.1);
            color: #2c3e50;
            border-bottom-left-radius: 8px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        }
        
        .message-bubble.received::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(128, 0, 0, 0.02) 0%, transparent 50%);
            border-radius: 22px;
            border-bottom-left-radius: 8px;
            pointer-events: none;
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
            padding: 22px 30px; 
            border-top: 2px solid rgba(128, 0, 0, 0.15);
            background: linear-gradient(135deg, #800000 0%, #a94442 50%, #800000 100%);
            position: relative;
            z-index: 1;
            box-shadow: 0 -4px 15px rgba(128, 0, 0, 0.2);
        }
        
        .chat-compose::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .chat-compose textarea { 
            flex: 1; 
            border-radius: 25px; 
            border: 2px solid rgba(255, 255, 255, 0.4);
            padding: 15px 20px; 
            resize: none; 
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        .chat-compose textarea:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 25px rgba(255, 255, 255, 0.4);
            transform: scale(1.02);
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            position: relative;
            z-index: 1;
        }
        
        .chat-compose button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 90%;
            position: relative;
            animation: modalSlideIn 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        
        .modal-header h2 {
            color: #800000;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 2rem;
            color: #800000;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close-modal:hover {
            color: #a94442;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(128, 0, 0, 0.2);
            border-radius: 12px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 15px rgba(128, 0, 0, 0.2);
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-group small {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #a94442 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: #800000;
            border: 2px solid rgba(128, 0, 0, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        #caseSuccessMsg {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
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
            .client-list { 
                width: 100%; 
                border-right: none; 
                border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
            } 
            .modal-content {
                padding: 30px 20px;
                margin: 20px;
            }
        }
    </style>
    <script>
        let selectedClientId = null;
        function selectClient(id, name) {
            selectedClientId = id;
            document.getElementById('selectedClient').innerText = name;
            document.getElementById('createCaseBtn').style.display = 'inline-block';
            document.getElementById('chatCompose').style.display = 'flex';
            fetchMessages();
        }
        function fetchMessages() {
            if (!selectedClientId) return;
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('client_id', selectedClientId);
            fetch('attorney_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('chatMessages');
                    chat.innerHTML = '';
                    msgs.forEach(m => {
                        const sent = m.sender === 'attorney';
                        chat.innerHTML += `
                <div class='message-bubble ${sent ? 'sent' : 'received'}' style='display:flex;align-items:flex-end;gap:10px;'>
                    ${sent ? '' : `<img src='${m.profile_image}' alt='Client' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>`}
                    <div style='flex:1;'>
                        <div class='message-text'><p>${m.message}</p></div>
                        <div class='message-meta'><span class='message-time'>${m.sent_at}</span></div>
                    </div>
                    ${sent ? `<img src='${m.profile_image}' alt='Attorney' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>` : ''}
                </div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                });
        }
        function sendMessage() {
            const input = document.getElementById('messageInput');
            if (!input.value.trim() || !selectedClientId) return;
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('client_id', selectedClientId);
            fd.append('message', input.value);
            fetch('attorney_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        input.value = '';
                        fetchMessages();
                    } else {
                        alert('Error sending message.');
                    }
                });
        }
        document.getElementById('createCaseForm').onsubmit = function(e) {
            e.preventDefault();
            if (!selectedClientId) return;
            const fd = new FormData(this);
            fd.append('action', 'create_case_from_chat');
            fd.append('client_id', selectedClientId);
            fetch('attorney_messages.php', { method: 'POST', body: fd })
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
        function openCreateCaseModal() {
            if (selectedClientId !== null) {
                const clientName = document.querySelector('.client-item[data-id="'+selectedClientId+'"] span').innerText;
                document.getElementById('caseClientName').value = clientName;
                document.getElementById('createCaseModal').style.display = 'block';
            }
        }
        function closeCreateCaseModal() {
            document.getElementById('createCaseModal').style.display = 'none';
        }
    </script>
</body>
</html> 