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

// =========================================================================
//                   LOGIC 0: FILTER DAFTAR PROJECT UTAMA ($where)
// =========================================================================
// Variabel $where membatasi proyek berdasarkan role (Admin melihat semua)
$where = " WHERE 1=1 ";
if ($login_type == 2) {
    $where .= " AND manager_id = '$user_id' ";
} elseif ($login_type == 3) {
    $where .= " AND FIND_IN_SET('$user_id', user_ids) ";
}

// Ambil semua project yang valid untuk dropdown
$all_projects_q = $conn->query("SELECT id, name FROM project_list p $where ORDER BY name ASC");
$all_projects = [];
while($p = $all_projects_q->fetch_assoc()){
    $all_projects[] = $p;
}

// Ambil semua user untuk dropdown berdasarkan filter (Diperlukan oleh dropdown)
$user_filter_where = " WHERE 1=1 ";
$all_users_q = $conn->query("SELECT id, firstname, lastname FROM users $user_filter_where ORDER BY firstname ASC");
$all_users = [];
while($u = $all_users_q->fetch_assoc()){
    $all_users[] = $u;
}

// =========================================================================
//                   LOGIC 2: FILTER TUGAS (TASK) UTAMA
// =========================================================================
// $task_where HANYA akan menampung filter tambahan (User Dropdown atau Role 3)
$task_where = " WHERE 1=1 "; 

// --- A. Batasan Tugas berdasarkan Role ---

if ($login_type == 2) {
    // Manager: Hanya tugas yang ditugaskan kepada Employee (Role 3).
    $users_q = $conn->query("SELECT id FROM users WHERE type = 3");
    $role_3_user_ids = [];
    while($row = $users_q->fetch_assoc()){
        $role_3_user_ids[] = $row['id'];
    }
    
    if (!empty($role_3_user_ids)) {
        $task_where .= " AND (";
        foreach ($role_3_user_ids as $r3_id) {
            $task_where .= " FIND_IN_SET('$r3_id', t.user_ids) OR";
        }
        $task_where = rtrim($task_where, " OR") . " ) ";
        
    } else {
        $task_where .= " AND t.id = 0 ";
    }
} elseif ($login_type == 3) {
    // User (Role 3): Hanya tugas yang ditugaskan kepadanya.
    $task_where .= " AND FIND_IN_SET('$user_id', t.user_ids) "; 
}
// Administrator (Role 1) tidak diberi batasan role apa pun di $task_where.

// --- B. Filter tambahan berdasarkan GET parameters (dropdown filter) ---

// Filter Project (Diterapkan ke $where di Langkah C)
if ($selected_project_id > 0) {
    $where .= " AND p.id = '$selected_project_id' ";
}

// Filter User Dropdown (Diterapkan ke $task_where)
if ($login_type != 3 && $selected_user_id > 0) {
    $task_where .= " AND FIND_IN_SET('$selected_user_id', t.user_ids) ";
}


// Status mapping
$stat = [
    0 => "Pending",
    1 => "Started",
    2 => "On-Progress",
    3 => "On-Hold",
    4 => "Over Due",
    5 => "Done"
];

// Query project yang akan di-looping (menggunakan $where yang sudah difilter)
$projects = $conn->query("SELECT * FROM project_list p $where ORDER BY name ASC");
?>

<div class="container-fluid mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex">
            <div class="dropdown mr-2">
                <button class="btn dropdown-toggle text-white" type="button" id="projectDropdown"
                        data-toggle="dropdown" aria-expanded="false" style="background-color:#B75301;">
                    <?php 
                        if($selected_project_id){
                            $proj_name = array_column($all_projects, 'name', 'id')[$selected_project_id] ?? "Pilih Project";
                            echo htmlspecialchars($proj_name);
                        } else {
                            echo "Semua Project";
                        }
                    ?>
                </button>
                <div class="dropdown-menu" aria-labelledby="projectDropdown">
                    <a class="dropdown-item <?= $selected_project_id == 0 ? 'active' : '' ?>" 
                       href="index.php?page=task_list<?= $selected_user_id > 0 ? '&user_id=' . $selected_user_id : '' ?>">
                       Semua Project
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php foreach($all_projects as $p): ?>
                        <a class="dropdown-item <?= $selected_project_id == $p['id'] ? 'active' : '' ?>"
                           href="index.php?page=task_list&project_id=<?= $p['id'] ?><?= $selected_user_id > 0 ? '&user_id=' . $selected_user_id : '' ?>">
                            <?= htmlspecialchars($p['name']) ?>
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
                                    echo htmlspecialchars($user_name ?: "Pilih User");
                                } else {
                                    echo "Semua User";
                                }
                            ?>
                        </button>
                        
                        <div class="dropdown-menu" aria-labelledby="userDropdown">
                            <a class="dropdown-item <?= $selected_user_id == 0 ? 'active' : '' ?>" 
                            href="index.php?page=task_list<?= $selected_project_id > 0 ? '&project_id=' . $selected_project_id : '' ?>">
                            Semua User
                            </a>
                            <div class="dropdown-divider"></div>
                            <?php foreach($all_users as $u): ?>
                                <a class="dropdown-item <?= $selected_user_id == $u['id'] ? 'active' : '' ?>"
                                href="index.php?page=task_list&user_id=<?= $u['id'] ?><?= $selected_project_id > 0 ? '&project_id=' . $selected_project_id : '' ?>">
                                    <?= htmlspecialchars(ucwords($u['firstname'] . ' ' . $u['lastname'])) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif ?>
            </div>

        <button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
            <i class="fa fa-plus"></i> Add New Task
        </button>

    </div>
