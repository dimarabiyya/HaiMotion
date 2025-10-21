<?php
include 'db_connect.php';
session_start();

if (!isset($_REQUEST['id'])) { // Diubah dari $_POST ke $_REQUEST untuk kompatibilitas uni_modal (GET)
    echo "ID tidak ditemukan.";
    exit;
}

// Update status overdue sebelum fetch
$conn->query("
    UPDATE task_list 
    SET status = 4 
    WHERE end_date < NOW() 
    AND status NOT IN (0,3,5)
");


$id = intval($_REQUEST['id']); // Diubah dari $_POST ke $_REQUEST
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

  <!-- Header: Task Title + Buttons -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="font-weight-bold mb-1"><?= htmlspecialchars($row['task']) ?></h4>
      <span class="badge badge-<?= $status[1] ?> px-3 py-2" style="font-size:13px;">
        <?= $status[0] ?>
      </span>
    </div>
  </div>

  <hr>

  <!-- Project -->
  <div class="mb-3">
    <h6 class="text-muted mb-1">Project</h6>
    <h5 class="font-weight-bold mb-0"><?= htmlspecialchars($project_name) ?></h5>
  </div>

  <!-- Creator & Assignment -->
  <div class="row mb-4">
    <!-- Created By -->
    <div class="col-md-6 mb-3">
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

    <!-- Assignment User -->
    <div class="col-md-6 mb-3">
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
      <div class="d-flex align-items-center flex-wrap mt-2">
        <?php foreach ($task_assigned_users as $au): ?>
          <img 
            src="assets/uploads/<?= !empty($au['avatar']) ? htmlspecialchars($au['avatar']) : 'default.png'; ?>" 
            alt="<?= ucwords($au['firstname'].' '.$au['lastname']); ?>" 
            class="rounded-circle border border-secondary" 
            style="width:40px; height:40px; object-fit:cover; margin-right:-8px;" 
            title="<?= ucwords($au['firstname'].' '.$au['lastname']); ?>">
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="text-muted mb-0">No User</p>
      <?php endif; ?>
    </div>

    <!-- Start & End Date -->
    <div class="col-md-6 mb-3">
      <h6 class="text-muted mb-1">Start Date</h6>
      <p class="mb-0"><?= date('F d, Y', strtotime($row['start_date'])) ?></p>
    </div>
    <div class="col-md-6 mb-3">
      <h6 class="text-muted mb-1">End Date</h6>
      <p class="mb-0"><?= date('F d, Y', strtotime($row['end_date'])) ?></p>
    </div>
  </div>

  <!-- Description -->
  <div class="mb-3">
    <h6 class="text-muted">Description</h6>
    <div class="p-2 bg-light rounded border">
      <?= html_entity_decode($row['description']) ?>
    </div>
  </div>

  <!-- Content Pillar -->
  <div class="mb-3">
    <h6 class="text-muted">Content Pillar</h6>
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

  <!-- Platform -->
  <div class="mb-3">
    <h6 class="text-muted">Platform</h6>
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

  <!-- Reference Links -->
  <div class="mb-3">
    <h6 class="text-muted">Reference Links</h6>
    <ul class="pl-3 reference-links">
      <?php 
      $links = array_filter(array_map('trim', explode("\n", $row['reference_links'])));
      if (!empty($links)) {
          foreach ($links as $link) {
              $safe_link = htmlspecialchars($link);
              echo "<p><a href='{$safe_link}' target='_blank'>{$safe_link}</a></p>";
          }
      } else {
          echo "<p class='text-muted'>No links</p>";
      }
      ?>
    </ul>
  </div>
</div>

<div class="modal-footer display p-0 m-0">
    <button class="btn btn-primary mr-2" onclick="editTask(<?= $id ?>, <?= $project_id ?>)">
      <i class="fa fa-edit"></i> Edit Task
    </button>
    <button type="button" class="btn btn-danger mr-auto" onclick="confirmDelete(<?= $id ?>)">
      <i class="fa fa-trash"></i> Delete
    </button>
    </button>
  <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
</div>

    
<script>
    function editTask(id, pid) {
      // Sembunyikan modal detail task saat ini
      $('#uni_modal').modal('hide');
      
      // Beri sedikit jeda lalu buka modal edit task (manage_task.php)
      setTimeout(function(){
          uni_modal("<i class='fa fa-edit'></i> Edit Task",
              "manage_task.php?id=" + id + "&pid=" + pid,
              "modal-xl");
      }, 300); // 300ms delay
    }
    
    function confirmDelete(id) {
      // Tutup modal utama dulu
      $('#uni_modal').modal('hide');

      // Tunggu sampai animasi modal selesai baru panggil konfirmasi
      setTimeout(() => {
        _conf('Are you sure to delete this task?', 'delete_task', [id]);
      }, 400);
    }

    // Sembunyikan footer default dan tampilkan footer kustom
    $('#uni_modal .modal-footer').hide(); 
    $('.modal-footer.display').show();
    $('#uni_modal .modal-dialog').removeClass('modal-md').addClass("modal-lg");
</script>
    
    <?php
} else {
    echo "Data task tidak ditemukan.";
}
?>

<style>
.reference-links a {
    display: inline-block;
    max-width: 100%;
    word-wrap: break-word;
    word-break: break-all;
    overflow-wrap: break-word;
    white-space: normal;
}
#uni_modal .modal-footer {
    display: none; /* Hide default modal footer */
}
.modal-footer.display {
    display: flex !important;
    justify-content: flex-end;
    align-items: center;
    padding: 1rem;
    border-top: 1px solid #dee2e6;
}
</style>