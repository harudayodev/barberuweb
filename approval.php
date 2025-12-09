<?php
session_start();
if (!isset($_SESSION['sadminID'])) {
    header("Location: session_expired.html");
    exit();
}
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Language handling
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

$lang = $_SESSION['language'];
$translations = include("languages/{$lang}.php");

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

include 'Connection.php';
include 'send_email.php';

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build the query with search and filter conditions
$query = "SELECT * FROM application WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (business_name LIKE ? OR owner_name LIKE ? OR business_email LIKE ? OR business_address LIKE ? OR contact LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= "sssss";
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch all applications
$applications = [];
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}

// Handle Approve/Decline actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_id'], $_POST['action'])) {
    $app_id = intval($_POST['app_id']);
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Fetch application data
        $result = $conn->query("SELECT * FROM application WHERE shopID = $app_id");
        $app = $result->fetch_assoc();
        
        if ($app) {
            // Define required variables for barbershops insertion
            // NOTE: Replace these with a robust user generation system
            $username = 'shop' . $app['shopID'];
            // A temporary/default password that should be emailed to the owner
            $password = bin2hex(random_bytes(8)); 
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $status = 'active';

            // Update INSERT query to include latitude and longitude
            $stmt2 = $conn->prepare("INSERT INTO barbershops (name, owner, email, address, contact, status, business_permit_image, valid_id_image, username, password, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Update bind_param to include latitude and longitude values
            $stmt2->bind_param(
                "ssssssssssss", // 12 strings: name, owner, email, address, contact, status, permit, id, username, password, latitude, longitude
                $app['business_name'], 
                $app['owner_name'], 
                $app['business_email'], 
                $app['business_address'], 
                $app['contact'], 
                $status, 
                $app['business_permit_image'], 
                $app['valid_id_image'], 
                $username, 
                $hashed_password,
                $app['latitude'], // Latitude from application table
                $app['longitude'] // Longitude from application table
            );
            
            if ($stmt2->execute()) {
                 // Delete from application ONLY if insertion was successful
                 $conn->query("DELETE FROM application WHERE shopID = $app_id");
                 // Send approval email here
            } else {
                 // Log or display error if insertion failed
                 error_log("Barbershop insert failed: " . $stmt2->error);
            }
            $stmt2->close();
        }
    } elseif ($action === 'decline') {
        $status = $translations['declined'];
        $stmt = $conn->prepare("UPDATE application SET status = ? WHERE shopID = ?");
        $stmt->bind_param("si", $status, $app_id);
        $stmt->execute();
        $stmt->close();
        
        // Fetch application data for email
        $result = $conn->query("SELECT * FROM application WHERE shopID = $app_id");
        $app = $result->fetch_assoc();
        
        if ($app) {
            // Send rejection email (assuming send_rejection_email function is defined in send_email.php)
            send_rejection_email($app['business_email'], $app['business_name']);
        }
    }
    // Refresh the page to show updated status and keep filters
    header("Location: approval.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['application_approval'] ?> | Admin Dashboard</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body {
        background-color: #e6f7ff;
        font-family: 'Poppins', sans-serif;
    }
    .main-header {
        background: linear-gradient(90deg, #00aaff 60%, #cceeff 100%);
        padding: 10px 0 8px 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        position:fixed; top:0; left:0; width:100vw; z-index:1000;
    }
    .sidebar {
        background:#fff;
        border-radius:16px;
        box-shadow:0 2px 12px rgba(0,0,0,0.07);
        padding:32px 24px;
        min-width:220px;
        max-width:260px;
        flex:0 0 220px;
        margin-top:0;
    }
    .sidebar-header {
        display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; width:100%;
    }
    .sidebar-header img {
        height:60px; display:block; margin:0 auto;
    }
    .nav-links {
        list-style:none; padding:0; margin:32px 0 0 0; display:flex; flex-direction:column; gap:18px;
    }
    .nav-links li a {
        color:#008bcc; font-weight:500; text-decoration:none; transition: background 0.2s, color 0.2s;
        padding:6px 18px; border-radius:8px;
    }
    .nav-links li a.active, .nav-links li a:hover {
        background:#e6f7ff; color:#0077b6;
        border-radius: 12px;
        box-shadow: 0 1px 4px rgba(0,170,255,0.07);
        transition: background 0.2s, color 0.2s, border-radius 0.2s;
    }
    .nav-links .logout a { color:#dc3545; }
    .main-content {
        flex:1; background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); padding:32px 32px 40px 32px; min-width:0;
        margin-top:0;
    }
    .queue-container {
        width:100%;
    }
    .queue-list {
        background:#f7fbff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.04); padding:24px 18px; margin-top:18px;
    }
    table {
        width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,0.04);
    }
    th, td {
        padding:10px 8px; text-align:center; border-bottom:1px solid #e0e0e0;
    }
    th {
        background:#e6f7ff; color:#0077b6; font-weight:600;
    }
    tr:last-child td { border-bottom:none; }
    /* Styles for action buttons */
    .actions-container {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: center;
    }
    .btn {
        color: white !important;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        text-decoration: none;
        font-size: 14px;
        display: inline-block;
        text-align: center;
        transition: background-color 0.2s;
    }
    .btn-accept {
        background-color: #28a745; /* Green */
    }
    .btn-accept:hover {
        background-color: #218838;
    }
    .btn-decline {
        background-color: #dc3545; /* Red */
    }
    .btn-decline:hover {
        background-color: #c82333;
    }
    .barbershop-cards-container {
        display: flex;
        flex-direction: column;
        gap: 18px;
        margin-top: 12px;
    }
    .barbershop-card {
        background: linear-gradient(90deg, #f7fbff 80%, #e6f7ff 100%);
        border-radius: 18px;
        box-shadow: 0 2px 12px rgba(0,170,255,0.07);
        padding: 22px 28px 18px 28px;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        transition: box-shadow 0.2s, transform 0.2s;
        border: 1px solid #e0e0e0;
        position: relative;
    }
    .barbershop-card:hover {
        box-shadow: 0 4px 24px rgba(0,170,255,0.13);
        transform: translateY(-2px) scale(1.01);
    }
    .card-left {
        display: flex;
        align-items: center;
        gap: 18px;
    }
    .card-icon {
        background: #00aaff;
        color: #fff;
        border-radius: 50%;
        width: 54px;
        height: 54px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.1em;
        box-shadow: 0 2px 8px rgba(0,170,255,0.09);
    }
    .card-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .card-title {
        font-size: 1.25em;
        font-weight: 700;
        color: #0077b6;
        margin-bottom: 2px;
        letter-spacing: 0.5px;
    }
    .card-sub {
        font-size: 1em;
        color: #333;
        font-weight: 500;
    }
    .card-status {
        font-size: 0.98em;
        font-weight: 600;
        color: #fff;
        background: #00aaff;
        border-radius: 8px;
        padding: 2px 12px;
        margin-top: 6px;
        display: inline-block;
        letter-spacing: 0.5px;
        box-shadow: 0 1px 4px rgba(0,170,255,0.07);
    }
    .card-status.declined {
        background: #dc3545;
    }
    .card-status.pending {
        background: #ffc107;
        color: #333;
    }
    .card-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: flex-end;
        justify-content: center;
    }
    .view-details-btn {
        background: #00aaff;
        color: #fff;
        border: none;
        padding: 7px 18px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1em;
        font-weight: 600;
        box-shadow: 0 1px 4px rgba(0,170,255,0.07);
        transition: background 0.2s;
        width: 120px;
        margin-bottom: 0;
    }
    .view-details-btn:hover {
        background: #0077b6;
    }
    </style>