</div>

<?php
// ===== LOOPING PROJECT =====
if($projects->num_rows > 0):
while ($proj = $projects->fetch_assoc()):
    // Hitung progress
    $tprog = $conn->query("SELECT * FROM task_list WHERE project_id = {$proj['id']}")->num_rows;
    $cprog = $conn->query("SELECT * FROM task_list WHERE project_id = {$proj['id']} AND status = 5")->num_rows;
    $prog = $tprog > 0 ? ($cprog / $tprog) * 100 : 0;
    $prog = $prog > 0 ? number_format($prog, 2) : $prog;

    $prod = $conn->query("SELECT * FROM user_productivity WHERE project_id = {$proj['id']}")->num_rows;

    // Update status otomatis
    if ($proj['status'] == 0 && strtotime(date('Y-m-d')) >= strtotime($proj['start_date'])) {
        $proj['status'] = ($prod > 0 || $cprog > 0) ? 2 : 1;
    } elseif ($proj['status'] == 0 && strtotime(date('Y-m-d')) > strtotime($proj['end_date'])) {
        $proj['status'] = 4; // Over Due
    }

    // Ambil task dengan filter user dan project (FIXED)
    // $task_where menampung filter role dan user dropdown. Kita tambahkan project_id di sini.
    $tasks = $conn->query("SELECT * FROM task_list t $task_where AND t.project_id = {$proj['id']} ORDER BY t.task ASC");

    // Hanya tampilkan project yang memiliki task setelah di filter
    if($tasks->num_rows == 0) continue;
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
                        <col width="20%">   
                        <col width="10%">
                        <col width="20%">
                        <col width="5%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-center">No</th>
                            <th class="text-center">Task</th>
                            <th class="">Due Date</th>
                            <th class="text-center">Task Status</th>
                            <th class="text-center">Assigned</th>
                            <th class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = $tasks->fetch_assoc()): 
                            $desc = strip_tags(html_entity_decode($row['description']));

                            // === Cek deadline untuk status Over Due ===
                            $today = strtotime(date('Y-m-d'));
                            $end   = strtotime($row['end_date']);

                            if ($today > $end) {
                                if ($row['status'] != 0 && $row['status'] != 3 && $row['status'] != 5) {
                                    // update status ke Over Due (4) hanya jika bukan Pending, On-Hold, atau Done
                                    $row['status'] = 4;
                                }
                            }
                        ?>
                        <tr class="task-row" 
                            data-id="<?= $row['id'] ?>" 
                            data-pid="<?= $proj['id'] ?>" 
                            style="cursor:pointer;">
                            <td class="text-center"><?php echo $i++ ?></td>
                            <td class="text-center">
                                <b><?php echo ucwords($row['task']) ?></b>
                                <p class="truncate"><?php echo $desc ?></p>
                            </td>
                            <td><b><?php echo date("M d, Y", strtotime($row['end_date'])) ?></b></td>
                            
                            <td class="text-center">
                                <?php
                                $status_code = (int)$row['status'];
                                $tstatus = $stat[$status_code] ?? 'Pending';

                                $badge_class = [
                                    "Pending"     => "secondary",
                                    "Started"     => "info",
                                    "On-Progress" => "primary",
                                    "On-Hold"     => "warning",
                                    "Over Due"    => "danger",
                                    "Done"        => "success"
                                ];

                                $color = $badge_class[$tstatus] ?? "secondary";

                                echo "<span class='badge badge-{$color} p-2'>{$tstatus}</span>";
                                ?>
                            </td>

                            <td class="text-center">
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
                                    <div class="d-flex justify-content-center">
                                        <?php foreach ($task_assigned_users as $au): ?>
                                            <img src="assets/uploads/<?php echo !empty($au['avatar']) ? $au['avatar'] : 'default.png'; ?>" 
                                                 alt="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>" 
                                                 class="rounded-circle border border-secondary" 
                                                 style="width:35px; height:35px; object-fit:cover; margin-left:-8px;" 
                                                 title="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>">
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No User</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
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
                <p class="text-center">Tidak ada proyek yang sesuai dengan filter.</p>
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
$(document).ready(function(){

    // View Task (dropdown)
    $('.view_task').click(function(){
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
        // supaya klik tombol dropdown dll tidak ikut aksi ini
        if($(e.target).closest('.dropdown').length || $(e.target).is('button') || $(e.target).is('a') || $(e.target).is('i')) return;

        var taskId = $(this).data('id');
        // Memanggil modal detail saat baris diklik
        uni_modal("Task Details","get_task_detail.php?id="+ taskId ,"mid-large");
    });
});


// Function delete
function delete_task(id){
    start_load();
    $.ajax({
        url: 'ajax.php?action=delete_task',
        method: 'POST',
        data: { id: id },
        success: function(resp){
            if(resp == 1){
                alert_toast("Task berhasil dihapus", "success");
                setTimeout(() => location.reload(), 1500);
            } else {
                alert_toast("Gagal menghapus task", "danger");
            }
        }
    });
}
</script>


<style>
    table p {
        margin: unset !important;
    }
    table td {
        vertical-align: middle !important;
    }
</style>