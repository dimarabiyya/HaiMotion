<?php include 'db_connect.php' ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap4.min.css">


<div class="col-lg-12">
    <div class="container-fluid pb-3">
        <div class="row">
            <div class="col-md-6">
                <div class="d-flex justify-content-start">
                    <h4>Project Progress</h4>
                </div>
            </div>

            <div class="col-md-6">
                  <div class="d-flex justify-content-end">
                      <?php if($_SESSION['login_type'] != 3): ?>
                          <div class="card-tools">
                            <button type="button" class="btn text-white" style="background-color:#B75301;" id="new_project_btn">
                              <i class="fa fa-plus mr-2"></i> Add Project
                            </button>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
        </div>
    </div>

    <div class="card card-outline">
        <div class="table-responsive">
            <div class="card-body">
                <table class="table table-hover table-condensed">
                    <colgroup>
                        <col width="30%"> <col width="15%"> <col width="35%"> <col width="15%"> <col width="5%">  </colgroup>
                    <thead>
                        <tr>
                            <th class="text-left">Project Title</th>
                            <th class="text-left">Status</th>
                            <th class="text-left">Progress</th>
                            <th class="text-left">Assignee</th>
                            <th class="text-left"> </th>
                        </tr>
                    </thead>
                    <tbody>
    
    <?php
    $i = 1;
    $stat = array("Pending","Started","On-Progress","On-Hold","Over Due","Done");
    $where = "";
    
    // LOGIKA FILTER PROJECT BERDASARKAN ROLE
    if($_SESSION['login_type'] == 2){
      // Manager: Proyek yang ia kelola ATAU ia menjadi anggota
      $where = " WHERE manager_id = '{$_SESSION['login_id']}' OR FIND_IN_SET('{$_SESSION['login_id']}', user_ids) ";
    } elseif($_SESSION['login_type'] == 3){
      // User: Proyek yang ia menjadi anggota
      $where = " WHERE FIND_IN_SET('{$_SESSION['login_id']}', user_ids) ";
    } else {
        // Admin (login_type = 1) atau lainnya, tidak ada filter spesifik
        $where = "";
    }

    $qry = $conn->query("SELECT * FROM project_list $where ORDER BY name ASC");
    
    if($qry->num_rows > 0):
    while($row= $qry->fetch_assoc()):
      $prog= 0;
      $tprog = $conn->query("SELECT * FROM task_list where project_id = {$row['id']}")->num_rows;
      $cprog = $conn->query("SELECT * FROM task_list where project_id = {$row['id']} and status = 5")->num_rows;
      $prog = $tprog > 0 ? ($cprog/$tprog) * 100 : 0;
      $prog = $prog > 0 ?  number_format($prog,2) : $prog;
      $prod = $conn->query("SELECT * FROM user_productivity where project_id = {$row['id']}")->num_rows;

      // === Cek deadline untuk status Over Due ===
      $today = strtotime(date('Y-m-d'));
      $end   = strtotime($row['end_date']);

      if ($today > $end) {
          // Kalau sudah lewat deadline
          if ($row['status'] != 0 && $row['status'] != 3 && $row['status'] != 5) {
              // Hanya update ke Over Due jika BUKAN Pending, On-Hold, atau Done
              $row['status'] = 4;
          }
      }

      // === Persiapan data user untuk Assignment UI ===
      $uids = !empty($row['user_ids']) ? explode(",", $row['user_ids']) : [];
      $total_users = count($uids);
      $max_show = 5; // Maksimal avatar yang ditampilkan
      $users_to_show = array_slice($uids, 0, $max_show);
      $more_count = $total_users - $max_show;
      
      $assigned_users = [];
      if(!empty($uids)){
        $users_qry = $conn->query("SELECT id, firstname, lastname, avatar 
                                   FROM users 
                                   WHERE id IN (".implode(",", $uids).")");
        while($u = $users_qry->fetch_assoc()){
          // Simpan semua user di array untuk JSON encoding
          $assigned_users[$u['id']] = $u; 
        }
      }
?>

<tr class="project-row" 
    data-id="<?php echo $row['id'] ?>" 
    data-encoded-id="<?= encode_id($row['id']) ?>" 
    style="cursor:pointer;">

    <td class="text-left">
        <b><?php echo ucwords($row['name']) ?></b>
        <p class="text-muted"><small>Due: <?php echo date("Y-m-d",strtotime($row['end_date'])) ?></small></p>
    </td>

    <td class="project-state text-left">
        <?php
          $status = (int)$row['status'];
          $label = isset($stat[$status]) ? $stat[$status] : "Unknown";
          $badgeClass = [
            0=>'badge-secondary',
            1=>'badge-info',
            2=>'badge-primary',
            3=>'badge-warning',
            4=>'badge-danger',
            5=>'badge-success'
          ][$status] ?? 'badge-dark';
          echo "<span class='badge {$badgeClass} p-2'>{$label}</span>";
        ?>
    </td>

    <td class="project_progress text-left">
        <div class="progress progress-sm mb-1 progress-custom"> 
          <div class="progress-bar progress-bar-custom" role="progressbar" style="width: <?php echo $prog ?>%"></div>
        </div>
        <small><?php echo $prog ?>% Complete</small>
    </td>

    <td class="project-assignment text-left">
        <?php if(!empty($uids)): 
          echo '<div class="d-flex align-items-center text-nowrap">'; 
          $displayed_count = 0;
          foreach($users_to_show as $uid):
            if(isset($assigned_users[$uid])):
              $u = $assigned_users[$uid];
              $avatar = !empty($u['avatar']) ? 'assets/uploads/'.$u['avatar'] : 'assets/uploads/default.png';
        ?>
            <img src="<?= $avatar ?>" 
                 class="rounded-circle border border-white" 
                 style="width:30px; height:30px; object-fit:cover; margin-left:-8px;" 
                 title="<?= ucwords($u['firstname'].' '.$u['lastname']) ?>">
        <?php 
              $displayed_count++;
            endif;
          endforeach; 

          // Tombol (+.. more)
          if ($more_count > 0):
        ?>
            <button type="button" 
                    class="btn btn-sm btn-info rounded-circle border border-white view_all_users"
                    data-id="<?= $row['id'] ?>"
                    data-users='<?= htmlspecialchars(json_encode(array_values($assigned_users)), ENT_QUOTES, 'UTF-8') ?>'
                    style="width:30px; height:30px; font-size:10px; padding:0; line-height:30px; margin-left:-8px;"
                    title="View all <?= $total_users ?> members"
                    data-toggle="modal" 
                    data-target="#usersModal">
              +<?= $more_count ?>
            </button>
        <?php
          endif;
          echo '</div>'; 
        else: ?>
          <span class="text-muted">No assignment</span>
        <?php endif; ?>
    </td>

    <td class="text-left">
        <div class="dropdown">
            <button class="btn text-secondary" type="button" data-toggle="dropdown">
                <i class="fa fa-ellipsis-v"></i>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="index.php?page=view_project&id=<?= encode_id($row['id']) ?>">
                  <i class="fa fa-eye mr-2"></i> View
                </a>
                <?php if($_SESSION['login_type'] != 3): ?>
                <a class="dropdown-item" href="index.php?page=edit_project&id=<?= encode_id($row['id']) ?>">
                  <i class="fa fa-solid fa-pen mr-2"></i> Edit
                </a>
                <a class="dropdown-item text-danger delete_project_trigger"
                  data-id="<?= $row['id'] ?>"
                  data-name="<?= ucwords($row['name']) ?>"
                  data-toggle="modal"
                  data-target="#deleteProjectModal">
                  <i class="fa fa-trash mr-2"></i> Delete
                </a>
                <?php endif; ?>
            </div>
        </div>
    </td> 
</tr>

    <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="5">
                <p class="text-center">Tidak ada proyek yang sesuai dengan role Anda.</p>
            </td>
        </tr>
    <?php endif; ?>
    
                    </tbody>  
                </table>
            </div>
        </div>
    </div>
    </div>

<div class="modal fade" id="deleteProjectModal" tabindex="-1" role="dialog" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProjectModalLabel">Confirm Deletion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the project: <b id="projectToDeleteName"></b>?
                This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteProjectBtn">Delete Project</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="usersModal" tabindex="-1" role="dialog" aria-labelledby="usersModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background-color:#B75301; color:white;">
        <h5 class="modal-title" id="usersModalLabel">All Assignee</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="usersModalBody">
        </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ================================================= */
/* CUSTOM CSS UNTUK MEMAKSA MODAL-XL JADI LEBIH LEBAR */
/* ================================================= */
.modal-xl {
    max-width: 150% !important; 
    width: 100% !important;
}

#uni_modal .modal-body {
    padding: 1.5rem; 
}

