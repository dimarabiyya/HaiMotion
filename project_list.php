<?php 
// Asumsi 'db_connect.php' sudah ada dan berisi koneksi database ($conn)
include 'db_connect.php'; 

// --- FUNGSI ENCODE_ID SIMULASI ---
// Jika fungsi ini belum didefinisikan di tempat lain, Anda bisa menggunakan ini sementara.
if (!function_exists('encode_id')) {
    function encode_id($id) {
        return base64_encode($id);
    }
}
// Tambahkan Font Awesome versi terbaru (seperti yang Anda gunakan di code terakhir)
?>

<head>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap4.min.css">

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> 
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<div class="col-lg-12">
    <div class="pb-3">
        <div class="row">
            <div class="col-md-6">
                <h3 class="m-0">Project Progress</h3>
            </div>

            <div class="col-md-6">
                  <div class="d-flex justify-content-end">
                      <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] != 3): ?>
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

    <!-- Search and Sort Section -->
    <div class="mb-3">
        <div class="row align-items-center">
            <!-- Filter dan Sort di KIRI -->
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <div class="d-flex flex-wrap justify-content-center justify-content-md-start">
                    <div class="dropdown mr-2 mb-2 mb-md-0">
                        <button class="btn dropdown-toggle text-white" 
                                type="button" 
                                id="statusFilterDropdown"
                                data-toggle="dropdown" 
                                aria-expanded="false" 
                                style="background-color:#B75301;">
                            <i class="fa fa-filter mr-1"></i> <span id="statusFilterLabel">All Status</span>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="statusFilterDropdown">
                            <a class="dropdown-item status-filter" href="#" data-status="-1">All Status</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item status-filter" href="#" data-status="0">Pending</a>
                            <a class="dropdown-item status-filter" href="#" data-status="1">Started</a>
                            <a class="dropdown-item status-filter" href="#" data-status="2">On-Progress</a>
                            <a class="dropdown-item status-filter" href="#" data-status="3">On-Hold</a>
                            <a class="dropdown-item status-filter" href="#" data-status="4">Over Due</a>
                            <a class="dropdown-item status-filter" href="#" data-status="5">Done</a>
                        </div>
                    </div>

                    <div class="dropdown">
                        <button class="btn dropdown-toggle text-white" 
                                type="button" 
                                id="sortDropdown"
                                data-toggle="dropdown" 
                                aria-expanded="false" 
                                style="background-color:#B75301;">
                            <i class="fa fa-sort mr-1"></i> <span id="sortLabel">Sort: Name (A-Z)</span>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="sortDropdown">
                            <a class="dropdown-item sort-option" href="#" data-sort="name-asc">Name (A-Z)</a>
                            <a class="dropdown-item sort-option" href="#" data-sort="name-desc">Name (Z-A)</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search di KANAN -->
            <div class="col-12 col-md-6">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           id="searchProject" 
                           placeholder="Search by project name or assignee...">
                    <div class="input-group-append">
                        <span class="input-group-text" style="background-color:#B75301; color:white; border:none;">
                            <i class="fa fa-search"></i>
                        </span>
                    </div>
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
    
    // =========================================================
    // ✅ LOGIKA FILTER PROJECT BERDASARKAN ROLE (Ditambah issets check)
    // =========================================================
    if(isset($_SESSION['login_type']) && isset($_SESSION['login_id'])) {
        $login_id = $_SESSION['login_id']; 
        
        if($_SESSION['login_type'] == 2){
          // Manager (Role 2): Proyek yang ia kelola ATAU ia menjadi anggota
          $where = " WHERE manager_id = '{$login_id}' OR FIND_IN_SET('{$login_id}', user_ids) ";
        } elseif($_SESSION['login_type'] == 3){
          // User (Role 3): Proyek yang ia menjadi anggota
          $where = " WHERE FIND_IN_SET('{$login_id}', user_ids) ";
        } else {
            // Admin (Role 1) atau lainnya: Tampilkan semua
            $where = "";
        }
    } else {
        // Jika sesi login_type atau login_id tidak ada, tidak tampilkan apa-apa.
        $where = " WHERE 1 = 0 "; 
    }

    $qry = $conn->query("SELECT * FROM project_list $where ORDER BY name ASC");
    if($qry === false) {
         // Error pada Query Utama
         echo "<tr><td colspan='5'><p class='text-center text-danger'>SQL Error pada Query Utama: " . htmlspecialchars($conn->error) . "</p></td></tr>";
    } elseif($qry->num_rows > 0) {
        
        while($row= $qry->fetch_assoc()) {
      
          $prog= 0;
          
          // Sub-Query 1: Total Tasks (dengan error checking yang robust)
          $tprog_qry = $conn->query("SELECT id FROM task_list where project_id = {$row['id']}");
          $tprog = ($tprog_qry === false) ? 0 : $tprog_qry->num_rows;
          
          // Sub-Query 2: Completed Tasks (dengan error checking yang robust)
          $cprog_qry = $conn->query("SELECT id FROM task_list where project_id = {$row['id']} and status = 5");
          $cprog = ($cprog_qry === false) ? 0 : $cprog_qry->num_rows;
          
          $prog = $tprog > 0 ? ($cprog/$tprog) * 100 : 0;
          $prog = $prog > 0 ?  number_format($prog,2) : $prog;
          
          // Sub-Query 3: User Productivity Count (dengan error checking yang robust)
          $prod_qry = $conn->query("SELECT id FROM user_productivity where project_id = {$row['id']}");
          $prod = ($prod_qry === false) ? 0 : $prod_qry->num_rows;

          // === Cek deadline untuk status Over Due ===
          $today = strtotime(date('Y-m-d'));
          $end   = strtotime($row['end_date']);

          if ($row['status'] < 5 && $today > $end) { 
              if ($row['status'] != 0 && $row['status'] != 3) {
                  $row['status'] = 4; // Over Due
              }
          }

          // === Persiapan data user untuk Assignment UI ===
          $uids = !empty($row['user_ids']) ? explode(",", $row['user_ids']) : [];
          $total_users = count($uids);
          $max_show = 5; 
          $users_to_show = array_slice($uids, 0, $max_show);
          $more_count = $total_users - $max_show;
          
          $assigned_users = [];
          if(!empty($uids)){
            $valid_uids = array_filter($uids, 'is_numeric');
            if (!empty($valid_uids)) {
                $users_qry = $conn->query("SELECT id, firstname, lastname, avatar 
                                           FROM users 
                                           WHERE id IN (".implode(",", $valid_uids).")");
                while($u = $users_qry->fetch_assoc()){
                  $assigned_users[$u['id']] = $u; 
                }
            }
          }
    ?>

    <tr class="project-row" 
        data-id="<?php echo $row['id'] ?>" 
        data-encoded-id="<?= encode_id($row['id']) ?>" 
        data-name="<?= htmlspecialchars(ucwords($row['name'])) ?>"
        data-status="<?= $row['status'] ?>"
        data-end-date="<?= strtotime($row['end_date']) ?>"
        data-progress="<?= $prog ?>"
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

        <td class="project-assignment text-left" 
            data-assignees="<?= !empty($assigned_users) ? htmlspecialchars(implode(',', array_map(function($u){ return ucwords($u['firstname'].' '.$u['lastname']); }, $assigned_users)), ENT_QUOTES, 'UTF-8') : '' ?>">
            <?php if(!empty($assigned_users)): 
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

              if ($more_count > 0):
            ?>
                <button type="button" 
                        class="rounded-circle border border-secondary view_all_users"
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
                    <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] != 3): ?>
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

    <?php $i++; } // Penutup while ?>
    <?php } else { // Menutup elseif dan membuka else ?>
        <tr>
            <td colspan="5">
                <p class="text-center">Tidak ada proyek yang sesuai dengan role Anda.</p>
            </td>
        </tr>
    <?php } // Penutup else ?>
    
                    </tbody>  
                </table>
            </div>
        </div>
    </div>
    </div>

