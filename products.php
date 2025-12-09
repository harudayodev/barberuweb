<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Session timeout: 15 minutes
$timeout_duration = 900; // 900 seconds = 15 minutes
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: session_expired.html");
    exit();
}

// Check authentication BEFORE updating LAST_ACTIVITY
if (!isset($_SESSION['adminID']) && !isset($_SESSION['barberID']) && !isset($_SESSION['sadminID'])) {
    header("Location: session_expired.html");
    exit();
}

// Only update LAST_ACTIVITY if authenticated
$_SESSION['LAST_ACTIVITY'] = time();

// Language handling
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

$lang = $_SESSION['language'];
$translations = include("languages/{$lang}.php");

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Database connection
require_once "Connection.php";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$shopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : null;
$AdminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : null;

// Fetch barbershop name for sidebar
$barbershopName = '';
if ($conn->connect_error) {
    $barbershopName = '';
} else {
    if (isset($_SESSION['barbershopID'])) {
        $barbershopID = (int)$_SESSION['barbershopID'];
        $result = $conn->query("SELECT name FROM barbershops WHERE shopID = $barbershopID");
        if ($result && $row = $result->fetch_assoc()) {
            $barbershopName = $row['name'];
        }
    }
}

// Handle add product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prod_name']) && $shopID && $AdminID) {
    $name = $conn->real_escape_string($_POST['prod_name']);
    $critical = isset($_POST['critical_level']) ? (int)$_POST['critical_level'] : 5;
    $qty = (int)$_POST['prod_qty'];
    $price = (float)$_POST['prod_price'];
    $date_added = date('Y-m-d H:i:s'); // Asia/Manila time
    $sql = "INSERT INTO inventory (PName, CriticalLevel, Quantity, Price, Status, AdminID, shopID, date_added) VALUES ('$name', $critical, $qty, $price, 'active', $AdminID, $shopID, '$date_added')";
    if (!$conn->query($sql)) { die('Error: ' . $conn->error); }
    header("Location: products.php");
    exit();
}

// Handle remove product (only for this shop and admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id']) && $shopID && $AdminID) {
    $id = (int)$_POST['remove_id'];
    $conn->query("DELETE FROM inventory WHERE ProductID = $id AND shopID = $shopID AND AdminID = $AdminID");
    header("Location: products.php");
    exit();
}

// Handle use product (decrement quantity, only for this shop and admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_id']) && $shopID && $AdminID) {
    $id = (int)$_POST['use_id'];
    $use_qty = isset($_POST['use_qty']) ? max(1, (int)$_POST['use_qty']) : 1;
    $conn->query("UPDATE inventory SET Quantity = Quantity - $use_qty WHERE ProductID = $id AND Quantity >= $use_qty AND shopID = $shopID AND AdminID = $AdminID");
    // Insert a single row with the used quantity
    $conn->query("INSERT INTO inventory_history (product_id, shopID, quantity) VALUES ($id, $shopID, $use_qty)");
    header("Location: products.php");
    exit();
}

// Handle archive product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id']) && $shopID && $AdminID) {
    $id = (int)$_POST['archive_id'];
    $conn->query("UPDATE inventory SET Status = 'archived' WHERE ProductID = $id AND shopID = $shopID AND AdminID = $AdminID");
    header("Location: products.php");
    exit();
}

