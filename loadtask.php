<?php
// FILE: loadtask.php (Dipanggil oleh FullCalendar)

include 'db_connect.php'; 
session_start();

// --- Pengecekan Sesi dan Variabel (Lebih Aman) ---
$current_user_id = $_SESSION['login_id'] ?? 0;
$login_type = $_SESSION['login_type'] ?? 0;

if (!function_exists('encode_id') || !function_exists('decode_id')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Encoding functions missing.']);
    exit;
}

// 1. Ambil dan DEKODE Project ID dari GET
$encoded_project_id = $_GET['project_id'] ?? null;
// Project ID bernilai 0 jika 'Semua Project' dipilih atau ID tidak valid
$project_id = $encoded_project_id ? decode_id($encoded_project_id) : 0;
$project_filter_sql = "";

// 2. Filter tasks sesuai role user (Role 2 Manager, Role 3 Member)
$where_role = "";
if ($login_type == 2) { 
    // Manager: Project yang ia kelola ATAU ia menjadi anggota
    $where_role = " (FIND_IN_SET($current_user_id, p.user_ids) OR p.manager_id = $current_user_id) ";
} elseif ($login_type == 3) { 
    // Member: Project yang ia menjadi anggota
    $where_role = " FIND_IN_SET($current_user_id, p.user_ids) ";
} else {
    // Admin (Role 1) atau lainnya
    $where_role = " 1=1 "; 
}


// 3. Menggabungkan Filter Project yang dipilih dengan Filter Role
$where_combined = " WHERE {$where_role} ";

if ($project_id > 0) {
    // Jika Project ID spesifik dipilih, tambahkan filter Project ID numerik yang sudah didekode
    $where_combined .= " AND t.project_id = " . $project_id;
}


// 4. Ambil data task
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
        t.end_date,
        p.name AS project_name
    FROM task_list t 
    INNER JOIN project_list p ON t.project_id = p.id 
    {$where_combined}
";

$result = mysqli_query($conn, $sql);

$data = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row["end_date"])) {
            $data[] = array(
                // KRITIS: ENKRIPSI ID TASK SEBELUM DIKIRIM KE KALENDER
                'id'            => encode_id($row["id"]), 
                'title'         => $row["title"],
                'start'         => $row["end_date"], 
                'end'           => $row["end_date"],
                'description'   => $row["description"],
                'status'        => $row["status"],
                'content_pillar'=> $row["content_pillar"],
                'platform'      => $row["platform"],
                'project_name'  => $row["project_name"], 
                'reference'     => $row["reference_links"],
                'start_date'    => $row["start_date"],
                'end_date'      => $row["end_date"]
            );
        }
    }
}

header('Content-Type: application/json');
echo json_encode($data);
?>