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
// Manager (tipe 2) melihat semua proyeknya (baik yang dikelola maupun di-assign). User (tipe 3) melihat proyek yang ia masuki.
$where = " WHERE 1=1 ";
if ($_SESSION['login_type'] == 2) {
    // **PERUBAHAN DI SINI**: Manager sees projects they manage OR projects they are assigned to
    $where .= " AND (p.manager_id = '{$current_user_id}' 
                   OR CONCAT('[', REPLACE(p.user_ids, ',', '],['), ']') LIKE '%[{$current_user_id}]%') ";
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
        <button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
            <i class="fa fa-plus"></i> Add Task
        </button>
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
    
    $tasks = $conn->query("SELECT * FROM task_list t 
                       WHERE t.project_id = {$proj['id']} 
                       $user_task_filter 
                       ORDER BY t.date_created DESC");

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
                            <th class="text-left">No</th>
                            <th class="text-left">Task</th>
                            <th class="text-left">Due Date</th>
                            <th class="text-left">Task Status</th>
                            <th class="text-left">Assignee</th>
                            <th class="text-left"> </th>
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
                            <td class="text-left"><?php echo $i++ ?></td>
                            <td class="text-left">
                                <b><?php echo ucwords($row['task']) ?></b>
                                <p class="truncate"><?php echo $desc ?></p>
                            </td>
                            <td><b><?php echo date("M d, Y", strtotime($row['end_date'])) ?></b></td>
                            
                            <td class="text-left">
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

                            <td class="text-left">
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
                                
                                // Tentukan batas tampilan
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
                                        
                                            <a class="dropdown-item text-danger delete_task" href="javascript:void(0)" data-id="<?= $row['id'] ?>">
                                                <i class="fa fa-trash mr-2"></i> Delete
                                            </a>    
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
    // Fungsi untuk mendapatkan parameter URL
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
    // >>> END SOLUSI SELECT2 BARU DITAMBAHKAN DI SINI <<<

    const taskId = getUrlParameter('id');
    const pageName = getUrlParameter('page'); 

    // Cek apakah ID Tugas (numerik) ada di URL
    if (taskId && $.isNumeric(taskId)) {
        // Otomatis buka modal detail tugas
        uni_modal("Task Details", "get_task_detail.php?id=" + taskId, "mid-large");
        
        // Opsional: Hapus parameter ID dari URL agar modal tidak muncul saat refresh halaman
        if (history.replaceState) {
            // URL target adalah index.php?page=mytask
            let cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + (pageName ? '?page=' + pageName : '');
            history.replaceState({path: cleanUrl}, '', cleanUrl);
        }
    }
    
    // Pastikan event handler untuk baris tugas di mytask.php tetap berfungsi
    $('.task-row').click(function(e){
        // Mencegah aksi jika klik berasal dari dalam dropdown menu atau elemen interaktif lainnya
        if($(e.target).closest('.dropdown, .dropdown-toggle, .dropdown-menu, .new_productivity, .edit_task, .delete_task').length) return;

        var taskId = $(this).data('id');
        // Memanggil modal detail saat baris diklik
        uni_modal("Task Details","get_task_detail.php?id="+ taskId ,"mid-large");
    });
});

// Edit Task (buka modal)
    $('.edit_task').click(function(){
        var id = $(this).data('id');
        var pid = $(this).data('pid');
        uni_modal("<i class='fa fa-edit'></i> Edit Task",
            "manage_task.php?id=" + id + "&pid=" + pid,
            "mid-large");
    });

// Cek jika URL mengandung ?id=...
const params = new URLSearchParams(window.location.search);
const taskId = params.get('id');

if (taskId) {
  setTimeout(() => {
    uni_modal("Task Details", "get_task_detail.php?id=" + taskId, "mid-large");
  }, 500);
}

// Add Productivity (modal-xl)
    $('.new_productivity').click(function(){
        uni_modal("<i class='fa fa-plus'></i> New Comment for: " + $(this).attr('data-task'),
            "manage_progress.php?pid=" + $(this).attr('data-pid') + "&tid=" + $(this).attr('data-tid'),
            "mid-large");
    });

// Delete Task
    $('.delete_task').click(function(){
        var id = $(this).attr('data-id');
        _conf("Are you sure to delete this task?", "delete_task", [id]);
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



