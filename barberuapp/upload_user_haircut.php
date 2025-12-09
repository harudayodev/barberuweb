<?php
header('Content-Type: application/json');

$targetDir = "userhaircuts/";
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

if (isset($_POST['customername']) && isset($_POST['imageBase64'])) {
    $customername = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $_POST['customername']);
    $imageData = $_POST['imageBase64'];
    $imageData = str_replace('data:image/png;base64,', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $data = base64_decode($imageData);

    $filename = $targetDir . $customername . '_' . time() . '.png';

    if (file_put_contents($filename, $data)) {
        echo json_encode([
            "status" => "success",
            "message" => "Image uploaded successfully.",
            "path" => $filename
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to save image."
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Missing parameters."
    ]);
}
?>