</head>
<body>
    <header class="main-header">
        <div style="max-width:1800px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;width:100%;">
            <div style="display:flex;align-items:center;gap:8px;">
                <img src="Resources/ab.png" alt="Logo" style="height:50px;width:50px;">
                <span style="font-size:2.3rem;font-weight:700;color:#fff;letter-spacing:1px;">BARBERU</span>
            </div>
        </div>
    </header>
    <div style="display:flex;max-width:1800px;width:98vw;margin:100px auto 0 auto;gap:32px;min-height:calc(100vh - 80px);">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="Resources/cd.png" alt="">
                <div style="font-weight:600;margin-top:10px;color:#00aaff;font-size:1.1em;">Super Admin</div>
            </div>
            <ul class="nav-links">
                <li><a href="sadmin.php" class="<?= $current_page === 'sadmin.php' ? 'active' : '' ?>">
                    <i class="fa fa-tachometer-alt" style="margin-right:10px;"></i>Dashboard
                </a></li>
                <li><a href="approval.php" class="<?= $current_page === 'approval.php' ? 'active' : '' ?>">
                    <i class="fa fa-list-check" style="margin-right:10px;"></i>Applications
                </a></li>
                <li class="logout" style="margin-top:32px;"><a href="logout.php">
                    <i class="fa fa-sign-out-alt" style="margin-right:10px;"></i>Logout
                </a></li>
            </ul>
        </aside>
        <main class="main-content" style="display:flex;flex-direction:column;justify-content:flex-start;min-height:calc(100vh - 120px);">
            <section class="queue-container" style="flex:1;display:flex;flex-direction:column;justify-content:flex-start;">
                <div class="queue-list" style="margin-top:0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="margin: 0; display: flex; align-items: center;">
                            <?= $translations['application_list'] ?>
                        </h2>
                        <form method="get" style="display: flex; gap: 10px; align-items: center; margin: 0;">
                            <input type="text" name="search" placeholder="<?= $translations['search_placeholder'] ?>" value="<?= htmlspecialchars($search) ?>" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                            <select name="status" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                                <option value="">All Status</option>
                                <option value="<?= $translations['pending'] ?>" <?= $status_filter === $translations['pending'] ? 'selected' : '' ?>><?= $translations['pending'] ?></option>
                                <option value="<?= $translations['declined'] ?>" <?= $status_filter === $translations['declined'] ? 'selected' : '' ?>><?= $translations['declined'] ?></option>
                            </select>
                            <button type="submit" class="search-button" style="background: #28a745; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 16px;">Search</button>
                            <button type="button" class="clear-button" onclick="window.location.href='approval.php'" style="background: #dc3545; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 16px;">Clear</button>
                        </form>
                    </div>
                    <div class="barbershop-cards-container">
                        <?php if (empty($applications)): ?>
                            <div style="padding:24px;text-align:center;">No results found.</div>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                                <?php
                                    $is_declined = (strtolower($app['status'] ?? '') === 'declined' || $app['status'] === ($translations['declined'] ?? 'declined'));
                                    if ($is_declined && $status_filter !== ($translations['declined'] ?? 'declined')) continue;
                                    $detailsJson = htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8');
                                    $status_class = 'card-status ';
                                    if (strtolower($app['status']) === 'declined' || $app['status'] === ($translations['declined'] ?? 'declined')) {
                                        $status_class .= 'declined';
                                    } elseif (strtolower($app['status']) === 'pending' || $app['status'] === ($translations['pending'] ?? 'pending')) {
                                        $status_class .= 'pending';
                                    }
                                ?>
                                <div class="barbershop-card">
                                    <div class="card-left">
                                        <div class="card-icon">
                                            <i class="fa fa-store"></i>
                                        </div>
                                        <div class="card-info">
                                            <div class="card-title"><?= htmlspecialchars($app['business_name']) ?></div>
                                            <div class="card-sub"><?= htmlspecialchars($app['owner_name']) ?></div>
                                            <div class="<?= $status_class ?>">
                                                <?= htmlspecialchars($app['status']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-actions">
                                        <button type="button" class="view-details-btn" data-details="<?= $detailsJson ?>">More Info</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- Modal for Application Details -->
                    <div id="applicationDetailsModal" class="modal" style="display:none;align-items:center;justify-content:center;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:1000;background:rgba(0,0,0,0.4);">
                        <div class="modal-content" style="background-color:#fff;padding:28px 36px 24px 36px;border:none;max-width:480px;width:95vw;border-radius:16px;position:relative;box-shadow:0 4px 24px rgba(0,0,0,0.15);margin:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                            <span class="close" id="closeApplicationDetailsModal" style="color:#aaa;position:absolute;right:16px;top:8px;font-size:28px;font-weight:bold;cursor:pointer;">&times;</span>
                            <h2 style="text-align:center;margin-bottom:18px;">Application Details</h2>
                            <div id="applicationDetailsContent" style="width:100%;">
                                <!-- Populated by JS -->
                            </div>
                            <div id="applicationActionsContent" style="margin-top:18px;text-align:center;width:100%;">
                                <!-- Populated by JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            // Modal logic
            var modal = document.getElementById('applicationDetailsModal');
            var closeBtn = document.getElementById('closeApplicationDetailsModal');
            var detailsContent = document.getElementById('applicationDetailsContent');
            var actionsContent = document.getElementById('applicationActionsContent');
            // Open modal
            document.querySelectorAll('.view-details-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var app = JSON.parse(btn.getAttribute('data-details'));
                    detailsContent.innerHTML = `
                        <div style=\"margin-bottom:10px;\"><strong>Business Name:</strong> ${app.business_name}</div>
                        <div style=\"margin-bottom:10px;\"><strong>Owner:</strong> ${app.owner_name}</div>
                        <div style=\"margin-bottom:10px;\"><strong>Email:</strong> ${app.business_email}</div>
                        <div style=\"margin-bottom:10px;\"><strong>Address:</strong> ${app.business_address}</div>
                        <div style=\"margin-bottom:10px;\"><strong>Contact:</strong> ${app.contact}</div>
                        <div style=\"margin-bottom:10px;\"><strong>Status:</strong> ${app.status}</div>
                        <div style=\"margin-bottom:10px;\"><strong>Business Permit:</strong> ${app.business_permit_image ? `<a href='${app.business_permit_image}' target='_blank'>View</a>` : 'N/A'}</div>
                        <div style=\"margin-bottom:10px;\"><strong>Valid ID:</strong> ${app.valid_id_image ? `<a href='${app.valid_id_image}' target='_blank'>View</a>` : 'N/A'}</div>
                        <div style=\"margin-bottom:10px;\"><strong>DTI Certificate:</strong> ${app.dti_clearance ? `<a href='${app.dti_clearance}' target='_blank'>View</a>` : 'N/A'}</div>
                        <div style=\"margin-bottom:10px;\"><strong>BIR Registration:</strong> ${app.police_clearance ? `<a href='${app.police_clearance}' target='_blank'>View</a>` : 'N/A'}</div>
                    `;
                    // Accept/Decline buttons
                    var status_lower = (app.status || '').toLowerCase();
                    var is_pending = status_lower === 'pending' || app.status === '<?= $translations['pending'] ?>';
                    var is_declined = status_lower === 'declined' || app.status === '<?= $translations['declined'] ?>';
                    actionsContent.innerHTML = '';
                    if (is_pending || is_declined) {
                        actionsContent.innerHTML += `<a href=\"check.php?app_id=${app.shopID}\" class=\"btn btn-accept\" style=\"margin-right:8px;\">Accept</a>`;
                    }
                    if (is_pending) {
                        actionsContent.innerHTML += `
                            <form method=\"POST\" style=\"display:inline-block; margin: 0;\">
                                <input type=\"hidden\" name=\"app_id\" value=\"${app.shopID}\">
                                <input type=\"hidden\" name=\"action\" value=\"decline\">
                                <button type=\"submit\" class=\"btn btn-decline\">Decline</button>
                            </form>
                        `;
                    }
                    modal.style.display = 'flex';
                });
            });
            closeBtn.onclick = function() {
                modal.style.display = 'none';
            };
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            };
        });
    </script>
</body>
</html>