<div class="modal fade" id="uni_modal" role='dialog' aria-labelledby="uni_modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uni_modalLabel"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
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


<style>
/* ================================================= */
/* 2. REVISI: Ukuran Modal-XL dikecilkan (sesuai permintaan) */
/* ================================================= */
.modal-xl {
    max-width: 80% !important; /* Dikecilkan dari 90% */
    width: 100% !important;
}

/* ================================================= */
/* 2. REVISI: CUSTOM CSS SELECT2 (Warna Biru) */
/* ================================================= */
/* Warna biru untuk garis border dan fokus */
.select2-container--bootstrap4 .select2-selection--multiple:focus,
.select2-container--bootstrap4.select2-container--focus .select2-selection--multiple {
    border-color: #007bff !important; /* Biru Bootstrap Primary */
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
}
/* Warna biru untuk badge yang dipilih */
.select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
    background-color: #007bff !important; /* Biru Bootstrap Primary */
    border-color: #007bff !important;
    color: white !important;
}
/* Warna X (tombol hapus) pada badge */
.select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
    color: rgba(255, 255, 255, 0.7) !important; 
}


/* CSS LAIN */
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
function alert_toast(message, type = 'success') {
    console.log("Notifikasi:", message, type);
    alert(`[${type.toUpperCase()}] ${message}`); 
}

