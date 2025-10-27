<?php include 'db_connect.php' ?>
<?php include 'add_task.php'; ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<?php
// Pastikan variabel user_id, login_type, $conn sudah terdefinisi
$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];
$selected_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0; 
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0; 
$selected_status = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : -1; // -1 untuk Semua Status

// Status mapping
$stat = [
    0 => "Pending",
    1 => "Started",
    2 => "On-Progress",
    3 => "On-Hold",
    4 => "Over Due",
    5 => "Done"
];
$status_options = $stat;
$status_options[-1] = "Semua Status";
ksort($status_options);


// =========================================================================
//                   LOGIC 0: FILTER DAFTAR PROJECT UTAMA ($where)
// =========================================================================
// $where_project_dropdown digunakan untuk membatasi project di DROPDOWN filter dan loop utama
$where_project_dropdown = " WHERE 1=1 ";
if ($login_type == 2) {
    // Manager melihat project yang dia kelola ATAU dia menjadi anggota
    $where_project_dropdown .= " AND (p.manager_id = '$user_id' OR FIND_IN_SET('$user_id', p.user_ids)) ";
} elseif ($login_type == 3) {
    // User biasa hanya melihat project di mana dia menjadi anggota
    $where_project_dropdown .= " AND FIND_IN_SET('$user_id', p.user_ids) ";
}
// Admin (Role 1) tidak memiliki batasan


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
    // Anda mungkin ingin memfilter user berdasarkan role/project di sini, 
    // tapi untuk dropdown User, kita tampilkan semua user yang ada (paling fleksibel).
    $all_users[] = $u;
}

// =========================================================================
//                   LOGIC 1: APPLY PROJECT FILTER (untuk Query Project di Bawah)
// =========================================================================
$project_query_where = $where_project_dropdown;
if ($selected_project_id > 0) {
    $project_query_where .= " AND p.id = '$selected_project_id' ";
}

