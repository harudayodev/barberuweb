<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['adminID']) && !isset($_SESSION['barberID']) && !isset($_SESSION['sadminID'])) {
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

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_status = isset($_GET['search_status']) ? strtolower($_GET['search_status']) : '';

// Build the query with search and filter conditions
// The query selects all columns (*), including latitude and longitude, but they will not be displayed.
$query = "SELECT * FROM barbershops WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR owner LIKE ? OR email LIKE ? OR address LIKE ? OR contact LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= "sssss";
}

if (!empty($search_status)) {
    $query .= " AND LOWER(status) = ?";
    $params[] = $search_status;
    $types .= "s";
} else {
    // Default: hide archived records
    $query .= " AND LOWER(status) != 'archived'";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Remove duplicate initialization
$barbershops = [];
while ($row = $result->fetch_assoc()) {
    $barbershops[] = $row;
}

// Handle discontinue/reactivate or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_id'], $_POST['toggle_action'])) {
        $toggle_id = intval($_POST['toggle_id']);
        $toggle_action = $_POST['toggle_action'];
        $new_status = ($toggle_action === 'discontinued') ? 'discontinued' : 'active';
        $stmt = $conn->prepare("UPDATE barbershops SET status = ? WHERE shopID = ?");
        if ($stmt === false) {
            die("Error preparing status update statement: " . $conn->error);
        }
        $stmt->bind_param("si", $new_status, $toggle_id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['archive_id'])) {
        $archive_id = intval($_POST['archive_id']);
        // Archive the barbershop (set status to 'archived')
        $stmt = $conn->prepare("UPDATE barbershops SET status = 'archived' WHERE shopID = ?");
        if ($stmt === false) {
            die("Error preparing archive statement: " . $conn->error);
        }
        $stmt->bind_param("i", $archive_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: barbershop_list.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit();
}
// (Removed duplicate/unused status update handler)
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['barbershop_list'] ?> | Admin Dashboard</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body {
        background-color: #e6f7ff;
        font-family: 'Poppins', sans-serif;
    }
    /* Service card design from employees.php */
    .service-card-list {
        display: flex;
        flex-direction: column;
        gap: 12px; /* Increased gap for more space between cards */
        margin-top: 18px;
        width: 100%;
    }
    /* Add gap between cards */
    /* Remove margin-bottom from .service-card, spacing handled by gap above */
    /* Align search/filter row with title */
    .barbershop-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 18px;
        margin-bottom: 10px;
    }
    .barbershop-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .search-group {
        display: flex;
        align-items: center;
        gap: 0;
    }
    .service-card {
        background: linear-gradient(90deg, #e6f7ff 60%, #cceeff 100%);
        border-radius: 18px;
        box-shadow: 0 4px 16px rgba(0,170,255,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
        padding: 0;
        min-width: 0;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        border: none;
        transition: box-shadow 0.2s, transform 0.2s;
        margin-bottom: 2px;
        overflow: hidden;
    }
    .service-card:hover {
        box-shadow: 0 8px 24px rgba(0,170,255,0.18), 0 2px 12px rgba(0,0,0,0.08);
        transform: translateY(-2px) scale(1.01);
    }
    .service-card-title {
        font-size: 1.18rem;
        font-weight: 600;
        color: #000000ff;
        margin-left: 32px;
        margin-right: 0;
        flex: 1;
        text-align: left;
        padding: 18px 0;
        letter-spacing: 0.5px;
        text-shadow: 0 1px 2px #cceeff;
    }
    .service-card-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-right: 32px;
    }
    .service-action-btn {
        background: #00aaff;
        border: none;
        border-radius: 25%;
        width: 25px;
        height: 25px;
        padding: 0;
        cursor: pointer;
        font-size: 1.08rem;
        color: #fff;
        transition: background 0.2s, box-shadow 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0,170,255,0.10);
    }
    .service-action-btn.info { background: #00aaff; }
    .service-action-btn.edit { background: #ffc107; color: #fff; }
    .service-action-btn.archive { background: #6c757d; color: #fff; }
    .service-action-btn:hover { filter: brightness(0.95); box-shadow: 0 4px 12px rgba(0,170,255,0.18); }
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
    /* Modal styles for popup */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100vw;
        height: 100vh;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
    .modal-content {
        background-color: #fff;
        padding: 28px 36px 24px 36px;
        border: none;
        width: 480px;
        max-width: 98vw;
        border-radius: 16px;
        position: relative;
        box-shadow: 0 4px 24px rgba(0,0,0,0.15);
        margin: 0;
    }
    .close {
        color: #aaa;
        position: absolute;
        right: 16px;
        top: 8px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .close:hover,
    .close:focus {
        color: #000;
        text-decoration: none;
        cursor: pointer;
    }
    .modal-content h2 {
        margin-top: 0;
        margin-bottom: 18px;
        font-size: 1.4rem;
        font-weight: 700;
        text-align: center;
    }
    /* Ratings section redesign - improved alignment */
    .ratings-summary {
        margin-bottom: 18px;
        text-align: center;
        background: #f8fcff;
        border-radius: 10px;
        padding: 12px 0 10px 0;
        box-shadow: 0 1px 4px rgba(0,170,255,0.07);
    }
    .ratings-summary .star-icon {
        font-size: 2.1em;
        color: #ffc107;
        vertical-align: middle;
        margin-right: 6px;
    }
    .ratings-summary .avg-rating {
        font-size: 1.5em;
        font-weight: 700;
        color: #333;
        vertical-align: middle;
    }
    .ratings-summary .review-count {
        font-size: 0.98em;
        color: #888;
        margin-top: 2px;
    }
    .ratings-row {
        display: flex;
        align-items: center;
        margin-bottom: 7px;
        min-height: 18px;
        width: 100%;
    }
    .ratings-row .star-num-group {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        width: 54px;
        min-width: 54px;
        font-size: 1.08em;
        color: #555;
        gap: 3px;
        padding-right: 4px;
    }
    .ratings-row .star-num-group .star {
        color: #ffc107;
        font-size: 1em;
        margin-left: 2px;
    }
    .ratings-row .bar-percent-group {
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 0;
        gap: 8px;
    }
    .ratings-row .bar-container {
        background: #e6f7ff;
        border-radius: 6px;
        overflow: hidden;
        height: 14px;
        position: relative;
        flex: 1;
        min-width: 40px;
        max-width: 100%;
    }
    .ratings-row .bar-fill {
        background: #00aaff;
        height: 14px;
        border-radius: 6px;
        transition: width 0.2s;
        display: block; /* Ensure bar-fill respects width */
    }
    .ratings-row .percent-label {
        text-align: left;
        font-size: 0.98em;
        color: #00aaff;
        width: 44px;
        min-width: 44px;
        padding-left: 2px;
        white-space: nowrap;
    }
    /* End ratings section redesign */
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
                <li><a href="barbershop_list.php" class="<?= $current_page === 'barbershop_list.php' ? 'active' : '' ?>">
                    <i class="fa fa-store" style="margin-right:10px;"></i>Barbershops
                </a></li>
                <li class="logout" style="margin-top:32px;"><a href="logout.php">
                    <i class="fa fa-sign-out-alt" style="margin-right:10px;"></i>Logout
                </a></li>
            </ul>
        </aside>
        <main class="main-content" style="display:flex;flex-direction:column;justify-content:flex-start;min-height:calc(100vh - 120px);">
            <section class="queue-container" style="flex:1;display:flex;flex-direction:column;justify-content:flex-start;">
                <div class="queue-list" style="margin-top:0;">
                    <div class="barbershop-header-row">
                        <h2 style="margin: 0; display: flex; align-items: center;">
                            <?= $translations['all_barbershops'] ?>
                        </h2>
                        <div class="barbershop-header-actions">
                            <form method="get" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                                <span class="search-group">
                                    <input type="text" name="search" placeholder="<?= $translations['search_placeholder'] ?>" value="<?= htmlspecialchars($search) ?>" style="padding: 8px; border-radius: 4px 0 0 4px; border: 1px solid #ccc; height:30px;">
                                    <button type="submit" class="search-button" style="background: #28a745; color: #fff; border: none; border-radius: 0 4px 4px 0; cursor: pointer; font-size: 18px;display:flex;align-items:center;justify-content:center; width: 30px; height:30px;" title="Search">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </button>
                                </span>
                                <select name="search_status" style="padding: 2px; border-radius: 4px; border: 1px solid #ccc; height:30px; min-width:120px;" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="active" <?= $search_status === 'active' ? 'selected' : '' ?>><?= $translations['active'] ?></option>
                                    <option value="discontinued" <?= $search_status === 'discontinued' ? 'selected' : '' ?>><?= $translations['discontinued'] ?></option>
                                    <option value="archived" <?= $search_status === 'archived' ? 'selected' : '' ?>>Archived</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="barbershop-cards-container service-card-list">
                    <?php if (empty($barbershops)): ?>
                        <div style="padding:24px;text-align:center;"><?= $translations['no_results'] ?></div>
                    <?php else: ?>
                        <?php foreach ($barbershops as $shop): ?>
    <?php
    $detailsJson = htmlspecialchars(json_encode($shop), ENT_QUOTES, 'UTF-8');
    // Fetch review stats for this shop
    $shopID = (int)$shop['shopID'];
    $reviewStats = [1=>0,2=>0,3=>0,4=>0,5=>0];
    $totalReviews = 0;
    $totalStars = 0;
    $reviewRes = $conn->query("SELECT stars FROM review WHERE shopID = $shopID AND stars IS NOT NULL");
    if ($reviewRes) {
        while ($r = $reviewRes->fetch_assoc()) {
            $star = (int)$r['stars'];
            if ($star >= 1 && $star <= 5) {
                $reviewStats[$star]++;
                $totalReviews++;
                $totalStars += $star;
            }
        }
    }
    $avgRating = $totalReviews > 0 ? round($totalStars / $totalReviews, 2) : 0;
    $reviewStatsJson = htmlspecialchars(json_encode([
        'stats' => $reviewStats,
        'total' => $totalReviews,
        'average' => $avgRating
    ]), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="service-card">
        <div class="service-card-title"><?= htmlspecialchars($shop['name']) ?></div>
        <div class="service-card-actions">
            <button type="button" class="service-action-btn info view-details-btn" data-details="<?= $detailsJson ?>" data-reviews="<?= $reviewStatsJson ?>" title="More Info"><i class="fa-solid fa-circle-info"></i></button>
        </div>
    </div>
<?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                    <!-- Modal for Barbershop Details -->
                    <div id="barbershopDetailsModal" class="modal">
    <div class="modal-content" style="max-width:700px;width:95vw;display:flex;gap:24px;">
        <span class="close" id="closeBarbershopDetailsModal">&times;</span>
        <!-- 2 columns: left for details, right for ratings -->
        <div id="barbershopDetailsLeft" style="flex:1;min-width:220px;">
            <h2 style="text-align:center;margin-bottom:18px;">Barbershop Details</h2>
            <div id="barbershopDetailsContent"></div>
            <form method="POST" id="archiveForm" style="margin-top:18px;">
                <input type="hidden" name="archive_id" id="archiveShopId" value="">
                <button type="submit" style="background:#dc3545;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:1em;font-weight:600;width:100%;margin-bottom:10px;">Archive</button>
            </form>
            <form method="POST" id="statusForm" style="margin-bottom:10px;">
                <input type="hidden" name="toggle_id" id="statusShopId" value="">
                <label for="statusSelect" style="font-weight:500;margin-bottom:6px;display:block;">Status:</label>
                <select name="toggle_action" id="statusSelect" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc;">
                    <option value="active">Active</option>
                    <option value="discontinued">Discontinued</option>
                </select>
                <button type="submit" style="background:#00aaff;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:1em;font-weight:600;width:100%;margin-top:8px;">Update Status</button>
            </form>
        </div>
        <div id="barbershopDetailsRight" style="flex:1;min-width:180px;border-left:1px solid #eee;padding-left:18px;">
            <h2 style="text-align:center;margin-bottom:18px;font-size:1.15em;">Ratings</h2>
            <div id="barbershopRatingsContent"></div>
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
            var modal = document.getElementById('barbershopDetailsModal');
            var closeBtn = document.getElementById('closeBarbershopDetailsModal');
            var detailsContent = document.getElementById('barbershopDetailsContent');
            var ratingsContent = document.getElementById('barbershopRatingsContent');
            var archiveForm = document.getElementById('archiveForm');
            var archiveShopId = document.getElementById('archiveShopId');
            var statusForm = document.getElementById('statusForm');
            var statusShopId = document.getElementById('statusShopId');
            var statusSelect = document.getElementById('statusSelect');
            // Open modal
            document.querySelectorAll('.view-details-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var shop = JSON.parse(btn.getAttribute('data-details'));
                    var reviews = JSON.parse(btn.getAttribute('data-reviews'));
                    detailsContent.innerHTML = `
                        <div style="margin-bottom:10px;"><strong>Name:</strong> ${shop.name}</div>
                        <div style="margin-bottom:10px;"><strong>Owner:</strong> ${shop.owner}</div>
                        <div style="margin-bottom:10px;"><strong>Email:</strong> ${shop.email}</div>
                        <div style="margin-bottom:10px;"><strong>Address:</strong> ${shop.address}</div>
                        <div style="margin-bottom:10px;"><strong>Contact:</strong> ${shop.contact}</div>
                        <div style="margin-bottom:10px;"><strong>Status:</strong> ${shop.status}</div>
                        <div style="margin-bottom:10px;"><strong>Permit:</strong> ${shop.business_permit_image ? `<a href='${shop.business_permit_image}' target='_blank'>View</a>` : 'N/A'}</div>
                        <div style="margin-bottom:10px;"><strong>Valid ID:</strong> ${shop.valid_id_image ? `<a href='${shop.valid_id_image}' target='_blank'>View</a>` : 'N/A'}</div>
                        <div style="margin-bottom:10px;"><strong>DTI Certificate:</strong> ${shop.dti_clearance ? `<a href='${shop.dti_clearance}' target='_blank'>View</a>` : 'N/A'}</div>
                        <div style="margin-bottom:10px;"><strong>BIR Registration:</strong> ${shop.police_clearance ? `<a href='${shop.police_clearance}' target='_blank'>View</a>` : 'N/A'}</div>
                    `;
                    // Redesigned Ratings column (perfect alignment)
                    let ratingsHtml = '';
                    let total = reviews.total || 0;
                    ratingsHtml += `<div class="ratings-summary">
                        <span class="star-icon"><i class="fa-solid fa-star"></i></span>
                        <span class="avg-rating">${reviews.average > 0 ? reviews.average : 'N/A'}</span>
                        <div class="review-count">${total} review${total==1?'':'s'}</div>
                    </div>`;
                    // Set max count for scaling bars to 500
                    let maxCount = 500;
                    for (let i = 5; i >= 1; i--) {
                        let count = reviews.stats[i] || 0;
                        let width = maxCount > 0 ? Math.round((count / maxCount) * 100) : 0;
                        ratingsHtml += `
                            <div class="ratings-row">
                                <span class="star-num-group">
                                    <span>${i}</span>
                                    <span class="star"><i class="fa-solid fa-star"></i></span>
                                </span>
                                <span class="bar-percent-group">
                                    <span class="bar-container">
                                        <div class="bar-fill" style="width:${width}%;"></div>
                                    </span>
                                    <span class="percent-label">${count}</span>
                                </span>
                            </div>
                        `;
                    }
                    ratingsContent.innerHTML = ratingsHtml;
                    archiveShopId.value = shop.shopID;
                    statusShopId.value = shop.shopID;
                    statusSelect.value = shop.status;
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