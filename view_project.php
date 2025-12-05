<?php
// FILE: view_project.php (REVISI LENGKAP - KPI TIM)

include 'db_connect.php'; 
session_start();

// ID sudah didekode dan diverifikasi di index.php (Project ID numerik)
$id = $_GET['id'] ?? 0; 
if ($id === 0 || !is_numeric($id) || $id <= 0) {
    header("Location: index.php?page=404");
    exit;
}

// 💡 DEKLARASI FUNGSI ENCODER/DECODER UNTUK KEAMANAN
$encoder = function_exists('encode_id') ? 'encode_id' : function($i) { return $i; };

// 1. Query Proyek
$qry = $conn->query("SELECT * FROM project_list WHERE id = $id");
$project_data = $qry->fetch_array();

// 2. Cek jika proyek benar-benar ditemukan
if (!$project_data) {
    header("Location: index.php?page=404");
    exit;
}

// 3. Deklarasikan Variabel
foreach($project_data as $k => $v){
    $$k = $v;
}

// 4. Deklarasikan $row dan $today
$row = $project_data; 
$today = strtotime(date("Y-m-d"));

// 💡 ID PROJECT TERENKRIPSI UNTUK LINK KELUAR
$encoded_project_id = $encoder($id);

// =======================================================
// LOGIKA UTAMA DAN PENGHITUNGAN STATUS PROYEK
// =======================================================

$tprog_qry = $conn->query("SELECT id FROM task_list where project_id = {$id}");
$tprog = ($tprog_qry === false) ? 0 : $tprog_qry->num_rows;

$cprog_qry = $conn->query("SELECT id FROM task_list where project_id = {$id} and status = 5"); // Status 5 = Done
$cprog = ($cprog_qry === false) ? 0 : $cprog_qry->num_rows;

$prod_qry = $conn->query("SELECT id FROM user_productivity where project_id = {$id}");
$prod = ($prod_qry === false) ? 0 : $prod_qry->num_rows;

// Progress Proyek Keseluruhan (berdasarkan task yang Done)
$prog = $tprog > 0 ? ($cprog/$tprog) * 100 : 0;
$prog = $prog > 0 ?  number_format($prog,2) : $prog;

$endDate = strtotime($row['end_date']);
if($row['status'] != 5 && $row['status'] != 3 && $row['status'] != 0 && $today > $endDate){
    $row['status'] = 4; // Over Due
}

// Logika Status Otomatis Proyek
if($status == 0 && $today >= strtotime($start_date)){
    if($prod > 0 || $cprog > 0){
        $status = 2;
    } else {
        $status = 0;
    }
} 
elseif($status == 0 && $today > $endDate) {
    $status = 4;
}

$manager = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where id = $manager_id");
$manager = $manager->num_rows > 0 ? $manager->fetch_array() : array();

// mapping status
$stat = array(
    0 => "Pending",
    1 => "Started",
    2 => "On-Progress",
    3 => "On-Hold",
    4 => "Over Due",
    5 => "Done"
);

// =======================================================
// PENGHITUNGAN DATA UNTUK CHART.JS (DIREVISI)
// =======================================================

// --- 1. Data untuk Diagram DONUT (Task Status) ---
$all_tasks_qry_status = $conn->query("SELECT status, end_date FROM task_list WHERE project_id = {$id}");
$task_status_counts = array_fill(0, 6, 0); 
if ($all_tasks_qry_status->num_rows > 0) {
    while($task = $all_tasks_qry_status->fetch_assoc()) {
        $status_key = $task['status'];
        $endDate_task = strtotime($task['end_date']);
        $task_status = $status_key;
        if($status_key != 5 && $status_key != 3 && $today > $endDate_task){
            $task_status = 4; // Paksa jadi Over Due
        }
        if (isset($task_status_counts[$task_status])) {
            $task_status_counts[$task_status]++;
        }
    }
}
$donut_labels = json_encode(array_values($stat));
$donut_data = json_encode(array_values($task_status_counts));
$donut_colors = json_encode([
    '#6c757d', '#17a2b8', '#007bff', '#ffc107', '#dc3545', '#28a745'
]);

