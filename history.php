<?php
session_start();
if (!isset($_SESSION['adminID']) && !isset($_SESSION['barberID']) && !isset($_SESSION['sadminID'])) {
    header("Location: session_expired.html");
    exit();
}

$shopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : null;
$AdminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : null;

// Language handling
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

$lang = $_SESSION['language'];
$translations = include("languages/{$lang}.php");

require_once "Connection.php";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$current_page = basename($_SERVER['PHP_SELF']);

// Fetch barbershop name for sidebar
$barbershopName = '';
if (isset($_SESSION['barbershopID'])) {
    $barbershopID = (int)$_SESSION['barbershopID'];
    $result = $conn->query("SELECT name FROM barbershops WHERE shopID = $barbershopID");
    if ($result && $row = $result->fetch_assoc()) {
        $barbershopName = $row['name'];
    }
}

// Fetch count of new queue notifications
$newQueueCount = 0;
$adminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : 0;
$barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
$result = $conn->query("SELECT COUNT(*) AS cnt FROM queue WHERE status = 'In Queue' AND adminID = $adminID AND shopID = $barbershopID");
if ($result && $row = $result->fetch_assoc()) {
    $newQueueCount = (int)$row['cnt'];
}

// Fetch usage history
$where = [];
if ($shopID && $AdminID) {
    $where[] = "h.shopID = $shopID AND i.AdminID = $AdminID";
}
if (!empty($_GET['search_term'])) {
    $searchTerm = $conn->real_escape_string($_GET['search_term']);
    $where[] = "i.PName = '$searchTerm'";
}

if (!empty($_GET['search_date'])) {
    $searchDate = $conn->real_escape_string($_GET['search_date']);
    $where[] = "DATE(h.used_at) = '$searchDate'";
}

$sql = "SELECT h.id, h.used_at, i.PName, i.CriticalLevel, h.quantity AS Quantity, i.ProductID
    FROM inventory_history h
    JOIN inventory i ON h.product_id = i.ProductID";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY h.used_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['product_usage_history'] ?? 'Product Usage History' ?> | Admin Dashboard</title>
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
                <?php if (!empty($barbershopName)) { echo '<div style="font-weight:600;margin-top:10px;color:#00aaff;font-size:1.1em;">' . htmlspecialchars($barbershopName) . '</div>'; } ?>
            </div>
            <ul class="nav-links">
                <li style="position:relative;display:flex;align-items:center;">
                    <a href="queueing.php" class="<?= $current_page === 'queueing.php' ? 'active' : '' ?>" style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-list"></i> Queue
                        <?php if ($newQueueCount > 0): ?>
                            <span style="min-width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;background:#dc3545;color:#fff;border-radius:50%;font-size:1em;font-weight:600;box-shadow:0 2px 8px rgba(220,53,69,0.12);margin-left:8px;"> <?= $newQueueCount ?> </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="management.php" class="<?= $current_page === 'management.php' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> Management</a></li>
                <li><a href="reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li><a href="options.php" class="<?= $current_page === 'options.php' ? 'active' : '' ?>"><i class="fa-solid fa-sliders"></i> Options</a></li>
                <li class="logout" style="margin-top:32px;"><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
            </ul>
        </aside>
        <main class="main-content" style="display:flex;flex-direction:column;justify-content:flex-start;min-height:calc(100vh - 120px);">
            <section class="queue-container" style="flex:1;display:flex;flex-direction:column;justify-content:flex-start;">
                <div class="queue-list" style="margin-top:0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="margin: 0; display: flex; align-items: center;">
                            <?= $translations['product_usage_history'] ?? 'Product Usage History' ?>
                        </h2>
<form method="get" style="display: flex; gap: 10px; align-items: center; margin: 0;">
    <?php
    // ✅ Fetch product names that appear in history only
    $productQuery = "
        SELECT DISTINCT i.PName 
        FROM inventory_history h
        JOIN inventory i ON h.product_id = i.ProductID
        WHERE i.AdminID = $AdminID AND i.shopID = $shopID
        ORDER BY i.PName ASC
    ";
    $productResult = $conn->query($productQuery);
    ?>
    
    <select name="search_term" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; min-width: 200px;">
        <option value=""><?= $translations['select_product'] ?? 'Select Product' ?></option>
        <?php
        if ($productResult && $productResult->num_rows > 0) {
            while ($pRow = $productResult->fetch_assoc()) {
                $selected = (isset($_GET['search_term']) && $_GET['search_term'] === $pRow['PName']) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($pRow['PName']) . "' $selected>" . htmlspecialchars($pRow['PName']) . "</option>";
            }
        }
        ?>
    </select>

    <input type="date" name="search_date" 
           value="<?= isset($_GET['search_date']) ? htmlspecialchars($_GET['search_date']) : '' ?>" 
           style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">

    <button type="submit" class="search-button" 
            style="background: #28a745; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 16px;">
        Search
    </button>

    <button type="button" class="clear-button" 
            onclick="window.location.href='history.php'" 
            style="background: #dc3545; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 16px;">
        Clear
    </button>
</form>


                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= $translations['product_name'] ?? 'Product Name' ?></th>
                                <th>Critical Level</th>
                                <th><?= $translations['quantity'] ?? 'Quantity' ?></th>
                                <th><?= $translations['date_used'] ?? 'Date Used' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $idx = 1;
                            if ($result && $result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>{$idx}</td>";
                                    echo "<td>" . htmlspecialchars($row['PName']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['CriticalLevel']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['Quantity']) . "</td>";
                                    echo "<td>" . (isset($row['used_at']) ? date('M d, Y h:i A', strtotime($row['used_at'])) : '-') . "</td>";
                                    echo "</tr>";
                                    $idx++;
                                }
                            } else {
                                echo '<tr><td colspan="5" style="text-align:center;">' . ($translations['no_results'] ?? 'No result found') . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>