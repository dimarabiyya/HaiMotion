<?php 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php'; 

$selected_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0; 
$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];
?>

<div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog" aria-labelledby="addTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form action="" id="add-task-form">
        <div class="modal-header" style="color:#B75301;">
          <h5 class="modal-title" id="addTaskModalLabel"><i class="fa fa-plus"></i> Add New Task</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="form-group">
            <label for="project_id"><b>Project</b></label>
            <select name="project_id" id="project_id" class="form-control" required>
              <option value="">Select Project</option>
              <?php
              $project_where = " WHERE 1=1 ";
              if ($login_type == 2) {
                  // **PERUBAHAN DI SINI**: Manager sees projects they manage OR projects they are assigned to
                  // Menggunakan FIND_IN_SET untuk mengecek user_ids
                  $project_where .= " AND (manager_id = '$user_id' OR FIND_IN_SET('$user_id', user_ids)) ";
              } elseif ($login_type == 3) {
                  $project_where .= " AND FIND_IN_SET('$user_id', user_ids) ";
              }

              $projects = $conn->query("
                SELECT id, name 
                FROM project_list p
                $project_where
                ORDER BY name ASC
              ");

              while($row = $projects->fetch_assoc()):
                $selected = ($row['id'] == $selected_project_id) ? 'selected' : '';
              ?>
              <option value="<?= $row['id'] ?>" <?= $selected ?>>
                  <?= ucwords($row['name']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="task"><b>Task</b></label>
            <input type="text" name="task" id="task" class="form-control" required>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="start_date"><b>Start Date</b></label>
              <input type="date" name="start_date" id="start_date" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label for="end_date"><b>End Date</b></label>
              <input type="date" name="end_date" id="end_date" class="form-control" required>
            </div>
          </div>

          <div class="form-group">
            <label for="description"><b>Description</b></label>
            <textarea name="description" id="description" class="summernote form-control"></textarea>
          </div>

          <div class="form-group">
            <label><b>Content Pillar</b></label><br>
            <?php
            $pillars = ['Edukasi', 'Tips', 'Behind The Scene', 'Testimoni', 'Portofolio', 'Awareness', 'Engagement', 'Promo', 'Lainnya'];
            foreach($pillars as $pillar):
            ?>
            <div class="form-check form-check-inline mb-1">
              <input class="form-check-input" type="checkbox" name="content_pillar[]" value="<?= $pillar ?>">
              <label class="form-check-label"><?= $pillar ?></label>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="form-group">
            <label><b>Platform</b></label><br>
            <?php
            $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Twitter', 'LinkedIn', 'Website', 'Lainnya'];
            foreach($platforms as $plat):
            ?>
            <div class="form-check form-check-inline mb-1">
              <input class="form-check-input" type="checkbox" name="platform[]" value="<?= $plat ?>">
              <label class="form-check-label"><?= $plat ?></label>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="form-group">
            <label><b>Refrence</b></label>
            <textarea name="reference_links" id="reference_links" class="form-control" rows="3" placeholder="Pisahkan link dengan enter."></textarea>
            <small class="text-muted">Pisahkan link dengan enter.</small>
          </div>

          <div class="form-group">
            <label><b>Status</b></label>
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
            <label><b>Assign To</b></label>
            <select name="user_ids[]" id="user_ids" class="form-control select2" multiple="multiple" style="width:100%;" required>
              <option value=""> Select project first </option>
            </select>
          </div>

        </div>
        
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn text-white" style="background-color:#B75301;">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
    // Inisialisasi plugin/editor
    $('.summernote').summernote({ height: 200 });
    // Select2 fix agar dropdown muncul di atas modal
    $('.select2').select2({
        placeholder: "Select Employee",
        width: '100%',
        dropdownParent: $('#addTaskModal') // penting biar dropdown muncul di dalam modal
    });

    // Auto-load user list jika project aktif saat modal dibuka
    if($('#project_id').val()){
        $('#project_id').trigger('change');
    }

    // Event handler saat project berubah
    $('#project_id').change(function(){
    var pid = $(this).val();
    var userSelect = $('#user_ids');
    
    userSelect.html('<option value="">Loading users...</option>');
    userSelect.prop('disabled', true);

    if(pid){
        $.ajax({
            url: 'ajax.php?action=get_project_users',
            method: 'POST',
            data: {pid: pid},
            success:function(resp){
                userSelect.prop('disabled', false);
                userSelect.html(resp);
                userSelect.trigger('change');
            },
            error: function(){
                userSelect.html('<option value="">Failed to load users</option>');
                userSelect.prop('disabled', false);
            }
        });
      } else {
          userSelect.html('<option value="">-- Select project first --</option>');
          userSelect.prop('disabled', true);
      }
    });


    // Submit form menggunakan AJAX
    $('#add-task-form').off('submit').submit(function(e){
        e.preventDefault();
        // Cek apakah fungsi start_load/end_load ada (jika digunakan untuk loading screen)
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