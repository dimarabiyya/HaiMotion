<?php
include 'db_connect.php';
session_start();

// Filter project sesuai role
$where = "";
if ($login_type == 2) { // Manager
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids) OR manager_id = $current_user_id";
} elseif ($login_type == 3) { // Member
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids)";
}
// Ambil data task (pakai end_date sebagai tanggal event)
$sql = "
    SELECT 
        t.id, 
        t.task AS title, 
        t.description,
        t.status,
        t.content_pillar,
        t.platform,
        t.reference_links,
        t.start_date,
        t.end_date
    FROM task_list t 
    INNER JOIN project_list p ON t.project_id = p.id 
    $where
";

$result = mysqli_query($conn, $sql);

$data = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row["end_date"])) {
            $data[] = array(
                'id'            => $row["id"],
                'title'         => $row["title"],
                'start'         => $row["end_date"], 
                'end'           => $row["end_date"],
                'description'   => $row["description"],
                'status'        => $row["status"],
                'content_pillar'=> $row["content_pillar"],
                'platform'      => $row["platform"],
                'reference'     => $row["reference_links"],
                'start_date'    => $row["start_date"],
                'end_date'      => $row["end_date"]
            );
        }
    }
}

header('Content-Type: application/json');
echo json_encode($data);
