<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['login_id']) || !isset($_SESSION['login_type'])) {
    die('Unauthorized access.');
}

$current_user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];

// --- Handle Delete Task ---
// Memastikan output adalah JSON MURNI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task_id'])) {
    header('Content-Type: application/json');
    $id = (int) $_POST['delete_task_id'];
    
    if ($login_type == 1 || $login_type == 2) {
        // Asumsi fungsi delete di file ajax.php, kita hanya perlu menghapus di sini.
        $delete = $conn->query("DELETE FROM task_list WHERE id = $id");
        if ($delete) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized action']);
    }
    exit;
}
// -----------------------------

// Filter project sesuai role
$where = "";
if ($login_type == 2) { // Manager
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids) OR manager_id = $current_user_id";
} elseif ($login_type == 3) { // Member
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids)";
}

$projects = [];
$project_q = $conn->query("SELECT id, name FROM project_list $where ORDER BY name ASC");
while ($row = $project_q->fetch_assoc()) $projects[] = $row;
$allowed_project_ids = array_column($projects, 'id');

// Project ID dari dropdown
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!in_array($project_id, $allowed_project_ids) && !empty($allowed_project_ids)) {
    $project_id = $allowed_project_ids[0];
} elseif (empty($allowed_project_ids)) {
    $project_id = 0; // Tidak ada project yang diizinkan
}

// Update status drag & drop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $id = (int) $_POST['id'];
    $status = (int) $_POST['status'];
    $conn->query("UPDATE task_list SET status = $status WHERE id = $id");
    exit;
}

// --- NEW STATUS MAP (6 Columns) ---
$status_map = [
    0 => ['label' => 'Pending', 'color' => '#6c757d'],       
    1 => ['label' => 'Started', 'color' => '#6f42c1'],       
    2 => ['label' => 'On Progress', 'color' => '#0d6efd'],   
    3 => ['label' => 'Hold', 'color' => '#ffc107'],          
    4 => ['label' => 'Overdue', 'color' => '#dc3545'],       
    5 => ['label' => 'Done', 'color' => '#198754']           
];

