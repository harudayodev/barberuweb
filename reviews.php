<?php
session_start();

// Session timeout: 15 minutes
$timeout_duration = 900;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: session_expired.html");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['adminID']) && !isset($_SESSION['barberID']) && !isset($_SESSION['sadminID'])) {
    header("Location: session_expired.html");
    exit();
}

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
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews | Admin Dashboard</title>
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
    /* Card styles for reviews */
    .reviews-grid {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 18px;
    }
    .review-card {
        background: linear-gradient(90deg, #f7fbff 80%, #e6f7ff 100%);
        border-radius: 10px;
        box-shadow: 0 1px 4px rgba(0,170,255,0.06);
        padding: 14px 18px 10px 18px;
        display: flex;
        flex-direction: column;
        min-height: 54px;
        border: 1px solid #e3eaf2;
        transition: box-shadow 0.18s, border-color 0.18s;
        position: relative;
    }
    .review-card:hover {
        box-shadow: 0 2px 10px rgba(0,170,255,0.13);
        border-color: #b6e6ff;
        background: linear-gradient(90deg, #e6f7ff 80%, #f7fbff 100%);
    }
    .review-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2px;
    }
    .review-user {
        font-weight: 600;
        color: #0093c9;
        font-size: 0.98em;
        letter-spacing: 0.01em;
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .review-date {
        font-size: 0.93em;
        color: #7ab6d6;
        font-weight: 500;
        margin-left: 10px;
        white-space: nowrap;
    }
    .review-content {
        font-size: 1em;
        color: #222;
        margin-bottom: 6px;
        margin-top: 2px;
        word-break: break-word;
        font-weight: 500;
        letter-spacing: 0.01em;
        line-height: 1.35;
    }
    .review-stars {
        margin-top: 0px;
        margin-bottom: 0px;
        display: flex;
        align-items: center;
        gap: 2px;
    }
    .review-stars .fa-star {
        color: #ffc107;
        font-size: 1.08em;
        filter: drop-shadow(0 1px 1px #ffe066);
    }
    /* Controls styling */
    .review-controls {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    #starFilter {
        padding:6px 12px; border-radius:6px; border:1px solid #cfe7fe; background:#fff; font-size:0.98rem; color:#000000;
        min-width:90px;
        margin-right: 8px;
        height: 34px;
    }
    .search-input-group {
        display: flex;
        align-items: center;
    }
    #reviewSearchInput {
        padding:6px 12px; border-radius:6px 0 0 6px; border:1px solid #cfe7fe; background:#fff; font-size:0.98rem; color:#000000;
        width: 180px;
        border-right: none;
        transition: border 0.2s;
        outline: none;
        height: 34px;
    }
    #searchIconBtn {
        background: #28a745;
        border: 1px solid #cfe7fe;
        border-radius: 0 6px 6px 0;
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #fff;
        font-size: 1.08em;
        margin-left: 0;
        transition: filter 0.2s;
        outline: none;
    }
    #searchIconBtn:hover {
        filter: brightness(0.92);
    }
    @media (max-width: 600px) {
        .reviews-grid {
            gap: 8px;
        }
        .review-controls {
            flex-direction: column;
            gap: 8px;
        }
        #reviewSearchInput {
            width: 100%;
        }
        .review-card {
            padding: 10px 6px 8px 6px;
        }
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
                <div class="queue-list" style="margin-top:0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                        <h2 style="margin: 0;">Shop Reviews</h2>
                        <!-- Redesigned Filter and Search Controls -->
                        <div class="review-controls">
                            <select id="starFilter" aria-label="Filter reviews by stars">
                                <option value="">All Stars</option>
                                <option value="1">1 Star</option>
                                <option value="2">2 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="5">5 Stars</option>
                            </select>
                            <div class="search-input-group">
                                <input type="text" id="reviewSearchInput" placeholder="Search reviews...">
                                <button id="searchIconBtn" tabindex="0" aria-label="Search">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Card layout for reviews -->
                    <div class="reviews-grid" id="reviewsGrid">
                    <?php
                    // Fetch reviews from the 'review' table.
                    $sql = "SELECT userID, reviewcontent, reviewdate, stars FROM review";
                    if ($shopID) {
                        $sql .= " WHERE shopID = $shopID";
                    }
                    $sql .= " ORDER BY reviewdate DESC";

                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo '<div class="review-card" data-stars="' . (int)$row['stars'] . '" data-review="' . htmlspecialchars(strtolower($row['reviewcontent'])) . '">';
                            echo '<div class="review-header">';
                            echo '<span class="review-user">User ' . htmlspecialchars($row['userID']) . '</span>';
                            echo '<span class="review-date">' . htmlspecialchars(date('F j, Y, g:i a', strtotime($row['reviewdate']))) . '</span>';
                            echo '</div>';
                            echo '<div class="review-content">' . htmlspecialchars($row['reviewcontent']) . '</div>';
                            echo '<div class="review-stars">' . str_repeat('<i class="fas fa-star"></i>', (int)$row['stars']) . '</div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div style="text-align:center;width:100%;color:#0077b6;font-weight:500;font-size:1.1em;">No reviews found for this shop.</div>';
                    }
                    ?>
                    </div>
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
            // ...existing dark mode code...

            // Redesigned filter/search functionality for cards
            const starFilter = document.getElementById('starFilter');
            const reviewSearchInput = document.getElementById('reviewSearchInput');
            const reviewsGrid = document.getElementById('reviewsGrid');
            const searchIconBtn = document.getElementById('searchIconBtn');

            function filterAndSearchCards() {
                const starFilterValue = starFilter.value;
                const searchTerm = reviewSearchInput.value.toLowerCase();
                const cards = reviewsGrid.getElementsByClassName('review-card');

                for (let i = 0; i < cards.length; i++) {
                    const card = cards[i];
                    const stars = card.getAttribute('data-stars');
                    const reviewText = card.getAttribute('data-review');

                    const starMatch = (starFilterValue === '' || stars === starFilterValue);
                    const textMatch = (searchTerm === '' || reviewText.includes(searchTerm));

                    if (starMatch && textMatch) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                }
            }

            // Auto-apply filter/search on input/change
            starFilter.addEventListener('change', filterAndSearchCards);
            reviewSearchInput.addEventListener('input', filterAndSearchCards);

            // Search icon triggers search (for accessibility)
            searchIconBtn.addEventListener('click', filterAndSearchCards);

            // Also allow pressing Enter in input to trigger search
            reviewSearchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    filterAndSearchCards();
                }
            });
        });
    </script>
</body>
</html>