/* ================================================= */
/* (CSS lain yang sudah ada) */
/* ================================================= */

table p { margin: 0 !important; }
table td { vertical-align: middle !important; }

/* Menghapus margin-left-8px untuk elemen pertama di d-flex assignment */
.project-assignment .d-flex img:first-child,
.project-assignment .d-flex button:first-child {
    margin-left: 0px !important;
}

.table-responsive {
  overflow-x: auto; 
  -webkit-overflow-scrolling: touch;
}

.table-responsive table {
  min-width: 768px; 
}

.progress-custom {
    border-radius: 10px !important; 
    height: 10px; 
    overflow: hidden; 
}

.progress-bar-custom {
    background-color: #B75301 !important; 
    border-radius: 10px !important;
    height: 100%;
}
</style>

<script>
// PENTING: Anda harus memastikan fungsi encode_id() tersedia di global scope JS.

// FUNGSI UNI_MODAL DIREVISI
function uni_modal(title, url, size = 'xl') {
    var $modal = $('#uni_modal');
    
    // Hapus semua kelas ukuran modal yang mungkin ada, lalu tambahkan kelas yang diminta
    $modal.find('.modal-dialog')
        .removeClass('modal-sm modal-md modal-lg modal-xl')
        .addClass('modal-' + size); 

    // --- START: CUSTOM STYLE HEADER ---
    $modal.find('.modal-header').css('color', '#B75301').removeClass('bg-primary');
    $modal.find('.modal-title').html(title);
    $modal.find('.close').css('color', '#B75301 !important'); // Ganti warna tombol close
    // --- END: CUSTOM STYLE HEADER ---

    $modal.find('.modal-body').html('Loading...');
    
    $.ajax({
        url: url,
        success: function(resp){
            if(resp){
                $modal.find('.modal-body').html(resp);
                $modal.modal('show');
            }
        }
    });
}

