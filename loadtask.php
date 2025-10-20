<?php
include 'db_connect.php';
session_start();

// Kondisi WHERE berdasarkan tipe user
$where = '';
if ($_SESSION['login_type'] == 2) {
    $where = " WHERE p.manager_id = '{$_SESSION['login_id']}' ";
} elseif ($_SESSION['login_type'] == 3) {
    $where = " WHERE CONCAT('[', REPLACE(p.user_ids, ',', '],['), ']') LIKE '%[{$_SESSION['login_id']}]%' ";
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
