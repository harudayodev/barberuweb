<?php
session_start();
// Clear any existing session data
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Role | BarberU</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Prevent back button from working
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function () {
            window.history.pushState(null, "", window.location.href);
        };
        
    </script>
</head>
</body>
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
        .back-button {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 999;
}

.btn-outline {
    font-family: 'Poppins', sans-serif;
    display: inline-block;
    padding: 10px 20px;
    border: 2px solid #3498db;
    background-color: transparent;
    color: #3498db;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.btn-outline:hover {
    background-color: #3498db;
    color: white;
}

    </style>
    <div class="bg-shape shape1"></div>
    <div class="bg-shape shape2"></div>
    <div class="container">
        <main style="width: 100%; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box;">
            <div style="background: #fff; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 40px 30px; max-width: 400px; width: 100%; text-align: center;">
                <img src="Resources/ab.png" alt="Logo" style="width: 70px; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto;">
                <h1 style="font-size: 2.2em; font-weight: 700; margin-bottom: 20px; color: #2c3e50;">Select Your Role</h1>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <form action="login.php" method="get">
                        <input type="hidden" name="role" value="admin">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Login as Admin</button>
                    </form>
                    <form action="sadmin_login.php" method="get">
                        <input type="hidden" name="role" value="super_admin">
                        <button type="submit" class="btn btn-secondary" style="width: 100%;">Login as Super Admin</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
<div class="back-button">
    <a href="joinus.php" class="btn-outline">← Back</a>
</div>

</body>
</html>