// Query project yang akan di-looping (menggunakan $project_query_where yang sudah difilter)
$projects = $conn->query("SELECT * FROM project_list p $project_query_where ORDER BY name ASC");
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
                                $proj_name = array_column($all_projects, 'name', 'id')[$selected_project_id] ?? "Pilih Project";
                                echo "Project: " . htmlspecialchars($proj_name);
                            } else {
                                echo "Semua Project";
                            }
                        ?>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="projectDropdown">
                        <a class="dropdown-item <?= $selected_project_id == 0 ? 'active' : '' ?>" 
                           href="index.php?page=task_list<?= $selected_user_id > 0 ? '&user_id=' . $selected_user_id : '' ?><?= $selected_status != -1 ? '&status=' . $selected_status : '' ?>">
                           Semua Project
                        </a>
                        <div class="dropdown-divider"></div>
                        <?php foreach($all_projects as $p): ?>
                            <a class="dropdown-item <?= $selected_project_id == $p['id'] ? 'active' : '' ?>"
                               href="index.php?page=task_list&project_id=<?= $p['id'] ?><?= $selected_user_id > 0 ? '&user_id=' . $selected_user_id : '' ?><?= $selected_status != -1 ? '&status=' . $selected_status : '' ?>">
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
                        <?php foreach($status_options as $key => $label): ?>
                            <a class="dropdown-item <?= $selected_status == $key ? 'active' : '' ?>"
                               href="index.php?page=task_list&status=<?= $key ?><?= $selected_project_id > 0 ? '&project_id=' . $selected_project_id : '' ?><?= $selected_user_id > 0 ? '&user_id=' . $selected_user_id : '' ?>">
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
                                    echo "User: " . htmlspecialchars($user_name ?: "Pilih User");
                                } else {
                                    echo "Semua User";
                                }
                            ?>
                        </button>
                        
                        <div class="dropdown-menu" aria-labelledby="userDropdown">
                            <a class="dropdown-item <?= $selected_user_id == 0 ? 'active' : '' ?>" 
                            href="index.php?page=task_list<?= $selected_project_id > 0 ? '&project_id=' . $selected_project_id : '' ?><?= $selected_status != -1 ? '&status=' . $selected_status : '' ?>">
                            Semua User
                            </a>
                            <div class="dropdown-divider"></div>
                            <?php foreach($all_users as $u): ?>
                                <a class="dropdown-item <?= $selected_user_id == $u['id'] ? 'active' : '' ?>"
                                href="index.php?page=task_list&user_id=<?= $u['id'] ?><?= $selected_project_id > 0 ? '&project_id=' . $selected_project_id : '' ?><?= $selected_status != -1 ? '&status=' . $selected_status : '' ?>">
                                    <?= htmlspecialchars(ucwords($u['firstname'] . ' ' . $u['lastname'])) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif ?>
            </div>
        </div>
        
        <?php if($_SESSION['login_type'] != 3): ?>
            <div class="col-12 col-md-4">
                <div class="d-flex justify-content-center justify-content-md-end">
                    <button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
                        <i class="fa fa-plus mr-1"></i> Add Task
                    </button>
                </div>
            </div>
        <?php endif ?>

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

    // Update status otomatis (Logika yang sama, jalankan di sisi PHP)
    if ($proj['status'] == 0 && strtotime(date('Y-m-d')) >= strtotime($proj['start_date'])) {
        $proj['status'] = ($prod > 0 || $cprog > 0) ? 2 : 1;
    } elseif ($proj['status'] == 0 && strtotime(date('Y-m-d')) > strtotime($proj['end_date'])) {
        $proj['status'] = 4; // Over Due
    }

    // ==========================
    // PERBAIKAN QUERY TASK DENGAN SEMUA FILTER
    // ==========================
    $task_query = "
    SELECT t.* FROM task_list t 
    WHERE t.project_id = {$proj['id']}
    ";

    // Filter Task Berdasarkan Role
    if ($login_type == 3) {
        // Role 3 (User): Hanya task yang ditugaskan padanya.
        $task_query .= " AND FIND_IN_SET('$user_id', t.user_ids)";
    } 
    // CATATAN: Role 1 (Admin) dan Role 2 (Manager) tidak difilter di sini 
    // karena mereka berhak melihat semua task di proyek yang sudah difilter di atas.

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
                        <col width="23%">
                        <col width="3%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-left">No</th>
                            <th class="text-left">Task</th>
                            <th class="text-left">Due Date</th>
                            <th class="text-left">Status</th>
                            <th class="text-left">Assigned To</th>
                            <th class="text-left"> </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = $tasks->fetch_assoc()): 
                            $desc = strip_tags(html_entity_decode($row['description']));

                            // === Cek deadline untuk status Over Due (Hanya perlu jika status di DB belum di-update) ===
                            $current_status = (int)$row['status'];
                            $today = strtotime(date('Y-m-d'));
                            $end   = strtotime($row['end_date']);

                            if ($today > $end && $current_status != 5 && $current_status != 3) {
                                $current_status = 4; // Over Due
                            }
                        ?>
                        <tr class="task-row" 
                            data-id="<?= $row['id'] ?>" 
                            data-pid="<?= $proj['id'] ?>" 
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
                                ?>
                                <?php if (!empty($task_assigned_users)): ?>
                                    <div class="d-flex justify-content-left">
                                        <?php 
                                        $max_show_avatars = 3;
                                        $displayed_count = 0;
                                        foreach (array_slice($task_assigned_users, 0, $max_show_avatars) as $au): 
                                            $avatar = !empty($au['avatar']) ? 'assets/uploads/'.$au['avatar'] : 'assets/uploads/default.png';
                                        ?>
                                            <img src="<?php echo $avatar; ?>" 
                                                 alt="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>" 
                                                 class="rounded-circle border border-secondary" 
                                                 style="width:35px; height:35px; object-fit:cover; margin-left:-8px;" 
                                                 title="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>">
                                        <?php 
                                            $displayed_count++;
                                        endforeach; 
                                        
                                        $more_count = count($task_assigned_users) - $max_show_avatars;
                                        if ($more_count > 0):
                                        ?>
                                             <span class="d-flex align-items-center justify-content-center text-secondary" 
                                                   style="width:35px; height:35px; font-size:12px; padding:0; margin-left:-8px; border-radius: 50%; background-color: #f8f9fa; border: 1px solid #ccc;"
                                                   title="and <?= $more_count ?> more members">
                                                +<?= $more_count ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No User</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-left">
                                <div class="dropdown">
                                    <button class="btn text-secondary" type="button" data-toggle="dropdown">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <h6 class="dropdown-header">Action</h6>
                                        <a class="dropdown-item view_task" href="javascript:void(0)" data-id="<?= $row['id'] ?>">
                                            <i class="fa fa-eye mr-2"></i> View
                                        </a>
                                           <a class="dropdown-item edit_task" href="javascript:void(0)" 
                                                data-id="<?= $row['id'] ?>" data-pid="<?= $proj['id'] ?>">
                                                <i class="fa fa-edit mr-2"></i> Edit
                                            </a>

                                            <a class="dropdown-item new_productivity" 
                                               data-pid="<?php echo $proj['id'] ?>" 
                                               data-tid="<?php echo $row['id'] ?>" 
                                               data-task="<?php echo ucwords($row['task']) ?>" 
                                               href="javascript:void(0)">
                                                <i class="fa fa-plus mr-2"></i> Add Comment
                                            </a>
                                        <?php if($_SESSION['login_type'] != 3): ?>
                                            <a class="dropdown-item text-danger delete_task" href="javascript:void(0)" data-id="<?= $row['id'] ?>">
                                                <i class="fa fa-trash mr-2"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
                <p class="text-left">Tidak ada proyek yang sesuai dengan filter.</p>
            </div>
        </div>
    </div>