// --- 2. Data untuk Diagram PIE (Content Pillar) ---
$pillar_qry = $conn->query("SELECT content_pillar, COUNT(id) as count FROM task_list WHERE project_id = {$id} GROUP BY content_pillar HAVING content_pillar IS NOT NULL AND content_pillar != ''");
$pillar_labels_arr = [];
$pillar_data_arr = [];
if ($pillar_qry->num_rows > 0) {
    while ($p_row = $pillar_qry->fetch_assoc()) {
        $pillar_labels_arr[] = ucwords($p_row['content_pillar']);
        $pillar_data_arr[] = $p_row['count'];
    }
}
if (empty($pillar_labels_arr)) {
    $pillar_labels_arr = ['No Pillar Assigned'];
    $pillar_data_arr = [1];
}
$pie_labels = json_encode($pillar_labels_arr);
$pie_data = json_encode($pillar_data_arr);
$pie_colors = json_encode(['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de', '#e83e8c', '#6f42c1']);


// --- 3. Data untuk Diagram BAR (KPI Individual) ---
$all_tasks_qry = $conn->query("SELECT user_ids, status FROM task_list WHERE project_id = {$id} AND user_ids != ''");
$user_metrics = [];
$all_user_ids_str = '';
if (!empty($user_ids)) {
    $all_user_ids_str = $user_ids;
}
if ($all_tasks_qry->num_rows > 0) {
    $all_tasks_qry->data_seek(0);
    while ($task = $all_tasks_qry->fetch_assoc()) {
        $all_user_ids_str .= ',' . $task['user_ids'];
    }
}
$all_user_ids = array_unique(array_filter(explode(',', $all_user_ids_str)));
$all_user_ids_str = implode(',', $all_user_ids);


if (!empty($all_user_ids_str)) {
    $user_names_qry = $conn->query("SELECT id, concat(firstname, ' ', lastname) as name FROM users WHERE id IN ({$all_user_ids_str})");
    while ($u_row = $user_names_qry->fetch_assoc()) {
        $user_metrics[$u_row['id']] = ['name' => ucwords($u_row['name']), 'assigned' => 0, 'done' => 0];
    }
}

if ($all_tasks_qry->num_rows > 0) {
    $all_tasks_qry->data_seek(0); 
    while ($task = $all_tasks_qry->fetch_assoc()) {
        $assigned_users = array_filter(explode(',', $task['user_ids']));
        
        foreach ($assigned_users as $uid) {
            $uid = (int) $uid;
            if (isset($user_metrics[$uid])) {
                $user_metrics[$uid]['assigned']++;
                if ($task['status'] == 5) {
                    $user_metrics[$uid]['done']++;
                }
            }
        }
    }
}

$bar_labels = [];
$bar_data_assigned = [];
$bar_data_done = [];

foreach ($user_metrics as $metric) {
    $bar_labels[] = $metric['name'];
    $bar_data_assigned[] = $metric['assigned'];
    $bar_data_done[] = $metric['done'];
}

$bar_labels = json_encode($bar_labels);
$bar_data_assigned = json_encode($bar_data_assigned);
$bar_data_done = json_encode($bar_data_done);

?>

