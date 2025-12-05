<?php include 'db_connect.php' ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<?php
// Ambil ID user yang sedang login
$current_user_id = $_SESSION['login_id'];

// 💡 DEKLARASI FUNGSI ENCODER/DECODER UNTUK KEAMANAN
// Fungsi encode_id() diasumsikan tersedia dari db_connect.php
$encoder = function_exists('encode_id') ? 'encode_id' : function($id) { return $id; };


// ===== FILTER PROJECT SESUAI USER YANG LOGIN =====
$where = " WHERE 1=1 ";
if ($_SESSION['login_type'] == 2) {
    $where .= " AND (p.manager_id = '{$current_user_id}' 
                   OR CONCAT('[', REPLACE(p.user_ids, ',', '],['), ']') LIKE '%[{$current_user_id}]%') ";
} elseif ($_SESSION['login_type'] == 3) {
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
    <div class="row align-items-center">
        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <h3 class="m-0">My Task</h3>
        </div>
        <div class="col-12 col-md-6">
            <div class="d-flex justify-content-center justify-content-md-end">
                <button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
                    <i class="fa fa-plus"></i> Add Task
                </button>
            </div>
        </div>
    </div>
    
    <!-- Search and Sort Section -->
    <div class="row align-items-center mt-3">
        <!-- Filter Status dan Sort di KIRI -->
        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <div class="d-flex flex-wrap">
                <div class="dropdown mr-2 mb-2 mb-md-0">
                    <button class="btn dropdown-toggle text-white" 
                            type="button" 
                            id="statusMyTaskDropdown"
                            data-toggle="dropdown" 
                            aria-expanded="false" 
                            style="background-color:#B75301;">
                        <i class="fa fa-filter mr-1"></i> <span id="statusMyTaskLabel">All Status</span>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="statusMyTaskDropdown">
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="-1">All Status</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="0">Pending</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="1">Started</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="2">On-Progress</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="3">On-Hold</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="4">Over Due</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="5">Done</a>
                    </div>
                </div>
                
                <div class="dropdown">
                    <button class="btn dropdown-toggle text-white" 
                            type="button" 
                            id="sortMyTaskDropdown"
                            data-toggle="dropdown" 
                            aria-expanded="false" 
                            style="background-color:#B75301;">
                        <i class="fa fa-sort mr-1"></i> <span id="sortMyTaskLabel">Sort: Task Name (A-Z)</span>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="sortMyTaskDropdown">
                        <a class="dropdown-item sort-mytask-option" href="#" data-sort="task-asc">Task Name (A-Z)</a>
                        <a class="dropdown-item sort-mytask-option" href="#" data-sort="task-desc">Task Name (Z-A)</a>
                        <a class="dropdown-item sort-mytask-option" href="#" data-sort="date-asc">Due Date (Earliest)</a>
                        <a class="dropdown-item sort-mytask-option" href="#" data-sort="date-desc">Due Date (Latest)</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search di KANAN -->
        <div class="col-12 col-md-6">
            <div class="input-group">
                <input type="text" 
                       class="form-control" 
                       id="searchMyTask" 
                       placeholder="Search by task name or assignee...">
                <div class="input-group-append">
                    <span class="input-group-text" style="background-color:#B75301; color:white; border:none;">
                        <i class="fa fa-search"></i>
                    </span>
                </div>
            </div>
        </div>
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
                        <col width="20%">
                        
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-left">No</th>
                            <th class="text-left">Task</th>
                            <th class="text-left">Due Date</th>
                            <th class="text-left">Task Status</th>
                            <th class="text-left">Assignee</th>
                            
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = $tasks->fetch_assoc()): 
                            
                            // 💡 1. ENKRIPSI ID UNTUK HTML ATTRIBUTES
                            $encoded_task_id = $encoder($row['id']);
                            $encoded_project_id = $encoder($proj['id']);
                            
                            $desc = strip_tags(html_entity_decode($row['description']));

                            // === Cek deadline untuk status Over Due ===
                            $today = strtotime(date('Y-m-d'));
                            $end   = strtotime($row['end_date']);

                            if ($today > $end) {
                                if ($row['status'] != 0 && $row['status'] != 3 && $row['status'] != 5) {
                                    $row['status'] = 4;
                                }
                            }
                        ?>
                        <tr class="task-row" 
                            data-id="<?= $encoded_task_id ?>" 
                            data-pid="<?= $encoded_project_id ?>"
                            data-status="<?= $row['status'] ?>"
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

    // 💡 3. Cek apakah ID Tugas (HASH) ada di URL
    if (encodedTaskId) {
        // Otomatis buka modal detail tugas
        uni_modal("Task Details", "get_task_detail.php?id=" + encodedTaskId, "mid-large");
        
        // Opsional: Hapus parameter ID dari URL
        if (history.replaceState) {
            let cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + (pageName ? '?page=' + pageName : '');
            history.replaceState({path: cleanUrl}, '', cleanUrl);
        }
    }
    
    // 💡 4. Event handler untuk baris tugas di mytask.php
    $('.task-row').click(function(e){
        if($(e.target).closest('.dropdown, .dropdown-toggle, .dropdown-menu, .new_productivity, .edit_task, .delete_task').length) return;

        // Task ID sudah terenkripsi dari data-id
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

// Delete Task (menggunakan ID numerik untuk AJAX)
$('.delete_task').click(function(){
    var numericId = $(this).attr('data-id'); 
    _conf("Are you sure to delete this task?", "delete_task", [numericId]);
});


// Function delete (assuming it is globally defined or defined here)
function delete_task(id){
    // ID yang diterima adalah ID numerik
    $.ajax({
        url: 'ajax.php?action=delete_task',
        method: 'POST',
        data: { id: id },
        success: function(resp){
            if(resp == 1){
                alert("Task berhasil dihapus");
                setTimeout(() => location.reload(), 1500);
            } else {
                alert("Gagal menghapus task");
            }
        }
    });
}

var currentMyTaskStatusFilter = -1; // -1 = All Status

$('.status-mytask-filter').on('click', function(e) {
    e.preventDefault();
    currentMyTaskStatusFilter = parseInt($(this).data('status'));
    
    // Update label
    $('#statusMyTaskLabel').text($(this).text());
    
    // Apply filter
    applyMyTaskFilters();
});

$('#searchMyTask').on('keyup', function() {
    applyMyTaskFilters();
});

function applyMyTaskFilters() {
    var searchValue = $('#searchMyTask').val().toLowerCase();
    
    $('.task-row').each(function() {
        var taskStatus = parseInt($(this).data('status'));
        var shouldShow = true;
        
        // Filter by status
        if (currentMyTaskStatusFilter !== -1 && taskStatus !== currentMyTaskStatusFilter) {
            shouldShow = false;
        }
        
        // Apply search filter
        if (searchValue !== '') {
            var taskName = $(this).find('td:eq(1) b').text().toLowerCase();
            var assignees = $(this).find('td:eq(4)').text().toLowerCase();
            
            if (taskName.indexOf(searchValue) === -1 && assignees.indexOf(searchValue) === -1) {
                shouldShow = false;
            }
        }
        
        if (shouldShow) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    
    // Check if no results for each project
    checkMyTaskNoResults();
}

// ============================================
// FITUR SORT MY TASK
// ============================================
var currentMyTaskSort = 'task-asc';

$('.sort-mytask-option').on('click', function(e) {
    e.preventDefault();
    currentMyTaskSort = $(this).data('sort');
    
    // Update label
    $('#sortMyTaskLabel').text('Sort: ' + $(this).text());
    
    // Sort tasks
    sortMyTasks(currentMyTaskSort);
});

function sortMyTasks(sortType) {
    $('.card.card-outline').each(function() {
        var $tbody = $(this).find('tbody');
        var $rows = $tbody.find('.task-row').toArray();
        
        $rows.sort(function(a, b) {
            var aVal, bVal;
            
            switch(sortType) {
                case 'task-asc':
                    aVal = $(a).find('td:eq(1) b').text().toLowerCase();
                    bVal = $(b).find('td:eq(1) b').text().toLowerCase();
                    return aVal.localeCompare(bVal);
                    
                case 'task-desc':
                    aVal = $(a).find('td:eq(1) b').text().toLowerCase();
                    bVal = $(b).find('td:eq(1) b').text().toLowerCase();
                    return bVal.localeCompare(aVal);
                    
                case 'date-asc':
                    aVal = $(a).find('td:eq(2) b').text();
                    bVal = $(b).find('td:eq(2) b').text();
                    return new Date(aVal) - new Date(bVal);
                    
                case 'date-desc':
                    aVal = $(a).find('td:eq(2) b').text();
                    bVal = $(b).find('td:eq(2) b').text();
                    return new Date(bVal) - new Date(aVal);
                    
                default:
                    return 0;
            }
        });
        
        // Reorder rows in table
        $.each($rows, function(index, row) {
            $tbody.append(row);
        });
    });
}

function checkMyTaskNoResults() {
    $('.card.card-outline').each(function() {
        var $tbody = $(this).find('tbody');
        var visibleRows = $tbody.find('.task-row:visible').length;
        
        $tbody.find('.no-results-row').remove();
        
        if (visibleRows === 0) {
            $tbody.append('<tr class="no-results-row"><td colspan="5" class="text-center text-muted">No tasks found</td></tr>');
        }
    });
}
</script>