<?php
include 'db_connect.php';
session_start();

// Asumsi fungsi decode_id() ada di file include/di tempat lain
if (!function_exists('decode_id')) {
    function decode_id($id) { return is_numeric($id) ? (int)$id : 0; }
}

if (!isset($_REQUEST['id'])) { 
    echo "ID tidak ditemukan.";
    exit;
}

$encoded_id = $_REQUEST['id'];

$id = decode_id($encoded_id);

// Jika decoding gagal atau ID tidak valid (null atau 0)
if (!is_numeric($id) || $id <= 0) {
    echo "ID Task tidak valid atau tidak dapat didekode.";
    exit;
}
// ---------------------------------------------------------------------

// Update status overdue sebelum fetch
$conn->query("
    UPDATE task_list 
    SET status = 4 
    WHERE end_date < NOW() 
    AND status NOT IN (0,3,5)
");


$id = intval($id); // Konversi ke integer untuk query
$qry = $conn->query("SELECT * FROM task_list WHERE id = $id");

if ($qry->num_rows > 0) {
    $row = $qry->fetch_assoc();
    $project_id = $row['project_id']; // Dapatkan Project ID untuk edit

    // Ambil data project
    $project_name = "Unknown Project";
    $proj_q = $conn->query("SELECT name FROM project_list WHERE id = $project_id LIMIT 1");
    if ($proj_q && $proj_q->num_rows > 0) {
        $project_name = $proj_q->fetch_assoc()['name'];
    }

    // Ambil data user pembuat task
    $creator = null;
    if (!empty($row['created_by'])) {
        $creator_q = $conn->query("SELECT firstname, lastname, avatar FROM users WHERE id = {$row['created_by']} LIMIT 1");
        if ($creator_q && $creator_q->num_rows > 0) {
            $creator = $creator_q->fetch_assoc();
        }
    }
    
    // Mapping status
    $statusArr = [
        0 => ['Pending', 'secondary'],
        1 => ['Started', 'info'],
        2 => ['On-Progress', 'primary'],
        3 => ['On-Hold', 'warning'],
        4 => ['Over Due', 'danger'],
        5 => ['Done', 'success']
    ];
    $status = $statusArr[$row['status']] ?? ['Unknown', 'dark'];
    ?>
    
    <div class="p-3">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="font-weight-bold mb-1" style="word-break: break-word;"><?= htmlspecialchars($row['task']) ?></h4>
      <span class="badge badge-<?= $status[1] ?> px-3 py-2" style="font-size:13px;">
        <?= $status[0] ?>
      </span>
    </div>
  </div>

  <hr>

  <div class="mb-3">
    <h6 class="text-muted mb-1">Project</h6>
    <h5 class="font-weight-bold mb-0" style="word-break: break-word;"><?= htmlspecialchars($project_name) ?></h5>
  </div>

  <div class="row mb-4">
    <div class="col-12 col-md-6 mb-3">
      <h6 class="text-muted">Created By</h6>
      <?php if ($creator): ?>
      <div class="d-flex align-items-center flex-wrap mt-2">
        <img 
          src="assets/uploads/<?= !empty($creator['avatar']) ? htmlspecialchars($creator['avatar']) : 'default.png' ?>" 
          alt="<?= ucwords($creator['firstname'].' '.$creator['lastname']) ?>" 
          class="rounded-circle border border-secondary"
          style="width:40px; height:40px; object-fit:cover; margin-right:8px;">
        <span><?= ucwords($creator['firstname'].' '.$creator['lastname']) ?></span>
      </div>
      <?php else: ?>
      <p class="text-muted mb-0">Unknown</p>
      <?php endif; ?>
    </div>

    <div class="col-12 col-md-6 mb-3">
      <h6 class="text-muted">Assignment User</h6>
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
      <div class="avatar-stack-container mt-2">
        <div class="d-flex align-items-center avatar-stack">
          <?php foreach ($task_assigned_users as $au): ?>
            <img 
              src="assets/uploads/<?= !empty($au['avatar']) ? htmlspecialchars($au['avatar']) : 'default.png'; ?>" 
              alt="<?= ucwords($au['firstname'].' '.$au['lastname']); ?>" 
              class="rounded-circle border border-secondary avatar-item" 
              title="<?= ucwords($au['firstname'].' '.$au['lastname']); ?>">
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <p class="text-muted mb-0">No User</p>
      <?php endif; ?>
    </div>

    <div class="col-12 col-md-6 mb-3">
      <h6 class="text-muted mb-1">Start Date</h6>
      <p class="mb-0"><?= date('F d, Y', strtotime($row['start_date'])) ?></p>
    </div>
    <div class="col-12 col-md-6 mb-3">
      <h6 class="text-muted mb-1">End Date</h6>
      <p class="mb-0"><?= date('F d, Y', strtotime($row['end_date'])) ?></p>
    </div>
  </div>

  <div class="mb-3">
    <h6 class="text-muted">Description</h6>
    <div class="p-2 bg-light rounded border description-content">
      <?= html_entity_decode($row['description']) ?>
    </div>
  </div>

  <div class="mb-3">
    <h6 class="text-muted">Content Pillar</h6>
    <div class="pillar-container"> 
      <?php 
      $pillars = array_filter(array_map('trim', explode(',', $row['content_pillar'])));
      if (!empty($pillars)) {
          foreach ($pillars as $p) {
              echo "<span class='badge badge-pill badge-primary mr-1 mb-1 px-3 py-2' style='font-size:13px;'>".ucwords($p)."</span>";
          }
      } else {
          echo "<span class='text-muted'>-</span>";
      }
      ?>
    </div>
  </div>

  <div class="mb-3">
    <h6 class="text-muted">Platform</h6>
    <div class="pillar-container">
      <?php 
      $platforms = array_filter(array_map('trim', explode(',', $row['platform'])));
      if (!empty($platforms)) {
          foreach ($platforms as $plat) {
              echo "<span class='badge badge-pill badge-success mr-1 mb-1 px-3 py-2' style='font-size:13px;'>$plat</span>";
          }
      } else {
          echo "<span class='text-muted'>-</span>";
      }
      ?>
    </div>
  </div>

  <div class="mb-3">
    <h6 class="text-muted">Reference Links</h6>
    <ul class="pl-3 links-container"> 
      <?php 
      $links = array_filter(array_map('trim', explode("\n", $row['reference_links'])));
      if (!empty($links)) {
          foreach ($links as $link) {
              $safe_link = htmlspecialchars($link);
              // Ubah <p> menjadi <li>
              echo "<li><a href='{$safe_link}' target='_blank'>{$safe_link}</a></li>";
          }
      } else {
          echo "<li class='text-muted'>No links</li>"; // Ubah <p> menjadi <li>
      }
      ?>
    </ul>
  </div>
</div>

<div class="modal-footer display p-0 m-0">
    <button class="btn mr-2" style="background-color: #2a80b9;" onclick="editTask(<?= $id ?>, <?= $project_id ?>)">Edit Task</button>
    <button type="button" class="btn btn-danger mr-auto" onclick="confirmDelete(<?= $id ?>)">Delete</button>
  <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
</div>

    
<script>
    function editTask(id, pid) {
      $('#uni_modal').modal('hide');
      
      setTimeout(function(){
          // Gunakan ukuran modal yang lebih lebar (large) untuk edit task
          // Agar manage_task.php yang biasanya punya banyak field input bisa tampil maksimal.
          uni_modal("<i class='fa fa-edit'></i> Edit Task",
              "manage_task.php?id=" + id + "&pid=" + pid,
              "large"); 
      }, 300); 
    }
    
    function confirmDelete(id) {
      $('#uni_modal').modal('hide');

      setTimeout(() => {
        _conf('Are you sure to delete this task?', 'delete_task', [id]);
      }, 400);
    }
    
    $('#uni_modal .modal-footer').hide(); 
    $('.modal-footer.display').show();
</script>
    
<?php
} else {
    echo "Data task tidak ditemukan.";
}
?>

<style>
/* 1. Responsifitas Link Referensi */
.links-container li a {
    display: inline-block;
    max-width: 100%;
    /* Memastikan URL yang panjang akan wrap dan tidak menyebabkan horizontal scroll */
    word-wrap: break-word; 
    word-break: break-all;
    overflow-wrap: break-word;
    white-space: normal;
}

/* 2. Responsifitas Avatar Assignee Stack */
.avatar-stack-container {
    /* Wadah utama yang memungkinkan scrolling horizontal */
    overflow-x: auto; 
    white-space: nowrap; /* Memastikan semua avatar tetap dalam satu baris */
    padding-bottom: 5px; /* Memberi ruang untuk scrollbar */
}
.avatar-stack {
    display: flex;
    align-items: center;
}
.avatar-stack .avatar-item {
    width: 40px; 
    height: 40px;
    object-fit: cover;
    margin-right: 0; /* Hilangkan margin-right yang menyebabkan space berlebihan */
    margin-left: -8px; /* Tumpukan avatar */
    flex-shrink: 0; /* Penting: mencegah gambar menyusut */
}
.avatar-stack .avatar-item:first-child {
    margin-left: 0px !important; 
}

/* 3. Footer Custom */
#uni_modal .modal-footer {
    display: none; /* Hide default modal footer */
}
.modal-footer.display {
    display: flex !important;
    flex-wrap: wrap; 
    justify-content: flex-end;
    align-items: center;
    padding: 1rem;
    border-top: 1px solid #dee2e6;
}

/* 4. Konten Deskripsi/Pillar */
.description-content, .pillar-container {
    word-wrap: break-word; 
    word-break: break-word;
    overflow-wrap: break-word;
    white-space: normal;
}
</style>