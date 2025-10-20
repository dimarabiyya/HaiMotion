<?php
include 'db_connect.php';

if (isset($_POST["title"])) {
    $title = mysqli_real_escape_string($conn, $_POST["title"]);
    $start = mysqli_real_escape_string($conn, $_POST["start"]);
    $end = mysqli_real_escape_string($conn, $_POST["end"]);
    $color = mysqli_real_escape_string($conn, $_POST["color"]);
    $description = mysqli_real_escape_string($conn, $_POST["description"]);

    $query = "INSERT INTO events (title, start_event, end_event, color, description) 
              VALUES ('$title', '$start', '$end', '$color', '$description')";

    if (mysqli_query($conn, $query)) {
        echo "Event inserted successfully";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>
