<?php 
// Pastikan db_connect.php sudah di-include di file utama yang memanggil ini.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php'; 

// 1. DEFINE $selected_project_id.
// Jika file ini dipanggil dari task_list.php, mungkin ada $_GET['project_id'].
$selected_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0; 
// Jika tidak ada di GET, default ke 0 (tidak ada yang dipilih)

$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];
?>

<div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog" aria-labelledby="addTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form action="" id="add-task-form">
        <div class="modal-header text-white" style="background-color:#B75301;">
          <h5 class="modal-title" id="addTaskModalLabel"><i class="fa fa-plus"></i> Add New Task</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="form-group">
            <label for="project_id">Project</label>
            <select name="project_id" id="project_id" class="form-control" required>
              <option value="">Select Project</option>
              <?php
              // 2. TWEAK QUERY untuk Administrator (type 1)
              $project_where = " WHERE 1=1 ";
              if ($login_type == 2) {
                  // Manager: Proyek yang ia kelola
                  $project_where .= " AND manager_id = '$user_id' ";
              } elseif ($login_type == 3) {
                  // User: Proyek yang ia menjadi anggota
                  $project_where .= " AND FIND_IN_SET('$user_id', user_ids) ";
              }
              // Administrator (type 1) tidak diberi batasan, sehingga akan menggunakan WHERE 1=1

              $projects = $conn->query("
                SELECT id, name 
                FROM project_list p
                $project_where
                ORDER BY name ASC
              ");

              while($row = $projects->fetch_assoc()):
                // Tandai project yang sedang difilter di halaman utama
                $selected = ($row['id'] == $selected_project_id) ? 'selected' : '';
              ?>
              <option value="<?= $row['id'] ?>" <?= $selected ?>>
                  <?= ucwords($row['name']) ?>
              </option>

              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="task">Task</label>
            <input type="text" name="task" id="task" class="form-control" required>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="start_date">Start Date</label>
              <input type="date" name="start_date" id="start_date" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label for="end_date">End Date</label>
              <input type="date" name="end_date" id="end_date" class="form-control" required>
            </div>
          </div>

          <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="summernote form-control"></textarea>
          </div>

          <div class="form-group">
            <label>Status</label>
            <select name="status" id="status" class="form-control">
              <option value="0">Pending</option>
              <option value="1">Started</option>
              <option value="2">On-Progress</option>
              <option value="3">On-Hold</option>
              <option value="4">Over Due</option>
              <option value="5">Done</option>
            </select>
          </div>

          <div class="form-group">
            <label>Assign To</label>
            <select name="user_ids[]" id="user_ids" class="form-control select2" multiple="multiple" style="width:100%;" required>
              <option value="">-- Select project first --</option>
            </select>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn text-white" style="background-color:#B75301;">Save Task</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
    // Inisialisasi Summernote dan Select2
    $('.summernote').summernote({ height: 200 });
    $('.select2').select2({ placeholder: "Select Employee" });

    // Pemicu awal jika project_id sudah ada di URL saat modal dibuka
    // Ini penting agar daftar Assign To langsung terisi jika filter project aktif
    if($('#project_id').val()){
        $('#project_id').trigger('change');
    }

    // ⬇️ Kalau project dipilih, ambil user dari backend
    $('#project_id').change(function(){
        var pid = $(this).val();
        var userSelect = $('#user_ids');
        
        // Kosongkan dan beri pesan loading
        userSelect.html('<option value="">Loading users...</option>');
        userSelect.trigger('change');

        if(pid){
            $.ajax({
                // Ini memanggil ajax.php?action=get_project_users
                url: 'ajax.php?action=get_project_users',
                method: 'POST',
                data: {pid: pid},
                success:function(resp){
                    userSelect.html(resp); // isi option user
                    userSelect.trigger('change'); // refresh select2
                }
            });
        }else{
            userSelect.html('<option value="">-- Select project first --</option>');
            userSelect.trigger('change');
        }
    });

    // Submit form
    $('#add-task-form').submit(function(e){
        e.preventDefault();
        // Asumsi start_load() didefinisikan di global script
        if (typeof start_load !== 'undefined') { start_load(); } 

        $.ajax({
            url: 'ajax.php?action=save_task',
            method: 'POST',
            data: $(this).serialize(),
            success:function(resp){
                if(resp == 1){
                    alert_toast("Task successfully added", 'success');
                    $('#addTaskModal').modal('hide');
                    setTimeout(function(){ location.reload(); },1500);
                }else{
                    // Menampilkan pesan error dari backend
                    alert_toast("Error saving task: "+resp, 'danger');
                }
                if (typeof end_load !== 'undefined') { end_load(); }
            },
            error: function() {
                alert_toast("An unknown error occurred.", 'danger');
                if (typeof end_load !== 'undefined') { end_load(); }
            }
        })
    });
});
</script>