function uni_modal(title, url, size = 'lg') { 
    var $modal = $('#uni_modal');
    
    $modal.find('.modal-dialog')
        .removeClass('modal-sm modal-md modal-lg modal-xl')
        .addClass('modal-' + size); 

    $modal.find('.modal-header').css('background-color', '#B75301').css('color', 'white');
    $modal.find('.modal-title').html(title).css('color', 'white'); 
    $modal.find('.close').css('color', 'white');

    $modal.find('.modal-body').html('Loading...');
    
    $.ajax({
        url: url,
        success: function(resp){
            if(resp){
                $modal.find('.modal-body').html(resp);
                
                if ($.fn.summernote) {
                    $modal.find('.summernote').summernote({
                        height: 200,
                    });
                }

                if ($.fn.select2) {
                    $modal.find('.select2').select2({
                        placeholder: "Select an Option",
                        width: '100%',
                        theme: 'bootstrap4', 
                        dropdownParent: $modal 
                    });
                }
                
                $modal.modal('show');
            }
        }
    });
}

$(document).ready(function(){

    $('#uni_modal').on('hidden.bs.modal', function (e) {
        if ($.fn.summernote) {
            $(this).find('.summernote').summernote('destroy');
        }
        
        if ($.fn.select2) {
            $(this).find('.select2-hidden-accessible').each(function() {
                $(this).select2('destroy'); 
            });
            $(this).find('.select2-container').remove(); 
        }
        $(this).find('.modal-body').html(''); 
    });

    
    $(document).on('click', '.project-row', function(e){
        if ($(e.target).closest('.dropdown, .dropdown-toggle, .dropdown-menu, .view_all_users').length === 0) {
            var encoded_pid = $(this).data('encoded-id'); 
            window.location.href = "index.php?page=view_project&id=" + encoded_pid;
        }
    });
    
    $('#new_project_btn').click(function(){
        window.location.href = "index.php?page=new_project"; 
    });

    function delete_project(id){
        $.ajax({
            url: 'ajax.php?action=delete_project',
            method: 'POST',
            data: { id },
            success: function(resp){
                if(resp.trim() == 1){
                    alert_toast("Project berhasil dihapus", "success");
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert_toast("Gagal menghapus project", "danger");
                }
            }
        });
    }

    $(document).on('click', '.delete_project_trigger', function(e){
        e.stopPropagation();
        var id = $(this).data('id'); 
        var name = $(this).data('name');

        $('#confirmDeleteProjectBtn').data('id', id);
        $('#projectToDeleteName').text(name);
    });

    $(document).on('click', '#confirmDeleteProjectBtn', function(){
        var id = $(this).data('id');
        $('#deleteProjectModal').modal('hide'); 
        delete_project(id); 
    });


    $(document).on('click', '.view_all_users', function(e){
        e.stopPropagation();
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

    $('.dropdown-toggle').dropdown({ display: 'static' });

    // ============================================
    // FITUR SEARCH PROJECT
    // ============================================
    $('#searchProject').on('keyup', function() {
        applyFilters();
    });

    // ============================================
    // FITUR FILTER BY STATUS
    // ============================================
    var currentStatusFilter = -1; // -1 = All Status
    
    $('.status-filter').on('click', function(e) {
        e.preventDefault();
        currentStatusFilter = parseInt($(this).data('status'));
        
        // Update label
        $('#statusFilterLabel').text($(this).text());
        
        // Apply filter
        applyFilters();
    });

    // ============================================
    // FITUR SORT PROJECT
    // ============================================
    var currentSort = 'name-asc';
    
    $('.sort-option').on('click', function(e) {
        e.preventDefault();
        currentSort = $(this).data('sort');
        
        // Update label
        $('#sortLabel').text('Sort: ' + $(this).text());
        
        // Sort projects
        sortProjects(currentSort);
    });

    function applyFilters() {
        var searchValue = $('#searchProject').val().toLowerCase();
        
        $('.project-row').each(function() {
            var projectStatus = parseInt($(this).data('status'));
            var shouldShow = true;
            
            // Filter by status
            if (currentStatusFilter !== -1 && projectStatus !== currentStatusFilter) {
                shouldShow = false;
            }
            
            // Apply search filter
            if (searchValue !== '') {
                var projectName = $(this).data('name').toLowerCase();
                var assignees = $(this).find('.project-assignment').data('assignees');
                var assigneesStr = assignees ? assignees.toLowerCase() : '';
                
                if (projectName.indexOf(searchValue) === -1 && assigneesStr.indexOf(searchValue) === -1) {
                    shouldShow = false;
                }
            }
            
            if (shouldShow) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Check if no results
        var visibleRows = $('.project-row:visible').length;
        if (visibleRows === 0 && !$('#noResultsRow').length) {
            $('tbody').append('<tr id="noResultsRow"><td colspan="5" class="text-center text-muted">No projects found</td></tr>');
        } else if (visibleRows > 0) {
            $('#noResultsRow').remove();
        }
    }

    function sortProjects(sortType) {
        var $tbody = $('tbody');
        var $rows = $('.project-row').toArray();
        
        $rows.sort(function(a, b) {
            var aVal, bVal;
            
            switch(sortType) {
                case 'name-asc':
                    aVal = $(a).data('name').toLowerCase();
                    bVal = $(b).data('name').toLowerCase();
                    return aVal.localeCompare(bVal);
                    
                case 'name-desc':
                    aVal = $(a).data('name').toLowerCase();
                    bVal = $(b).data('name').toLowerCase();
                    return bVal.localeCompare(aVal);
                    
                case 'date-asc':
                    aVal = parseInt($(a).data('end-date'));
                    bVal = parseInt($(b).data('end-date'));
                    return aVal - bVal;
                    
                case 'date-desc':
                    aVal = parseInt($(a).data('end-date'));
                    bVal = parseInt($(b).data('end-date'));
                    return bVal - aVal;
                    
                case 'progress-asc':
                    aVal = parseFloat($(a).data('progress'));
                    bVal = parseFloat($(b).data('progress'));
                    return aVal - bVal;
                    
                case 'progress-desc':
                    aVal = parseFloat($(a).data('progress'));
                    bVal = parseFloat($(b).data('progress'));
                    return bVal - aVal;
                    
                default:
                    return 0;
            }
        });
        
        // Reorder rows in table
        $.each($rows, function(index, row) {
            $tbody.append(row);
        });
    }
});
</script>