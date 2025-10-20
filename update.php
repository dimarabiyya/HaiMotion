<?php
include 'db_connect.php';

if (isset($_POST['id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $start = mysqli_real_escape_string($conn, $_POST['start']);
    $end = mysqli_real_escape_string($conn, $_POST['end']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $query = "UPDATE events 
              SET title='$title', color='$color', start_event='$start', end_event='$end', description='$description' 
              WHERE id='$id'";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
}
?>
