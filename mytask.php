<?php include 'db_connect.php' ?>
<?php include 'add_task.php'; ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<?php
// Ambil ID user yang sedang login
$current_user_id = $_SESSION['login_id'];

// ===== FILTER PROJECT SESUAI USER YANG LOGIN =====
// Manager (tipe 2) melihat semua proyeknya. User (tipe 3) melihat proyek yang ia masuki.
$where = " WHERE 1=1 ";
if ($_SESSION['login_type'] == 2) {
    // Manager sees projects they manage
    $where .= " AND p.manager_id = '{$current_user_id}' ";
} elseif ($_SESSION['login_type'] == 3) {
    // User sees projects they are assigned to
    // Menggunakan LIKE pada string yang sudah diformat dengan kurung siku
    $where .= " AND CONCAT('[', REPLACE(p.user_ids, ',', '],['), ']') LIKE '%[{$current_user_id}]%' ";
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
?>

<div class="container-fluid mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <h3 class="m-0">My Task</h3>
        
        <?php if($_SESSION['login_type'] != 3): // Biasanya user biasa (tipe 3) tidak bisa menambah task ?>
        <button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
            <i class="fa fa-plus"></i> Add Task
        </button>
        <?php endif; ?>
    </div>
</div>


<?php
// Query project yang melibatkan user yang login
$projects = $conn->query("SELECT * FROM project_list p $where ORDER BY name ASC");

// Set task filter condition: HANYA tugas yang di-assign ke user ini
$user_task_filter = " AND CONCAT('[', REPLACE(t.user_ids, ',', '],['), ']') LIKE '%[{$current_user_id}]%' ";
?>

<?php
// ===== LOOPING PROJECT =====
if($projects->num_rows > 0):
while ($proj = $projects->fetch_assoc()):
    
    // Ambil task dengan filter user yang sedang login
    $tasks = $conn->query("SELECT * FROM task_list t 
                           WHERE t.project_id = {$proj['id']} 
                           $user_task_filter 
                           ORDER BY t.end_date ASC");

    // Hanya tampilkan project yang memiliki task yang di-assign ke user ini
    if($tasks->num_rows == 0) continue;
?>

<div class="col-lg-12">
    <div class="card card-outline">
        <div class="card-header">
            Project <b><?php echo ucwords($proj['name']) ?></b> 
            <small class="text-muted ml-2">(<?php echo $tasks->num_rows; ?> Tugas untuk Anda)</small>
        </div>
        <div class="table-responsive">
            <div class="card-body">
                <table class="table table-hover table-condensed">
                    <colgroup>
                        <col width="5%">
                        <col width="45%">
                        <col width="15%">   
                        <col width="15%">
                        <col width="15%">
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
                                // Hanya tampilkan user yang di-assign (termasuk diri sendiri)
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
                                        
                                        <?php if($_SESSION['login_type'] != 3): ?>
                                           <a class="dropdown-item edit_task" href="javascript:void(0)" 
                                                data-id="<?= $row['id'] ?>" data-pid="<?= $proj['id'] ?>">
                                                <i class="fa fa-edit mr-2"></i> Edit
                                            </a>
                                        <?php endif; ?>

                                        <a class="dropdown-item new_productivity" 
                                           data-pid="<?php echo $proj['id'] ?>" 
                                           data-tid="<?php echo $row['id'] ?>" 
                                           data-task="<?php echo ucwords($row['task']) ?>" 
                                           href="javascript:void(0)">
                                            <i class="fa fa-plus mr-2"></i> Add Productivity
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
                <p class="text-center">Anda tidak memiliki tugas yang di-assign pada proyek mana pun.</p>
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
table p {
    margin: unset !important;
}
table td {
    vertical-align: middle !important;
}
</style>

<script>
$(document).ready(function(){

    // View Task (dropdown)
    $('.view_task').click(function(){
        // Memanggil modal detail menggunakan get_task_detail.php
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
});

// Klik seluruh row task untuk menampilkan modal detail
$('.task-row').click(function(e){
    // supaya klik tombol dropdown dll tidak ikut aksi ini
    if($(e.target).closest('.dropdown').length || $(e.target).is('button') || $(e.target).is('a') || $(e.target).is('i')) return;

    var taskId = $(this).data('id');
    // Memanggil modal detail saat baris diklik
    uni_modal("Task Details","get_task_detail.php?id="+ taskId ,"mid-large"); 
});

// Function delete (Diperlukan oleh _conf)
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