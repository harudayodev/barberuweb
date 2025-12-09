<?php
// barbershop_select.php
session_start();
include 'Connection.php';

if (!isset($_SESSION['adminID'])) {
    header('Location: login.php');
    exit();
}

$adminID = $_SESSION['adminID'];

// Handle selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shopID'])) {
    $_SESSION['barbershopID'] = intval($_POST['shopID']);
    header('Location: management.php'); // Redirect to main page
    exit();
}

// Fetch all barbershops for this admin
$stmt = $conn->prepare('SELECT shopID, name FROM barbershops WHERE admin_id = ?');
$stmt->bind_param('i', $adminID);
$stmt->execute();
$result = $stmt->get_result();
$barbershops = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($barbershops) === 1) {
    // Only one barbershop, auto-select
    $_SESSION['barbershopID'] = $barbershops[0]['shopID'];
    header('Location: management.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Barbershop | BarberU</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            min-height: 100vh;
            height: 100vh;
            width: 100vw;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
        }
        .container {
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            margin: 0;
        }
        main {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 28px;
            margin-top: 20px;
        }
        .barbershop-card {
            min-height: 140px;
            height: 170px;
            background: linear-gradient(135deg, #e3f0ff 0%, #f7faff 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(44,62,80,0.13);
            padding: 36px 18px 28px 18px;
            text-align: center;
            cursor: pointer;
            transition: box-shadow 0.2s, transform 0.2s, border-color 0.2s;
            border: 2.5px solid #e0e7ef;
            font-family: 'Poppins', sans-serif;
            font-size: 1.15em;
            font-weight: 600;
            color: #2c3e50;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        .barbershop-card::before {
            content: "";
            position: absolute;
            top: -30px;
            right: -30px;
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, #d0e6fa 0%, transparent 70%);
            opacity: 0.35;
            z-index: 0;
        }
        .barbershop-card::after {
            content: "";
            position: absolute;
            bottom: -20px;
            left: -20px;
            width: 60px;
            height: 60px;
            background: radial-gradient(circle, #b6d6f2 0%, transparent 70%);
            opacity: 0.22;
            z-index: 0;
        }
        .barbershop-card:hover {
            box-shadow: 0 16px 40px rgba(44,62,80,0.22);
            transform: translateY(-8px) scale(1.05);
            border-color: #3498db;
            background: linear-gradient(135deg, #d0e6fa 0%, #eaf6ff 100%);
        }
        .barbershop-card h2 {
            font-size: 1.45em;
            font-weight: 700;
            margin: 0;
            color: #2c3e50;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 8px rgba(44,62,80,0.07);
        }
        .barbershop-card .go-label {
            display: block;
            margin-top: 18px;
            color: #3498db;
            font-weight: 500;
            font-size: 1em;
            letter-spacing: 0.2px;
            position: relative;
            z-index: 1;
            text-shadow: 0 1px 4px rgba(44,62,80,0.04);
        }
        @media (max-width: 600px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
            .barbershop-card {
                min-height: 120px;
                height: 140px;
            }
        }
        /* Prevent back button from working */
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function () {
            window.history.pushState(null, "", window.location.href);
        };
    </style>
</head>
<body>
    <!-- Blue background shapes for visual effect -->
    <div class="bg-shape shape1"></div>
    <div class="bg-shape shape2"></div>
    <div class="container">
        <main style="width: 100%; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box;">
            <div style="background: #fff; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 40px 30px; max-width: 600px; width: 100%; text-align: center;">
                <img src="Resources/ab.png" alt="Logo" style="width: 70px; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto;">
                <h1 style="font-size: 2.2em; font-weight: 700; margin-bottom: 20px; color: #2c3e50;">Select Barbershop</h1>
                <div class="card-grid">
                    <?php foreach ($barbershops as $shop): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="shopID" value="<?= htmlspecialchars($shop['shopID']) ?>">
                            <button type="submit" class="barbershop-card" style="border:none; background:none; padding:0; width:100%;">
                                <h2><?= htmlspecialchars($shop['name']) ?></h2>
                                <span class="go-label">Go to Barbershop</span>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
