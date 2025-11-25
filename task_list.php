<?php include 'db_connect.php' ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<?php

$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];

// ----------------------------------------------------
// ✅ MODIFIKASI 1: MENDEKODE PROJECT ID DARI URL (KRITIS)
// ----------------------------------------------------
$raw_project_id = $_GET['project_id'] ?? null;
// Gunakan decode_id() untuk mendapatkan ID numerik internal
$decoded_project_id = $raw_project_id ? decode_id($raw_project_id) : 0;
// Gunakan ID numerik yang sudah didekode untuk query
$selected_project_id = intval($decoded_project_id); 


$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0; 
$selected_status = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : -1; // -1 untuk Semua Status

$encoder = function_exists('encode_id') ? 'encode_id' : function($id) { return $id; };

$stat = [
    0 => "Pending",
    1 => "Started",
    2 => "On-Progress",
    3 => "On-Hold",
    4 => "Over Due",
    5 => "Done"
];
$status_options = $stat;
$status_options[-1] = "All Status";
ksort($status_options);

$where_project_dropdown = " WHERE 1=1 ";
if ($login_type == 2) {
    $where_project_dropdown .= " AND (p.manager_id = '$user_id' OR FIND_IN_SET('$user_id', p.user_ids)) ";
} elseif ($login_type == 3) {
    $where_project_dropdown .= " AND FIND_IN_SET('$user_id', p.user_ids) ";
}

// Ambil semua project yang valid untuk dropdown
$all_projects_q = $conn->query("SELECT id, name FROM project_list p $where_project_dropdown ORDER BY name ASC");
$all_projects = [];
while($p = $all_projects_q->fetch_assoc()){
    $all_projects[] = $p;
}

// Ambil semua user untuk dropdown (Hanya untuk Admin/Manager)
$user_filter_where = " WHERE 1=1 ";
$all_users_q = $conn->query("SELECT id, firstname, lastname FROM users $user_filter_where ORDER BY firstname ASC");
$all_users = [];
while($u = $all_users_q->fetch_assoc()){
    $all_users[] = $u;
}

$project_query_where = $where_project_dropdown;
if ($selected_project_id > 0) {
    $project_query_where .= " AND p.id = '$selected_project_id' ";
}

// Query project yang akan di-looping
$projects = $conn->query("SELECT * FROM project_list p $project_query_where ORDER BY name ASC");

// 💡 LOGIC HELPER UNTUK MEMBANGUN URL FILTER YANG BERSIH
$base_params = [
    'page' => 'task_list',
    'user_id' => $selected_user_id > 0 ? $selected_user_id : null,
    'status' => $selected_status != -1 ? $selected_status : null,
];
$base_params = array_filter($base_params, fn($value) => $value !== null);
?>