<?php endif; ?>


<div class="modal fade" id="taskModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body" id="task_detail_content">
        </div>
    </div>
  </div>
</div>


<style>
.table-responsive {
    overflow-x: auto;
}
</style>

<script>
// Fungsi uni_modal, start_load, alert_toast, dan _conf harus tersedia di file lain (misalnya header.php atau script global Anda)
// Karena tidak disertakan, saya asumsikan mereka ada.

$(document).ready(function(){

    // View Task (dropdown)
    $('.view_task').click(function(){
        // Menggunakan parent().parent() untuk memastikan tidak ada event propagation dari tr
        uni_modal("Task Details","get_task_detail.php?id="+$(this).attr('data-id'),"mid-large")
    })
    
    // Delete Task
    $('.delete_task').click(function(){
        var id = $(this).attr('data-id');
        _conf("Are you sure to delete this task?", "delete_task", [id]);
    });

    // Add Productivity (modal-xl)
    $('.new_productivity').click(function(){
        uni_modal("<i class='fa fa-plus'></i> New Progress for: " + $(this).attr('data-task'),
            "manage_progress.php?pid=" + $(this).attr('data-pid') + "&tid=" + $(this).attr('data-tid'),
            "modal-xl");
    });
    
    // Edit Task (buka modal)
    $('.edit_task').click(function(){
        var id = $(this).data('id');
        var pid = $(this).data('pid');
        uni_modal("<i class='fa fa-edit'></i> Edit Task",
            "manage_task.php?id=" + id + "&pid=" + pid,
            "modal-xl");
    });
    
    // FIX: Klik seluruh row task untuk menampilkan modal detail
    $('.task-row').click(function(e){
        // Mencegah aksi jika klik berasal dari dalam dropdown menu atau elemen interaktif lainnya
        if($(e.target).closest('.dropdown, .dropdown-toggle, .dropdown-menu, .new_productivity, .edit_task, .delete_task').length) return;

        var taskId = $(this).data('id');
        // Memanggil modal detail saat baris diklik
        uni_modal("Task Details","get_task_detail.php?id="+ taskId ,"mid-large");
    });
});


// Function delete (assuming it is globally defined or defined here)
function delete_task(id){
    // start_load() jika ada
    $.ajax({
        url: 'ajax.php?action=delete_task',
        method: 'POST',
        data: { id: id },
        success: function(resp){
            if(resp == 1){
                // alert_toast("Task berhasil dihapus", "success"); // Ganti dengan fungsi alert_toast yang sebenarnya
                alert("Task berhasil dihapus");
                setTimeout(() => location.reload(), 1500);
            } else {
                // alert_toast("Gagal menghapus task", "danger"); // Ganti dengan fungsi alert_toast yang sebenarnya
                alert("Gagal menghapus task");
            }
        }
    });
}
</script>


<style>
    .dropdown-menu {
        /* Memastikan dropdown tampil di atas elemen lain */
        z-index: 1051 !important; 
    }
    table p {
        margin: unset !important;
    }
    table td {
        vertical-align: middle !important;
    }
</style>