$tasks = [0 => [], 1 => [], 2 => [], 3 => [], 4 => [], 5 => []];
if ($project_id) {
    $query = $conn->query("
        SELECT t.*, p.name AS project_name, 
               GROUP_CONCAT(CONCAT(u.firstname,' ',u.lastname) SEPARATOR ', ') AS assigned_names,
               GROUP_CONCAT(u.avatar SEPARATOR ',') AS avatars
        FROM task_list t
        INNER JOIN project_list p ON t.project_id = p.id
        LEFT JOIN users u ON FIND_IN_SET(u.id, t.user_ids)
        WHERE t.project_id = $project_id
        GROUP BY t.id
        ORDER BY t.id ASC
    ");
    while ($row = $query->fetch_assoc()) {
        if (isset($tasks[$row['status']])) {
            $tasks[$row['status']][] = $row;
        } else {
             if ($row['status'] == 4) {
                 $tasks[5][] = $row; 
             } else {
                 $tasks[0][] = $row; 
             }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Kanban Board - 6 Columns</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
/* --- Global Styles --- */
body {
    background-color: #f4f5f7;
    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    color: #172b4d;
    height: 100vh;
    overflow-x: auto; 
    padding: 20px 0;
}
.container-fluid-custom {
    padding: 0 20px;
    min-width: 1840px; 
}
.filter-area {
    margin-bottom: 25px;
    padding-left: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.dropdown-toggle {
    background-color: #B75301!important; 
    border: none;
    font-weight: 500;
}
.dropdown-toggle:hover {
    background-color: #A34A00 !important;
}

/* --- Kanban Board Styles (Main Area) --- */
.kanban-board-wrapper {
    display: flex;
    flex-wrap: nowrap;
    gap: 15px;
    padding-bottom: 10px;
    align-items: flex-start;
}

/* --- Kanban Column Styles (List) --- */
.kanban-column-container {
    flex: 0 0 300px; 
    max-height: 80vh;
}
.kanban-column {
    background: #ebecf0;
    border-radius: 8px;
    padding: 8px;
    min-height: 100px;
    max-height: 100%;
    overflow-y: auto;
    box-shadow: 0 1px 0 rgba(9, 30, 66, 0.25);
    transition: background 0.2s;
}
.kanban-column.drag-over {
    background: #dfe1e6;
}
.kanban-header {
    font-weight: 600;
    padding: 5px 10px 10px;
    font-size: 16px;
    color: #172b4d;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.task-count {
    background-color: rgba(9, 30, 66, 0.08);
    border-radius: 50%;
    padding: 2px 7px;
    font-size: 12px;
    font-weight: 500;
    color: #42526e;
}

/* --- Task Card Styles --- */
.task-card {
    background: #fff;
    border-radius: 5px;
    padding: 10px 12px;
    margin-bottom: 8px;
    cursor: pointer;
    box-shadow: 0 1px 0 rgba(9, 30, 66, 0.25);
    transition: all 0.2s;
    position: relative;
}
.task-card:hover {
    background: #f4f5f7;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.task-card.dragging {
    opacity: 0.5;
    border: 2px dashed #0079bf;
}
.task-title {
    font-weight: 500;
    font-size: 14px;
    color: #172b4d;
    line-height: 1.3;
}
.task-desc {
    font-size: 12px;
    color: #5e6c84;
    margin-top: 5px;
}
.task-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 8px;
}
.task-avatars {
    display: flex;
}
.task-avatars img {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid #fff;
    margin-left: -6px;
    object-fit: cover;
}
.task-avatars img:first-child {
    margin-left: 0;
}
.task-date {
    font-size: 11px;
    color: #5e6c84;
}

/* Delete Button */
.delete-btn {
    position: absolute;
    top: 5px;
    right: 5px;
    background: none;
    border: none;
    color: #dc3545;
    opacity: 0;
    transition: opacity 0.2s;
    padding: 2px 5px;
    font-size: 14px;
    line-height: 1;
}
.task-card:hover .delete-btn {
    opacity: 0.8;
}
.delete-btn:hover {
    opacity: 1;
}
</style>
</head>
<body>

<div class="container-fluid-custom">
    <div class="filter-area">
        <h3 class="mb-3">Kanban Board</h3>
        <div class="dropdown">
            <button class="btn text-white dropdown-toggle" type="button" id="projectDropdown"
                data-toggle="dropdown" aria-expanded="false" style="background-color: #0079bf !important;">
                <i class="fas fa-folder"></i>
                <?= htmlspecialchars(array_column($projects, 'name', 'id')[$project_id] ?? 'Pilih Project') ?>
            </button>
            <div class="dropdown-menu" aria-labelledby="projectDropdown">
                <?php foreach ($projects as $project): ?>
                    <li>
                        <a class="dropdown-item <?= $project_id == $project['id'] ? 'active' : '' ?>"
                           href="index.php?page=kanban&project_id=<?= $project['id'] ?>">
                            <?= htmlspecialchars($project['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($project_id): ?>
    <div class="kanban-board-wrapper">
        <?php foreach ($status_map as $status => $info): ?>
            <div class="kanban-column-container">
                <div class="kanban-header" style="color:<?= $info['color'] ?>;">
                    <span><?= $info['label'] ?></span>
                    <span class="task-count"><?= count($tasks[$status]) ?></span>
                </div>
                <div class="kanban-column" data-status="<?= $status ?>">
                    <?php foreach ($tasks[$status] as $task): ?>
                        <div class="task-card" data-id="<?= $task['id'] ?>" draggable="true">
                            <div class="task-title"><?= htmlspecialchars($task['task']) ?></div>
                            <?php if ($login_type == 1 || $login_type == 2): ?>
                                <button class="delete-btn" data-id="<?= $task['id'] ?>" title="Delete Task" onclick="event.stopPropagation(); deleteKanbanTask(<?= $task['id'] ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php endif; ?>
                            <div class="task-desc mt-1"><?= htmlspecialchars(substr($task['description'], 0, 80)) ?><?= strlen($task['description']) > 80 ? '...' : '' ?></div>
                            <div class="task-footer">
                                <div class="task-avatars">
                                    <?php 
                                    $avatars = explode(',', $task['avatars'] ?? '');
                                    $shown = 0;
                                    foreach ($avatars as $av) {
                                        if ($av && file_exists("assets/uploads/$av")) {
                                            echo "<img src='assets/uploads/$av' alt='user' title='{$task['assigned_names']}'>";
                                            $shown++;
                                            if ($shown >= 3) break;
                                        }
                                    }
                                    if ($shown == 0) echo "<img src='assets/default-avatar.png' alt='user'>";
                                    ?>
                                </div>
                                <div class="task-date">
                                    <?= date('d M', strtotime($task['start_date'])) ?> - <?= date('d M', strtotime($task['end_date'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div class="alert alert-warning mt-4 ms-2">Anda belum memiliki project. Silakan buat project baru atau minta manager/admin untuk menambahkan Anda ke sebuah project.</div>
    <?php endif; ?>
</div>

<div class="modal fade" id="uni_modal" role='dialog'>
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      </div>
      </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
// =================================================================
// ASUMSI: Fungsi uni_modal, start_load, end_load, dan _conf sudah ada
// di file induk (index.php) atau di sini sebagai fallback.
// Jika belum ada, Anda harus menambahkannya.
// =================================================================

// FALLBACK JIKA uni_modal TIDAK ADA
if (typeof uni_modal === 'undefined') {
    window.uni_modal = function(title, url, size = 'mid-large') {
        $('#uni_modal .modal-title').html(title);
        $('#uni_modal .modal-dialog').removeClass('modal-lg modal-md modal-sm').addClass(size);
        // start_load() // Jika ada
        $.ajax({
            url: url,
            success: function(resp) {
                if (resp) {
                    $('#uni_modal .modal-body').html(resp);
                    $('#uni_modal').modal('show');
                    // end_load() // Jika ada
                }
            },
            error: function(err) {
                 // end_load() // Jika ada
                 console.log(err);
            }
        });
    }
}
// FALLBACK JIKA _conf TIDAK ADA
if (typeof _conf === 'undefined') {
    window._conf = function(msg, func, params = []) {
        if(confirm(msg)) {
            window[func](...params);
        }
    }
}


// --- DELETE TASK FUNCTION (Untuk Tombol X di Kanban) ---
function deleteKanbanTask(id) {
    _conf("Are you sure to delete this task?", "delete_kanban_task_ajax", [id]);
}

function delete_kanban_task_ajax(id) {
    // start_load() // Jika ada
    $.ajax({
        url: window.location.href, // POST ke file ini sendiri
        method: 'POST',
        data: { delete_task_id: id },
        dataType: 'json',
        success: function(resp){
            // end_load() // Jika ada
            if(resp.status === 'success'){
                alert("Task berhasil dihapus!");
                $('.task-card[data-id="'+id+'"]').remove();
                location.reload(); 
            } else {
                alert("Gagal menghapus task: " + (resp.message || "Unknown error."));
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
             // end_load() // Jika ada
             console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
             alert("Terjadi kesalahan server saat menghapus task.");
        }
    });
}


// --- Drag & Drop dan Click ---
$(document).ready(function() {
    // Drag & Drop Logic
    $('.task-card').on('dragstart', function(e) {
        e.originalEvent.dataTransfer.setData('text/plain', $(this).data('id'));
        $(this).addClass('dragging');
    }).on('dragend', function() {
        $(this).removeClass('dragging');
    });

    $('.kanban-column').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('drag-over');
    }).on('dragleave', function() {
        $(this).removeClass('drag-over');
    }).on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
        const taskId = e.originalEvent.dataTransfer.getData('text/plain');
        const newStatus = $(this).data('status');
        const $card = $('.task-card[data-id="'+taskId+'"]');
        
        $(this).append($card);
        
        // Update status via POST
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { id: taskId, status: newStatus },
            success: function(resp) {
                console.log(`Task ${taskId} moved to status ${newStatus}.`);
            },
            error: function(err) {
                console.error('Error updating status:', err);
            }
        });
    });

    // Klik card buka detail modal (menggunakan uni_modal)
    $('.task-card').click(function(e) {
        // Mencegah aksi jika klik berasal dari tombol delete
        if ($(e.target).closest('.delete-btn').length) return; 

        const id = $(this).data('id');
        uni_modal("Task Detail", "get_task_detail.php?id=" + id, "modal-lg");
    });
});
</script>
</body>
</html>