<div class="container-fluid mb-3">
    <div class="row align-items-center">
        
        <div class="col-12 col-md-8 mb-2 mb-md-0">
            <div class="d-flex flex-wrap align-items-center">
                
                <div class="dropdown mr-2 mb-2 mb-md-0">
                    <button class="btn dropdown-toggle text-white" type="button" id="projectDropdown"
                            data-toggle="dropdown" aria-expanded="false" style="background-color:#B75301;">
                        <?php 
                            if($selected_project_id){
                                $proj_name = array_column($all_projects, 'name', 'id')[$selected_project_id] ?? "Choose Project";
                                echo "Project: " . htmlspecialchars($proj_name);
                            } else {
                                echo "All Projects";
                            }
                        ?>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="projectDropdown">
                        <?php 
                            // URL untuk All Projects (Project ID = 0)
                            $all_projects_url = 'index.php?' . http_build_query($base_params);
                        ?>
                        <a class="dropdown-item <?= $selected_project_id == 0 ? 'active' : '' ?>" 
                           href="<?= $all_projects_url ?>">
                           All Projects
                        </a>
                        <div class="dropdown-divider"></div>
                        <?php foreach($all_projects as $p): 
                            // ✅ MODIFIKASI 2: MENGGUNAKAN ENKODER UNTUK PROJECT ID DI LINK
                            $project_params = $base_params;
                            // Mengenkripsi ID Project untuk dikirim di URL
                            $project_params['project_id'] = $encoder($p['id']); 
                            $project_url = 'index.php?' . http_build_query($project_params);
                        ?>
                            <a class="dropdown-item <?= $selected_project_id == $p['id'] ? 'active' : '' ?>"
                               href="<?= $project_url ?>">
                                <?= htmlspecialchars($p['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dropdown mr-2 mb-2 mb-md-0">
                    <button class="btn dropdown-toggle text-white" type="button" id="statusDropdown"
                            data-toggle="dropdown" aria-expanded="false" style="background-color:#B75301;">
                        <?php 
                            echo "Status: " . htmlspecialchars($status_options[$selected_status]);
                        ?>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="statusDropdown">
                        <?php foreach($status_options as $key => $label): 
                            $status_params = $base_params;
                            $status_params['status'] = $key;
                            
                            // Jika ada Project ID yang dipilih (sudah numerik dari decoding di atas), tambahkan ke URL
                            if ($selected_project_id > 0) {
                                // ⚠️ PENTING: ID Project di URL kini harus terenkripsi!
                                $status_params['project_id'] = $encoder($selected_project_id);
                            }
                            
                            // Hapus status jika 'All Status'
                            if ($key == -1) unset($status_params['status']);
                            
                            $status_url = 'index.php?' . http_build_query($status_params);
                        ?>
                            <a class="dropdown-item <?= $selected_status == $key ? 'active' : '' ?>"
                               href="<?= $status_url ?>">
                                <?= htmlspecialchars($label) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>


                <?php if($_SESSION['login_type'] == 1 || $_SESSION['login_type'] == 2): ?>
                    <div class="dropdown">
                        <button class="btn dropdown-toggle text-white" type="button" id="userDropdown"
                                data-toggle="dropdown" aria-expanded="false" style="background-color:#B75301;">
                            <?php 
                                if($selected_user_id){
                                    $user_name = '';
                                    foreach ($all_users as $u) {
                                        if ($u['id'] == $selected_user_id) {
                                            $user_name = ucwords($u['firstname'] . ' ' . $u['lastname']);
                                            break;
                                        }
                                    }
                                    echo "User: " . htmlspecialchars($user_name ?: "Choose User");
                                } else {
                                    echo "All Users";
                                }
                            ?>
                        </button>
                        
                        <div class="dropdown-menu" aria-labelledby="userDropdown">
                            <?php 
                            // URL untuk All Users (User ID = 0)
                            $all_users_params = $base_params;
                            unset($all_users_params['user_id']); // Hapus user_id
                            
                            if ($selected_project_id > 0) {
                                // ⚠️ PENTING: ID Project di URL kini harus terenkripsi!
                                $all_users_params['project_id'] = $encoder($selected_project_id);
                            }
                            $all_users_url = 'index.php?' . http_build_query($all_users_params);
                            ?>
                            <a class="dropdown-item <?= $selected_user_id == 0 ? 'active' : '' ?>" 
                            href="<?= $all_users_url ?>">
                            All Users
                            </a>
                            <div class="dropdown-divider"></div>
                            <?php foreach($all_users as $u): 
                                $user_params = $base_params;
                                $user_params['user_id'] = $u['id'];
                                
                                if ($selected_project_id > 0) {
                                    // ⚠️ PENTING: ID Project di URL kini harus terenkripsi!
                                    $user_params['project_id'] = $encoder($selected_project_id);
                                }
                                $user_url = 'index.php?' . http_build_query($user_params);
                            ?>
                                <a class="dropdown-item <?= $selected_user_id == $u['id'] ? 'active' : '' ?>"
                                href="<?= $user_url ?>">
                                    <?= htmlspecialchars(ucwords($u['firstname'] . ' ' . $u['lastname'])) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif ?>
            </div>
        </div>
    
            <div class="col-12 col-md-4">
                <div class="d-flex justify-content-center justify-content-md-end">
                    <button class="btn text-white" 
                            style="background-color:#B75301;" 
                            data-toggle="modal" 
                            data-target="#addTaskModal">
                        <i class="fa fa-plus mr-1"></i> Add New Task
                    </button>
                </div>
            </div>

    </div>
</div>

<?php
// ===== LOOPING PROJECT =====
if($projects->num_rows > 0):
while ($proj = $projects->fetch_assoc()):
    // Hitung progress (tetap dihitung dari semua task proyek untuk badge progress)
    $tprog = $conn->query("SELECT * FROM task_list WHERE project_id = {$proj['id']}")->num_rows;
    $cprog = $conn->query("SELECT * FROM task_list WHERE project_id = {$proj['id']} AND status = 5")->num_rows;
    $prog = $tprog > 0 ? ($cprog / $tprog) * 100 : 0;
    $prog = $prog > 0 ? number_format($prog, 2) : $prog;

    $prod = $conn->query("SELECT * FROM user_productivity WHERE project_id = {$proj['id']}")->num_rows;

    if ($proj['status'] == 0 && strtotime(date('Y-m-d')) >= strtotime($proj['start_date'])) {
        $proj['status'] = ($prod > 0 || $cprog > 0) ? 2 : 1;
    } elseif ($proj['status'] == 0 && strtotime(date('Y-m-d')) > strtotime($proj['end_date'])) {
        $proj['status'] = 4; // Over Due
    }

    $task_query = "
    SELECT t.* FROM task_list t 
    WHERE t.project_id = {$proj['id']}
    ";

    // Filter Task Berdasarkan Role
    if ($login_type == 3) {
        $task_query .= " AND FIND_IN_SET('$user_id', t.user_ids)";
    } 

    // Filter Task Berdasarkan User Dropdown
    if ($login_type != 3 && $selected_user_id > 0) {
        $task_query .= " AND FIND_IN_SET('$selected_user_id', t.user_ids)";
    }
    
    // Filter Task Berdasarkan Status Dropdown
    if ($selected_status != -1) {
        $task_query .= " AND t.status = '$selected_status'";
    }

    $task_query .= " ORDER BY t.id DESC";

    $tasks = $conn->query($task_query);
    // Hanya tampilkan project yang punya task setelah filter
    if(!$tasks || $tasks->num_rows == 0) continue;
?>

<div class="col-lg-12">
    <div class="card card-outline">
        <div class="card-header">
            Project <b><?php echo ucwords($proj['name']) ?></b>
        </div>
        <div class="table-responsive">
            <div class="card-body">
                <table class="table table-hover table-condensed">
                    <colgroup>
                        <col width="5%">
                        <col width="40%">
                        <col width="15%">   
                        <col width="15%">
                        <col width="25%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-left">No</th>
                            <th class="text-left">Task</th>
                            <th class="text-left">Due Date</th>
                            <th class="text-left">Task Status</th>
                            <th class="text-left">Assigned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = $tasks->fetch_assoc()): 
                            
                            // 💡 1. ENKRIPSI ID UNTUK HTML ATTRIBUTES
                            $encoded_task_id = $encoder($row['id']);
                            $encoded_project_id = $encoder($proj['id']);

                            $desc = strip_tags(html_entity_decode($row['description']));

                            $current_status = (int)$row['status'];
                            $today = strtotime(date('Y-m-d'));
                            $end   = strtotime($row['end_date']);

                            if ($today > $end && $current_status != 5 && $current_status != 3) {
                                $current_status = 4; // Over Due
                            }
                        ?>
                        <tr class="task-row" 
                            data-id="<?= $encoded_task_id ?>" 
                            data-pid="<?= $encoded_project_id ?>" 
                            style="cursor:pointer;">
                            <td class="text-left"><?php echo $i++ ?></td>
                            <td class="text-left">
                                <b><?php echo ucwords($row['task']) ?></b>
                                <p class="truncate"><?php echo $desc ?></p>
                            </td>
                            <td><b><?php echo date("M d, Y", strtotime($row['end_date'])) ?></b></td>
                            
                            <td class="text-left">
                                <?php
                                $tstatus = $stat[$current_status] ?? 'Pending';

                                $badge_class = [
                                    0=>'secondary',
                                    1=>'info',
                                    2=>'primary',
                                    3=>'warning',
                                    4=>'danger',
                                    5=>'success'
                                ][$current_status] ?? 'secondary';
                                
                                echo "<span class='badge badge-{$badge_class} p-2'>{$tstatus}</span>";
                                ?>
                            </td>

                            <td class="text-left">
                                <?php 
                                $task_assigned_users = [];
                                if (!empty($row['user_ids'])) {
                                    $task_user_ids = array_map('intval', explode(',', $row['user_ids']));
                                    if (!empty($task_user_ids)) {
                                        $ids_str = implode(',', $task_user_ids);
                                        $task_users_q = $conn->query("SELECT id, avatar, firstname, lastname FROM users WHERE id IN ($ids_str)");
                                        while ($u = $task_users_q->fetch_assoc()) {
                                            $task_assigned_users[] = $u;
                                        }
                                    }
                                }
                                
                                $max_display = 5;
                                $total_assigned = count($task_assigned_users);
                                $displayed_users = array_slice($task_assigned_users, 0, $max_display);
                                $remaining_count = $total_assigned - $max_display;
                                ?>
                                <?php if (!empty($task_assigned_users)): ?>
                                    <div class="d-flex justify-content-left align-items-center">
                                        <?php $margin = 0; foreach ($displayed_users as $au): ?>
                                            <img src="assets/uploads/<?php echo !empty($au['avatar']) ? $au['avatar'] : 'default.png'; ?>" 
                                                alt="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>" 
                                                class="rounded-circle border border-secondary" 
                                                style="width:35px; height:35px; object-fit:cover; margin-left:<?php echo $margin; ?>px;" 
                                                title="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>">
                                        <?php $margin = -8; endforeach; ?>

                                        <?php if ($remaining_count > 0): ?>
                                            <span class="badge badge-info rounded-pill p-2 ml-1" 
                                                style="margin-left:-8px; background-color:#818181; color:#fff;">
                                                +<?php echo $remaining_count; ?> 
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endwhile; ?>
<?php else: ?>
    <div class="col-lg-12">
        <div class="card card-outline">
            <div class="card-body">
                <p class="text-center">Tidak ada proyek yang sesuai dengan filter.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.table-responsive {
    overflow-x: auto;
}
</style>

<script>

$(document).ready(function(){

    // Fungsi untuk mendapatkan parameter URL (Mengambil HASH ID)
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    };

    $('.summernote').summernote({ height: 200 });

   
    $('#user_ids').select2({
        placeholder: "Select users",
        dropdownParent: $('#addTaskModal')
    });

    const encodedTaskId = getUrlParameter('id');
    const pageName = getUrlParameter('page'); 

    // Cek apakah ID Tugas (HASH) ada di URL
    if (encodedTaskId && pageName === 'task_list') {
        // ID yang dikirim ke modal detail tugas adalah HASH ID
        uni_modal("Task Details", "get_task_detail.php?id=" + encodedTaskId, "mid-large");
        
        // Opsional: Hapus parameter ID dari URL
        if (history.replaceState) {
            let cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + (pageName ? '?page=' + pageName : '');
            history.replaceState({path: cleanUrl}, '', cleanUrl);
        }
    }
    
    // Event handler untuk baris tugas di task_list.php
    $('.task-row').click(function(e){
        if($(e.target).closest('.dropdown, .dropdown-toggle, .dropdown-menu, .new_productivity, .edit_task, .delete_task').length) return;

        // Task ID yang diambil sudah terenkripsi dari data-id
        var encodedTaskId = $(this).data('id'); 
        uni_modal("Task Details","get_task_detail.php?id="+ encodedTaskId ,"mid-large");
    });
});

