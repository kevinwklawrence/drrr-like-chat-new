<?php
session_start();
header('Content-Type: application/json');

// Clear the introduction flag from session
if (isset($_SESSION['show_introduction'])) {
    unset($_SESSION['show_introduction']);
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'no_flag']);
}
?>