$(document).ready(function(){
    
    // Klik card project → masuk ke view_project (Diubah menjadi klik row)
    $(document).on('click', '.project-row', function(e){
        // Mencegah aksi jika klik berasal dari dalam dropdown menu, tombol view_all_users, atau elemen interaktif lainnya
        if ($(e.target).closest('.dropdown, .dropdown-toggle, .dropdown-menu, .view_all_users').length === 0) {
            var encoded_pid = $(this).data('encoded-id'); 
            window.location.href = "index.php?page=view_project&id=" + encoded_pid;
        }
    });

    // Delete project logic
    function delete_project(id){
        // start_load() jika ada
        $.ajax({
            url: 'ajax.php?action=delete_project',
            method: 'POST',
            data: { id },
            success: function(resp){
                if(resp == 1){
                    // alert_toast("Project berhasil dihapus", "success");
                    alert("Project berhasil dihapus");
                    setTimeout(() => location.reload(), 1500);
                } else {
                    // alert_toast("Gagal menghapus project", "danger");
                    alert("Gagal menghapus project");
                }
            }
        });
    }

    // Trigger delete modal
    $(document).on('click', '.delete_project_trigger', function(e){
        e.preventDefault();
        var id = $(this).data('id'); 
        var name = $(this).data('name');

        $('#confirmDeleteProjectBtn').data('id', id);
        $('#projectToDeleteName').text(name);
    });

    // Confirm delete action
    $(document).on('click', '#confirmDeleteProjectBtn', function(){
        var id = $(this).data('id');
        $('#deleteProjectModal').modal('hide'); 
        delete_project(id); 
    });


    // =========================================================
    // LOGIKA MODAL ALL ASSIGNED USERS
    // =========================================================
    $(document).on('click', '.view_all_users', function(){
        const users = $(this).data('users'); 
        const modalBody = $('#usersModalBody');
        let htmlContent = '';

        if (Array.isArray(users) && users.length > 0) {
            $('#usersModalLabel').text(`All Assigned Members (${users.length})`);
            
            users.forEach(user => {
                const avatar = user.avatar ? `assets/uploads/${user.avatar}` : 'assets/uploads/default.png';
                const fullName = user.firstname + ' ' + user.lastname;

                htmlContent += `
                    <div class="d-flex align-items-center mb-2">
                        <img src="${avatar}" 
                             class="rounded-circle border border-secondary mr-3" 
                             style="width:45px; height:45px; object-fit:cover;" 
                             alt="${fullName}">
                        <b>${fullName}</b>
                    </div>
                `;
            });
        } else {
            $('#usersModalLabel').text(`All Assigned Members (0)`);
            htmlContent = '<p class="text-center text-muted">No members assigned to this project.</p>';
        }

        modalBody.html(htmlContent);
    });

    // Aksi klik tombol "Add Project"
    $('#new_project_btn').click(function(){
        // Mengubah ke "xl" dan menggunakan CSS kustom di atas untuk memaksa lebar
        uni_modal("<span style='color:#B75301;'><i class='fa fa-plus mr-1'></i> Add New Project", "new_project.php", "xl"); 
    });

    $('.dropdown-toggle').dropdown({ display: 'static' });
});
</script>