// Edit Task (buka modal)
$('.edit_task').click(function(){
    // ID Task dan Project sudah terenkripsi
    var encodedTaskId = $(this).data('id');
    var encodedProjectId = $(this).data('pid');
    
    uni_modal("<i class='fa fa-edit'></i> Edit Task",
        "manage_task.php?id=" + encodedTaskId + "&pid=" + encodedProjectId,
        "mid-large");
});

// Add Productivity (modal-xl)
$('.new_productivity').click(function(){
    // ID Task dan Project sudah terenkripsi
    var encodedPid = $(this).attr('data-pid');
    var encodedTid = $(this).attr('data-tid');
    uni_modal("<i class='fa fa-plus'></i> New Comment for: " + $(this).attr('data-task'),
        "manage_progress.php?pid=" + encodedPid + "&tid=" + encodedTid,
        "mid-large");
});

// Delete Task (ID yang digunakan adalah ID numerik, tapi harus diubah ke encoded jika delete di ajax.php mengharapkan encoded)
$('.delete_task').click(function(){
    // ID di data-id adalah HASH ID (seharusnya), tetapi karena tombol delete tidak ada di sini,
    // kita asumsikan fungsi ini dipanggil dari tempat lain (misal modal) dan mengirimkan HASH ID.
    // Jika tombol delete di tabel ini ada, pastikan data-id-nya di-encode.
    var encodedId = $(this).attr('data-id'); 
    _conf("Are you sure to delete this task?", "delete_task", [encodedId]);
});


// Function delete (assuming it is globally defined or defined here)
function delete_task(encodedId){ // Menerima ID terenkripsi
    // start_load() // Jika ada fungsi global loading
    $.ajax({
        url: 'ajax.php?action=delete_task',
        method: 'POST',
        data: { id: encodedId }, // Mengirim ID terenkripsi
        success: function(resp){
            if(resp.trim() == 1){ // ✅ PESAN ENGLIS
                alert_toast("Task successfully deleted", "success");
                setTimeout(() => location.reload(), 1500);
            } else {
                alert_toast("Failed to delete task", "error"); // ✅ PESAN ENGLIS
            }
        }
    });
}
</script>