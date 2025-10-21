<?php
include 'db_connect.php';

if (!isset($_SESSION['login_id']) || !isset($_SESSION['login_type'])) {
    die('Unauthorized access.');
}

$current_user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];

// Filter project sesuai role
$where = "";
if ($login_type == 2) { // Manager
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids) OR manager_id = $current_user_id";
} elseif ($login_type == 3) { // Member
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids)";
}

// Ambil semua project yang diizinkan
$projects = [];
$project_q = $conn->query("SELECT id, name FROM project_list $where ORDER BY name ASC");
while ($row = $project_q->fetch_assoc()) {
    $projects[] = $row;
}
$allowed_project_ids = array_column($projects, 'id');

// Cek project_id dari URL
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!in_array($project_id, $allowed_project_ids)) {
    $project_id = $allowed_project_ids[0] ?? 0;
}

// Update status task (drag & drop)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $id = (int) $_POST['id'];
    $status = (int) $_POST['status'];
    $conn->query("UPDATE task_list SET status = $status WHERE id = $id");
    exit;
}

// Status map hanya yg ditampilkan
$status_map = [
    0 => ['label' => 'Pending',     'color' => 'bg-secondary text-white rounded-3 p-3'],
    2 => ['label' => 'On-Progress', 'color' => 'bg-primary text-white rounded-3 p-3'],
    3 => ['label' => 'Hold',        'color' => 'bg-warning text-white rounded-3 p-3'],
    5 => ['label' => 'Done',        'color' => 'bg-success text-white rounded-3 p-3']
];


$tasks = [0 => [], 2 => [], 3 => [], 5 => []];
$query = $conn->query("
    SELECT t.*, p.name AS project_name
    FROM task_list t
    INNER JOIN project_list p ON t.project_id = p.id
    WHERE t.project_id = $project_id
");

while ($row = $query->fetch_assoc()) {
    if (array_key_exists($row['status'], $tasks)) {
        $tasks[$row['status']][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kanban - Project Board</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .kanban-column {
            min-height: 600px;
        }
        .task-card {
            margin-bottom: 10px;
            cursor: move;
        }
        .status-hold {
            background-color: #0d6efd; /* biru */
            color: #fff;
            border-radius: .5rem;
            padding: .5rem 1rem;
        }
    </style>
</head>
<body>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Kanban Board</h2>
        <div class="dropdown">
            <button class="btn text-white dropdown-toggle" type="button" id="projectDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background-color:#B75301;">
                <?= htmlspecialchars(array_column($projects, 'name', 'id')[$project_id] ?? 'Pilih Project') ?>
            </button>
            <ul class="dropdown-menu" aria-labelledby="projectDropdown">
                <?php foreach ($projects as $project): ?>
                    <li>
                        <a class="dropdown-item <?= $project_id == $project['id'] ? 'active' : '' ?>"
                           href="index.php?page=kanban&project_id=<?= $project['id'] ?>">
                            <?= htmlspecialchars($project['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php if ($project_id): ?>
        <div class="row">
            <?php foreach ($status_map as $status => $info): ?>
                <div class="col-md-3">
                    <h5 class="text-center fw-bold <?= $info['color']; ?>">
                        <?= $info['label'] ?>
                    </h5>
                    <div class="kanban-column" id="column-<?= $status ?>" data-status="<?= $status ?>">
                        <?php foreach ($tasks[$status] as $task): ?>
                            <div class="card task-card" draggable="true" data-id="<?= $task['id'] ?>">
                                <div class="card-body">
                                    <h6 class="card-title fw-bold"><?= ($task['task'])?></h6>
                                    <p class="card-text text-muted"><small>Project: <?= ($task['project_name'])?></small></p>
                                    <p class="card-text"><?= nl2br($task['description']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">Anda belum memiliki project.</div>
    <?php endif; ?> 
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.task-card').forEach(card => {
    card.addEventListener('dragstart', e => {
        e.dataTransfer.setData('text/plain', card.dataset.id);
    });
});

document.querySelectorAll('.kanban-column').forEach(column => {
    column.addEventListener('dragover', e => e.preventDefault());

    column.addEventListener('drop', e => {
        e.preventDefault();
        const taskId = e.dataTransfer.getData('text/plain');
        const newStatus = column.dataset.status;
        const taskCard = document.querySelector(`.task-card[data-id='${taskId}']`);
        column.appendChild(taskCard);

        fetch(window.location.href, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${taskId}&status=${newStatus}`
        })
        .then(res => res.text())
        .then(response => console.log(response));
    });
});

column.addEventListener('drop', e => {
    e.preventDefault();
    const taskId = e.dataTransfer.getData('text/plain');
    const newStatus = column.dataset.status;
    const taskCard = document.querySelector(`.task-card[data-id='${taskId}']`);
    column.appendChild(taskCard);

    console.log("Updating task", taskId, "to status", newStatus); // 👈 cek sebelum kirim

    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${taskId}&status=${newStatus}`
    })
    .then(res => res.text())
    .then(response => console.log("Server response:", response));
});

</script>
</body>
</html>