<div class="col-lg-12">
    <div class="row">
        <div class="col-md-12">
            <div class="card border p-2">
                <div class="col-md-12"> 
                    <div class="row">
                        <div class="col-sm-6">
                            <dl>
                                <dt>Project Name</dt>
                                <dd><?php echo ucwords($name) ?></dd>
                                <dt>Description</dt>
                                <dd>
                                    <?php $decoded_description = html_entity_decode(html_entity_decode($description)); echo strip_tags($decoded_description); 
                                    ?>
                                </dd>
                            </dl>
                            <dl>
                                <dt>Project Manager</dt>
                                <dd>
                                    <?php if(isset($manager['id'])) : ?>
                                    <div class="d-flex align-items-center mt-1">
                                        <img class="img-circle img-thumbnail p-0 shadow-sm border-info img-sm mr-3" src="assets/uploads/<?php echo $manager['avatar'] ?>" alt="Avatar">
                                        <?php echo ucwords($manager['name']) ?>
                                    </div>
                                    <?php else: ?>
                                        <small><i>Manager Deleted from Database</i></small>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl>
                                <dt>Start Date</dt>
                                <dd><?php echo date("F d, Y",strtotime($start_date)) ?></dd>
                            </dl>
                            <dl>
                                <dt>End Date</dt>
                                <dd><?php echo date("F d, Y",strtotime($end_date)) ?></dd>
                            </dl>
                            <dl>
                                <dt>Status</dt>
                                <dd>
                                    <?php 
                                    $badgeClass = [
                                        0 => "secondary",  // Pending
                                        1 => "info",
                                        2 => "primary",    // On-Progress
                                        3 => "warning",    // On-Hold
                                        4 => "danger",
                                        5 => "success"     // Done
                                    ];
                                    $current_status = $status;
                                    $class = isset($badgeClass[$current_status]) ? $badgeClass[$current_status] : "dark";
                                    echo "<span class='badge badge-{$class}'>".$stat[$current_status]."</span>";
                                    ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                        <div class="p-2">
                            <div class="">
                                <span><b class="bold-warning">Assignee:</b></span>
                                <div class="card-tools">    
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center text-nowrap user-avatar-stack">
                                    <?php 
                                    if(!empty($user_ids)):
                                        $members = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where id in ($user_ids) order by concat(firstname,' ',lastname) asc");
                                        while($row_m=$members->fetch_assoc()):
                                    ?>
                                            <img src="assets/uploads/<?php echo $row_m['avatar'] ?>" 
                                                alt="<?php echo ucwords($row_m['name']) ?>" 
                                                class="rounded-circle border border-white member-avatar" 
                                                style="width:38px; height:38px; object-fit:cover; margin-left:-8px;"
                                                title="<?php echo ucwords($row_m['name']) ?>">
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <span class="text-muted">No Team Member Assigned</span>
                                    <?php
                                    endif;
                                    ?>
                                </div>
                            </div>
                        </div>
                         <div class="mb-3">
                            <h6 class="mb-2"><b>Overall Progress</b></h5>
                            <div class="progress progress-sm progress-custom" style="height: 20px;">
                                <div class="progress-bar progress-bar-custom" role="progressbar" style="background-color:#B75301 ;width: <?php echo $prog ?>%" aria-valuenow="<?php echo $prog ?>" aria-valuemin="0" aria-valuemax="100">
                                    <b><?php echo $prog ?>%</b>
                                </div>
                            </div>
                            <small class="text-muted mt-1">Total: <?php echo $cprog ?> of <?php echo $tprog ?> tasks completed.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h5 class="mb-3"><b>Project Statistic</b></h5   >
        </div>

        <div class="col-md-4">
            <div class="card p-3 h-100">
                <h5 class="card-title text-center">Task Status </h5>
                <canvas id="doughnutChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card p-3 h-100">
                <h5 class="card-title text-center">Content Pillar</h5>
                <canvas id="pieChart" style="max-height: 250px;"></canvas>
            </div>
        </div>

        
        <div class="col-md-4">
            <div class="card p-3 h-100">
                <h5 class="card-title text-center">Team KPI</h5>
                <canvas id="barChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <hr class="mt-4 mb-4">

    <div class="row">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <span style="font-size: 20px;"><b>Task List</b></span>
                </div>
                <div class="col-md-6 text-right">
                    <div class="card-tools">
                        <button class="btn text-white" style="background-color:#B75301;" id="new_task">
                            <i class="fa fa-plus mr-1"></i> Add Task
                        </button>
                    </div>
                </div>
            </div>
            <div class="card mt-2">
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-condensed m-0 table-hover">
                        <colgroup>
                            <col width="5%">
                            <col width="40%">
                            <col width="25%">
                            <col width="10%">
                            <col width="20%">
                        </colgroup>
                        <thead>
                            <th class="text-left">No</th>
                            <th class="text-left">Task</th>
                            <th class="text-left">Description</th>
                            <th class="text-left">Status</th>
                            <th class="text-left">Assignee</th>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            $today = strtotime(date("Y-m-d"));
                            $tasks = $conn->query("SELECT * FROM task_list WHERE project_id = {$id} ORDER BY task ASC");
                            while($row_t = $tasks->fetch_assoc()):
                                $trans = get_html_translation_table(HTML_ENTITIES,ENT_QUOTES);
                                unset($trans["\""], $trans["<"], $trans[">"], $trans["<h2"]);
                                $desc = strtr(html_entity_decode($row_t['description']),$trans);
                                $desc = str_replace(array("<li>","</li>"), array("",", "), $desc);

                                $task_status = $row_t['status'];
                                $endDate = strtotime($row_t['end_date']);
                                if($task_status != 5 && $task_status != 3 && $today > $endDate){
                                    $task_status = 4;
                                }
                                
                                // 💡 ENKRIPSI ID TUGAS UNTUK LINK KELUAR
                                $encoded_task_id = $encoder($row_t['id']);
                            ?>
                                <tr class="view_task_row" data-id="<?php echo $encoded_task_id ?>" data-task="<?php echo $row_t['task'] ?>" style="cursor:pointer;">
                                    <td class="text-left"><?php echo $i++ ?></td>
                                    <td><b><?php echo ucwords($row_t['task']) ?></b></td>
                                    <td><p class="truncate"><?php echo strip_tags($desc) ?></p></td>
                                    <td>
                                        <?php 
                                        if($task_status == 0){
                                                echo "<span class='badge badge-secondary'>Pending</span>";
                                            }elseif($task_status == 1){
                                                echo "<span class='badge badge-info'>Started</span>";
                                            }elseif($task_status == 2){
                                                echo "<span class='badge badge-primary'>On-Progress</span>";
                                            }elseif($task_status == 3){
                                                echo "<span class='badge badge-warning'>On-Hold</span>";
                                            }elseif($task_status == 4){
                                                echo "<span class='badge badge-danger'>Over Due</span>";
                                            }elseif($task_status == 5){
                                                echo "<span class='badge badge-success'>Done</span>";
                                            }
                                        ?>
                                    </td>
                                    <?php 
                                    // === Assigned Users ===
                                    $task_assigned_users = [];
                                    if (!empty($row_t['user_ids'])) {
                                        $task_user_ids = explode(',', $row_t['user_ids']);
                                        $task_user_ids = array_map('intval', $task_user_ids);
                                        if (!empty($task_user_ids)) {
                                            $ids_str = implode(',', $task_user_ids);
                                            $task_users_q = $conn->query("SELECT id, avatar, firstname, lastname 
                                                                        FROM users 
                                                                        WHERE id IN ($ids_str)");
                                            while ($u = $task_users_q->fetch_assoc()) {
                                                $task_assigned_users[] = $u;
                                            }
                                        }
                                    }
                                    ?>
                                    <td class="text-left">
                                        <?php if (!empty($task_assigned_users)): ?>
                                            <div class="d-flex justify-content-left">
                                                <?php foreach ($task_assigned_users as $au): ?>
                                                    <img src="assets/uploads/<?php echo !empty($au['avatar']) ? $au['avatar'] : 'default.png'; ?>" 
                                                        alt="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>" 
                                                        class="rounded-circle border border-secondary" 
                                                        style="width:30px; height:30px; object-fit:cover; margin-left:-8px;" 
                                                        title="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>">
                                                <?php endforeach; ?>
                                            </div>
                                        <? else: ?>
                                            <span class="text-muted"></span>
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
    </div>
</div>
</div>
<style>
    /* ... CSS Styles (tetap sama) ... */
</style>

<script>
    function uni_modal(title, url, size = "mid-large") {
        if ($('#uni_modal .summernote').length) {
            $('#uni_modal .summernote').summernote('destroy');
        }
        if (typeof start_load !== 'undefined') { start_load(); } 
        
        $.ajax({
            url: url,
            success: function(resp) {
                if (resp) {
                    $('#uni_modal .modal-title').html(title);
                    $('#uni_modal .modal-body').html(resp);
                    $('#uni_modal .modal-dialog').removeClass('mid-large large').addClass(size); 
                    if ($('#uni_modal .select2').length) {
                        $('#uni_modal .select2').select2({
                            dropdownParent: $('#uni_modal'), 
                            width: '100%'
                        });
                    }
                    if ($('#uni_modal .summernote').length) {
                        $('#uni_modal .summernote').summernote({ 
                            height: 200,
                            toolbar: [
                                ['style', ['style']],
                                ['font', ['bold', 'italic', 'underline', 'clear']],
                                ['color', ['color']],
                                ['para', ['ul', 'ol', 'paragraph']],
                                ['insert', ['link', 'picture']],
                                ['view', ['codeview']]
                            ]
                        });
                    }
                    $('#uni_modal').modal('show');
                }
                if (typeof end_load !== 'undefined') { end_load(); } 
            },
            error: function() {
                if (typeof end_load !== 'undefined') { end_load(); } 
            }
        });
    }

    function delete_progress($id){
        if (typeof start_load !== 'undefined') { start_load(); }
        $.ajax({
            url:'ajax.php?action=delete_progress',
            method:'POST',
            data:{id:$id},
            success:function(resp){
                if(resp==1){
                    alert_toast("Data successfully deleted",'success')
                    setTimeout(function(){ location.reload() },1500)
                }
            }
        })
    }
    function delete_task(id){
        if (typeof start_load !== 'undefined') { start_load(); }
        $.ajax({
            url: 'ajax.php?action=delete_task',
            method: 'POST',
            data: { id: id },
            success: function(resp){
                if(resp == 1){
                    alert_toast("Task successfully deleted", 'success')
                    setTimeout(function(){  location.reload() }, 1500)
                }
            }
        })
    }
    
    // 💡 FIXED: Menggunakan ID Project Terenkripsi
    function edit_task(encodedTaskId, taskName = 'Task'){
        const encodedProjectId = '<?php echo $encoded_project_id; ?>'; // ID Project terenkripsi dari PHP
        uni_modal("Edit Task: " + taskName, "manage_task.php?pid=" + encodedProjectId + "&id=" + encodedTaskId, "mid-large");
    }

    $('#new_task').click(function(){
        const encodedProjectId = '<?php echo $encoded_project_id; ?>';
        uni_modal("New Task For <?php echo ucwords($name) ?>", "manage_task.php?pid=" + encodedProjectId + "&id=", "mid-large");
    })
    $('.edit_task').click(function(){
        const taskId = $(this).attr('data-id');
        const taskName = $(this).attr('data-task');
        edit_task(taskId, taskName);
    });
    $('.view_task').click(function(){
        // ID sudah dienkripsi
        uni_modal("Task Details","view_task.php?id="+$(this).attr('data-id'),"mid-large")
    })
    $('.delete_task').click(function(){
        // ID yang dikirim ke AJAX delete harus numerik
        _conf("Are you sure to delete this task?", "delete_task", [$(this).attr('data-id')])
    })
    $('#new_productivity').click(function(){
        const encodedProjectId = '<?php echo $encoded_project_id; ?>';
        uni_modal("<i class='fa fa-plus'></i> New Progress","manage_progress.php?pid=" + encodedProjectId,'large')
    })
    $('.manage_progress').click(function(){
        const encodedProjectId = '<?php echo $encoded_project_id; ?>';
        uni_modal("<i class='fa fa-edit'></i> Edit Progress","manage_progress.php?pid=" + encodedProjectId + "&id=" + $(this).attr('data-id'),'large')
    })
    $('.delete_progress').click(function(){
        _conf("Are you sure to delete this progress?","delete_progress",[$(this).attr('data-id')])
    })
    $('.view_task_row').click(function(e) {
        if (
            !$(e.target).closest('.dropdown').length &&
            !$(e.target).is('button') &&
            !$(e.target).is('a')
        ) {
            const taskId = $(this).data('id'); // HASH ID
            const taskName = $(this).data('task'); 
            uni_modal("Task: " + taskName, "get_task_detail.php?id=" + taskId, "mid-large");
        }
    });
    $('#uni_modal').off('hidden.bs.modal');
    // =========================================================
    // CHART.JS INITIALIZATION (REVISI LOGIKA)
    // =========================================================
    $(document).ready(function() {
        if (typeof Chart === 'undefined') {
            console.error("Chart.js library is not loaded.");
            return; 
        }
        
        // 1. Data KPI Individual (Bar Chart)
        const barLabels = <?php echo $bar_labels; ?>;
        const barDataAssigned = <?php echo $bar_data_assigned; ?>;
        const barDataDone = <?php echo $bar_data_done; ?>;

        const barCtx = document.getElementById('barChart');
        if (barCtx) {
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: barLabels,
                    datasets: [
                        {
                            label: 'Tasks Assigned',
                            data: barDataAssigned,
                            backgroundColor: '#007bff', // Biru (Assigned)
                            borderColor: '#007bff',
                            borderWidth: 1
                        },
                        {
                            label: 'Tasks Done',
                            data: barDataDone,
                            backgroundColor: '#28a745', // Hijau (Done)
                            borderColor: '#28a745',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { position: 'bottom' },
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                stepSize: 1 
                            }
                        }],
                        xAxes: [{
                            ticks: {
                                display: false 
                            },
                            gridLines: {
                                display: false
                            },
                            barPercentage: 0.8, 
                            categoryPercentage: 0.6
                        }]
                    }
                }
            });
        }

        // 2. Data Task Status (Doughnut Chart)
        const donutLabels = <?php echo $donut_labels; ?>;
        const donutData = <?php echo $donut_data; ?>;
        const donutColors = <?php echo $donut_colors; ?>;

        const doughnutCtx = document.getElementById('doughnutChart');
        if (doughnutCtx) {
            new Chart(doughnutCtx, {
                type: 'doughnut',
                data: {
                    labels: donutLabels,
                    datasets: [{
                        data: donutData,
                        backgroundColor: donutColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { position: 'bottom' },
                }
            });
        }

        // 3. Data Content Pillar (Pie Chart)
        const pieLabels = <?php echo $pie_labels; ?>;
        const pieData = <?php echo $pie_data; ?>;
        const pieColors = <?php echo $pie_colors; ?>;

        const pieCtx = document.getElementById('pieChart');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        data: pieData,
                        backgroundColor: pieColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { position: 'bottom' },
                }
            });
        }
    });

</script>