// Handle restock (edit product)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_product_id']) && $shopID && $AdminID) {
    $id = (int)$_POST['restock_product_id'];
    $critical = isset($_POST['restock_critical_level']) ? (int)$_POST['restock_critical_level'] : 5;
    $qty = (int)$_POST['restock_prod_qty'];
    $new_price = isset($_POST['restock_new_price']) ? (float)$_POST['restock_new_price'] : 0;
    $current_price = (float)$_POST['restock_prod_price'];
    $updated_price = $current_price + $new_price;

    // Fetch current quantity to ensure only increase is allowed
    $res = $conn->query("SELECT Quantity FROM inventory WHERE ProductID = $id AND shopID = $shopID AND AdminID = $AdminID");
    $current_qty = 0;
    if ($res && $row = $res->fetch_assoc()) {
        $current_qty = (int)$row['Quantity'];
    }
    if ($qty >= $current_qty) {
        $conn->query("UPDATE inventory SET CriticalLevel = $critical, Quantity = $qty, Price = $updated_price WHERE ProductID = $id AND shopID = $shopID AND AdminID = $AdminID");
    }
    // else: ignore or handle error (not shown)
    header("Location: products.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | Admin Dashboard</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body { background-color: #e6f7ff; font-family: 'Poppins', sans-serif; }
    .main-header { background: linear-gradient(90deg, #00aaff 60%, #cceeff 100%); padding: 10px 0 8px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); position:fixed; top:0; left:0; width:100vw; z-index:1000; }
    .sidebar { background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); padding:32px 24px; min-width:220px; max-width:260px; flex:0 0 220px; margin-top:0; }
    .sidebar-header { display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; width:100%; }
    .sidebar-header img { height:60px; display:block; margin:0 auto; }
    .nav-links { list-style:none; padding:0; margin:32px 0 0 0; display:flex; flex-direction:column; gap:18px; }
    .nav-links li a { color:#008bcc; font-weight:500; text-decoration:none; transition: background 0.2s, color 0.2s; padding:6px 18px; border-radius:8px; }
    .nav-links li a.active, .nav-links li a:hover { background:#e6f7ff; color:#0077b6; }
    .nav-links .logout a { color:#dc3545; }
    .main-content { flex:1; background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); padding:32px 32px 40px 32px; min-width:0; margin-top:0; }
    .queue-container { width:100%; }
    .queue-list { background:#f7fbff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.04); padding:24px 18px; margin-top:18px; }
    table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,0.04); }
    th, td { padding:10px 8px; text-align:center; border-bottom:1px solid #e0e0e0; }
    th { background:#e6f7ff; color:#0077b6; font-weight:600; }
    tr:last-child td { border-bottom:none; }
    /* Modal styles elided for brevity: unchanged */
    .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100vw; height:100vh; overflow:auto; background-color:rgba(0,0,0,0.4); align-items:center; justify-content:center; }
    .modal-content { background-color:#fff; padding:28px 36px 24px 36px; border:none; width:420px; border-radius:16px; position:relative; box-shadow:0 4px 24px rgba(0,0,0,0.15); margin:0; }
    .close { color:#aaa; position:absolute; right:16px; top:8px; font-size:28px; font-weight:bold; cursor:pointer; }
    .close:hover,.close:focus { color:#000; text-decoration:none; cursor:pointer; }
    .modal-content h2 { margin-top:0; margin-bottom:18px; font-size:1.4rem; font-weight:700; text-align:center; }
    .modal-content form { display:grid; grid-template-columns:120px 1fr; gap:12px 10px; align-items:center; width:100%; }
    .modal-content form label { text-align:right; font-weight:500; font-size:0.98rem; margin-right:4px; }
    .modal-content form input[type="text"], .modal-content form input[type="number"] { width:100%; padding:7px 8px; border:1px solid #ccc; border-radius:4px; font-size:1rem; margin-bottom:2px; }
    .modal-content form button[type="submit"] { grid-column:1 / 3; margin-top:10px; background:#00aaff; color:#fff; border:none; padding:10px 0; border-radius:8px; cursor:pointer; font-size:1rem; font-weight:600; width:100%; max-width:260px; justify-self:center; transition:background 0.2s; }
    .modal-content form button[type="submit"]:hover { background:#0077b6; }
    .modal-content form .error-message { grid-column:1 / 3; color:red; margin-bottom:10px; font-weight:bold; text-align:center; }
    .action-stack {
        display: flex;
        flex-direction: row;
        gap: 6px;
        align-items: center;
        justify-content: center;
    }
    .action-stack button {
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border-radius: 6px;
        font-size: 18px;
        border: none;
        background: #f5f5f5;
        cursor: pointer;
        transition: background 0.2s;
    }
    .action-stack button:hover {
        background: #e6f7ff;
    }
    .action-stack .use-btn { color: #007bff; border: 1px solid #007bff; }
    .action-stack .archive-btn { color: #6c757d; border: 1px solid #6c757d; }
    .action-stack .restock-btn { color: #ffc107; border: 1px solid #ffc107; }
    /* Modal styles for new use modal */
    #useModal .modal-content form label { text-align: right; font-weight: 500; font-size: 0.98rem; margin-right: 4px; }
    #useModal .modal-content form input[type="number"] { width: 100%; padding: 7px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 2px; }
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
            <?php
            $newQueueCount = 0;
            $adminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : 0;
            $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
            $result = $conn->query("SELECT COUNT(*) AS cnt FROM queue WHERE status = 'In Queue' AND adminID = $adminID AND shopID = $barbershopID");
            if ($result && $row = $result->fetch_assoc()) {
                $newQueueCount = (int)$row['cnt'];
            }
            ?>
            <ul class="nav-links">
                <li style="position:relative;display:flex;align-items:center;">
                    <a href="queueing.php" class="<?= $current_page === 'queueing.php' ? 'active' : '' ?>" style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-list"></i> Queue
                        <?php if ((isset($_SESSION['notifications_enabled']) && $_SESSION['notifications_enabled']) && isset($newQueueCount) && $newQueueCount > 0): ?>
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
                <!-- Modal for Add Product -->
                <div id="productModal" class="modal">
                    <div class="modal-content">
                        <span class="close" id="closeProductModal">&times;</span>
                        <h2><?= $translations['add_product'] ?></h2>
                        <form id="productForm" method="post">
                            <label for="prod_name"><?= $translations['product_name'] ?>:</label>
                            <input type="text" id="prod_name" name="prod_name" placeholder="Enter Specific Product Name" required>
                            <label for="critical_level">Critical Level:</label>
                            <input type="number" id="critical_level" name="critical_level" placeholder="Enter critical level" min="1" value="5" required>
                            <label for="prod_qty"><?= $translations['quantity'] ?>:</label>
                            <input type="number" id="prod_qty" name="prod_qty" placeholder="<?= $translations['enter_quantity'] ?>" min="0" required>
                            <label for="prod_price"><?= $translations['price'] ?>:</label>
                            <input type="number" id="prod_price" name="prod_price" placeholder="<?= $translations['enter_price'] ?>" min="0" step="0.01" required>
                            <button type="submit" style="grid-column: 1 / 3; margin-top: 10px; background: #00aaff; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 600; width: 100%; max-width: 260px; justify-self: center; transition: background 0.2s;">
                                <?= $translations['add_product'] ?>
                            </button>
                        </form>
                    </div>
                </div>
                <!-- End Modal -->
                <div class="queue-list" style="margin-top:0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="margin: 0; display: flex; align-items: center;">
                            Shop Inventory
                        </h2>
                        <!-- Product and Status filter dropdowns -->
                        <form id="filterForm" method="get" style="display: flex; gap: 10px; align-items: center; margin: 0;">
                            <?php
                            // Fetch all non-archived products for filter
                            $filter_sql = "SELECT ProductID, PName FROM inventory WHERE Status != 'archived'";
                            if ($shopID) $filter_sql .= " AND shopID = $shopID";
                            $filter_sql .= " ORDER BY PName ASC, ProductID ASC";
                            $filter_result = $conn->query($filter_sql);
                            ?>
                            <select name="filter_product" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                                <option value="">All Products</option>
                                <?php if ($filter_result && $filter_result->num_rows > 0): ?>
                                    <?php while($fp = $filter_result->fetch_assoc()): ?>
                                        <option value="<?= $fp['ProductID'] ?>" <?= (isset($_GET['filter_product']) && $_GET['filter_product'] == $fp['ProductID']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($fp['PName']) ?> (ID: <?= $fp['ProductID'] ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <!-- Status filter -->
                            <select name="search_status" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                                <option value="">All Products</option>
                                <option value="critical" <?= (isset($_GET['search_status']) && $_GET['search_status'] === 'critical') ? 'selected' : '' ?>>Critical</option>
                                <option value="archived" <?= (isset($_GET['search_status']) && $_GET['search_status'] === 'archived') ? 'selected' : '' ?>>Archived</option>
                            </select>
                            <button id="openProductModal" type="button" style="background: #007bff; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 16px;">Add Product</button>
                        </form>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <!-- Removed index column "#" -->
                                <th><?= $translations['product_id'] ?></th>
                                <th><?= $translations['name'] ?></th>
                                <th>Critical Level</th>
                                <th><?= $translations['quantity'] ?></th>
                                <th><?= $translations['price'] ?></th>
                                <th><?= isset($translations['date_added']) ? $translations['date_added'] : 'Date Added' ?></th>
                                <th><?= $translations['status'] ?></th>
                                <th><?= $translations['actions'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        // Build filter/search SQL
                        $where = [];
                        if ($shopID) {
                            $where[] = "shopID = $shopID";
                        }
                        if (!empty($_GET['filter_product'])) {
                            $filterProductID = (int)$_GET['filter_product'];
                            $where[] = "ProductID = $filterProductID";
                        }
                        if (!empty($_GET['search_status'])) {
                            if ($_GET['search_status'] === 'critical') {
                                $where[] = "Status != 'archived' AND Quantity <= CriticalLevel";
                            } elseif ($_GET['search_status'] === 'archived') {
                                $where[] = "Status = 'archived'";
                            }
                        }
                        // Notification for low stock (sum by product name, case-insensitive)
                        $notif_sql = "SELECT LOWER(PName) as pname_lower, MIN(PName) as display_name, SUM(Quantity) as total_qty, MIN(CriticalLevel) as min_critical FROM inventory ";
                        $notif_sql .= ($shopID ? "WHERE shopID = $shopID " : "");
                        $notif_sql .= "GROUP BY pname_lower HAVING total_qty < min_critical";
                        $notif_result = $conn->query($notif_sql);
                        if ($notif_result && $notif_result->num_rows > 0) {
                            echo '<tr><td colspan="8" style="background:#fff3cd;color:#856404;font-weight:bold;text-align:center;">';
                            echo 'The following products need to be replenished: ';
                            $low_products = [];
                            while($np = $notif_result->fetch_assoc()) {
                                $low_products[] = htmlspecialchars($np['display_name']) . " (Qty: " . (int)$np['total_qty'] . ", Critical: " . (int)$np['min_critical'] . ")";
                            }
                            echo implode(', ', $low_products);
                            echo '</td></tr>';
                        }
                        $sql = "SELECT * FROM inventory";
                        if (!empty($where)) {
                            $sql .= " WHERE " . implode(' AND ', $where);
                        }
                        $result = $conn->query($sql);
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                // Hide archived products unless filter is set to archived
                                if ((!isset($_GET['search_status']) || $_GET['search_status'] !== 'archived') && isset($row['Status']) && $row['Status'] === 'archived') {
                                    continue;
                                }
                                // Determine status
                                if (isset($row['Status']) && $row['Status'] === 'archived') {
                                    $status = '<span>Archived</span>';
                                } elseif ($row['Quantity'] <= $row['CriticalLevel']) {
                                    $status = '<span style="color: red; font-weight: bold;">Critical</span>';
                                } else {
                                    $status = $row['Quantity'] > 0 ? $translations['available'] : $translations['not_available'];
                                }
                                echo "<tr>
                                    <td>{$row['ProductID']}</td>
                                    <td>" . htmlspecialchars($row['PName']) . "</td>
                                    <td>{$row['CriticalLevel']}</td>
                                    <td>{$row['Quantity']}</td>
                                    <td>{$row['Price']}</td>
                                    <td>" . (isset($row['date_added']) ? date('M d, Y h:i A', strtotime($row['date_added'])) : '-') . "</td>
                                    <td>{$status}</td>
                                    <td>
                                        <div class='action-stack'>";
                                if (!isset($row['Status']) || $row['Status'] !== 'archived') {
                                    // Use button (icon)
                                    echo "<button type='button' class='use-btn' 
                                            data-productid='{$row['ProductID']}'
                                            data-pname='" . htmlspecialchars($row['PName'], ENT_QUOTES) . "'
                                            data-qty='{$row['Quantity']}'
                                            title='Use'
                                            " . ($row['Quantity'] <= 0 ? 'disabled' : '') . ">
                                            <i class='fa-solid fa-minus'></i>
                                        </button>";
                                    // Restock button (icon)
                                    echo "<button type='button' class='restock-btn' 
                                            data-productid='{$row['ProductID']}'
                                            data-pname='" . htmlspecialchars($row['PName'], ENT_QUOTES) . "'
                                            data-critical='{$row['CriticalLevel']}'
                                            data-qty='{$row['Quantity']}'
                                            data-price='{$row['Price']}'
                                            title='Restock'>
                                            <i class='fa-solid fa-plus'></i>
                                        </button>";
                                    // Archive button (icon)
                                    echo "<form method='post' style='display:inline;'>
                                            <input type='hidden' name='archive_id' value='{$row['ProductID']}'>
                                            <button type='submit' name='archive' class='archive-btn' title='Archive' onclick='return confirm(\"Are you sure you want to archive this product?\")'>
                                                <i class='fa-solid fa-box-archive'></i>
                                            </button>
                                        </form>";
                                }
                                echo "</div>
                                    </td>
                                    </tr>";
                            }
                        } else {
                            echo '<tr><td colspan="8" style="text-align:center;">' . ($translations['no_results'] ?? 'No result found') . '</td></tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                    <!-- Modal for Use Product -->
                    <div id="useModal" class="modal">
                        <div class="modal-content">
                            <span class="close" id="closeUseModal">&times;</span>
                            <h2 style="margin-top: 0; margin-bottom: 18px; font-size: 1.4rem; font-weight: 700; text-align: center;">Use Product</h2>
                            <form id="useForm" method="post" style="display: grid; grid-template-columns: 120px 1fr; gap: 12px 10px; align-items: center; width: 100%;">
                                <input type="hidden" id="use_product_id" name="use_id">
                                <label for="use_prod_name" style="text-align: right;">Product Name:</label>
                                <input type="text" id="use_prod_name" name="use_prod_name" readonly>
                                <label for="use_qty" style="text-align: right;">Quantity to Use:</label>
                                <input type="number" id="use_qty" name="use_qty" min="1" value="1" required>
                                <button type="submit" style="grid-column: 1 / 3; margin-top: 10px; background: #007bff; color: #fff; border: none; padding: 10px 0; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 600; width: 100%; max-width: 260px; justify-self: center; transition: background 0.2s;">Use</button>
                            </form>
                        </div>
                    </div>
                    <!-- Modal for Restock Product -->
                    <div id="restockModal" class="modal">
                        <div class="modal-content">
                            <span class="close" id="closeRestockModal">&times;</span>
                            <h2 style="margin-top: 0; margin-bottom: 18px; font-size: 1.4rem; font-weight: 700; text-align: center;">Restock Product</h2>
                            <form id="restockForm" method="post" style="display: grid; grid-template-columns: 120px 1fr; gap: 12px 10px; align-items: center; width: 100%;">
                                <input type="hidden" id="restock_product_id" name="restock_product_id">
                                <label for="restock_prod_name" style="text-align: right;">Product Name:</label>
                                <input type="text" id="restock_prod_name" name="restock_prod_name" readonly>
                                <label for="restock_critical_level" style="text-align: right;">Critical Level:</label>
                                <input type="number" id="restock_critical_level" name="restock_critical_level" min="1" required>
                                <label for="restock_prod_qty" style="text-align: right;">Quantity:</label>
                                <input type="number" id="restock_prod_qty" name="restock_prod_qty" min="0" required>
                                <label for="restock_new_price" style="text-align: right;">New Restock Price:</label>
                                <input type="number" id="restock_new_price" name="restock_new_price" min="0" step="0.01" required>
                                <label for="restock_prod_price" style="text-align: right;">Current Price:</label>
                                <input type="number" id="restock_prod_price" name="restock_prod_price" min="0" step="0.01" readonly>
                                <button type="submit" style="grid-column: 1 / 3; margin-top: 10px; background: #ffc107; color: #fff; border: none; padding: 10px 0; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 600; width: 100%; max-width: 260px; justify-self: center; transition: background 0.2s;">Update</button>
                            </form>
                        </div>
                    </div>
                    <!-- End Restock Modal -->
                </div>
            </section>
        </main>
    </div>
    <script>
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('darkMode') === 'enabled') {
                document.body.classList.add('dark-mode');
            }
            var toggle = document.getElementById('darkModeToggle');
            if (toggle) {
                toggle.checked = localStorage.getItem('darkMode') === 'enabled';
                toggle.addEventListener('change', function() {
                    if (toggle.checked) {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'enabled');
                    } else {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'disabled');
                    }
                });
            }
            // Modal JS for Add Product
            var productModal = document.getElementById('productModal');
            var openProductBtn = document.getElementById('openProductModal');
            var closeProductSpan = document.getElementById('closeProductModal');
            if (openProductBtn && productModal && closeProductSpan) {
                openProductBtn.onclick = function() {
                    productModal.style.display = 'flex';
                }
                closeProductSpan.onclick = function() {
                    productModal.style.display = 'none';
                }
                window.addEventListener('click', function(event) {
                    if (event.target === productModal) {
                        productModal.style.display = 'none';
                    }
                });
            }
            // Modal JS for Use Product
            var useModal = document.getElementById('useModal');
            var closeUseSpan = document.getElementById('closeUseModal');
            document.querySelectorAll('.use-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.getElementById('use_product_id').value = btn.getAttribute('data-productid');
                    document.getElementById('use_prod_name').value = btn.getAttribute('data-pname');
                    document.getElementById('use_qty').max = btn.getAttribute('data-qty');
                    document.getElementById('use_qty').value = 1;
                    useModal.style.display = 'flex';
                });
            });
            if (closeUseSpan && useModal) {
                closeUseSpan.onclick = function() {
                    useModal.style.display = 'none';
                }
                window.addEventListener('click', function(event) {
                    if (event.target === useModal) {
                        useModal.style.display = 'none';
                    }
                });
            }

            // Modal JS for Restock
            var restockModal = document.getElementById('restockModal');
            var closeRestockSpan = document.getElementById('closeRestockModal');
            document.querySelectorAll('.restock-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.getElementById('restock_product_id').value = btn.getAttribute('data-productid');
                    document.getElementById('restock_prod_name').value = btn.getAttribute('data-pname');
                    document.getElementById('restock_critical_level').value = btn.getAttribute('data-critical');
                    document.getElementById('restock_prod_qty').value = btn.getAttribute('data-qty');
                    document.getElementById('restock_prod_price').value = btn.getAttribute('data-price');
                    document.getElementById('restock_new_price').value = '';
                    // Prevent reducing quantity: set min to current qty
                    document.getElementById('restock_prod_qty').min = btn.getAttribute('data-qty');
                    restockModal.style.display = 'flex';
                });
            });
            if (closeRestockSpan && restockModal) {
                closeRestockSpan.onclick = function() {
                    restockModal.style.display = 'none';
                }
                window.addEventListener('click', function(event) {
                    if (event.target === restockModal) {
                        restockModal.style.display = 'none';
                    }
                });
            }
            // Auto-submit filter form on change
            var filterForm = document.getElementById('filterForm');
            if (filterForm) {
                filterForm.querySelectorAll('select').forEach(function(sel) {
                    sel.addEventListener('change', function() {
                        filterForm.submit();
                    });
                });
            }
        });
    </script>
</body>
</html>
