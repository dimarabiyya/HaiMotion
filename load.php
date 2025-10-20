<?php
include 'db_connect.php';

$data = array();
$query = "SELECT * FROM events ORDER BY id";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $data[] = array(
            'id' => $row['id'],
            'title' => $row['title'],
            'start' => $row['start_event'],
            'end' => $row['end_event'],
            'className' => [$row['color']],
            'description' => $row['description']
        );
    }
}

echo json_encode($data);
?>
