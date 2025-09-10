<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    if (is_numeric($id)) {
        $stmt = $conn->prepare("DELETE FROM firmware_data WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: index.php");
exit();
?>
