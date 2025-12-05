<?php 
if(!isset($conn)){ 
    include 'db_connect.php'; 
} 
// Memulai session jika belum dimulai (asumsi login_type diatur di session)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Logika untuk mengisi variabel $id, $name, $status, dll. (biasanya di sini jika ini edit)
?>
<form action="" id="manage-project">
    <input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="" class="control-label"><b>Name</b></label>
                <input type="text" class="form-control form-control-sm" name="name" value="<?php echo isset($name) ? $name : '' ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for=""><b>Status</b></label>
                <select name="status" id="status" class="custom-select custom-select-sm">
                    <option value="0" <?php echo isset($status) && $status == 0 ? 'selected' : '' ?>>Pending</option>
                    <option value="1" <?php echo isset($status) && $status == 1 ? 'selected' : '' ?>>Started</option>
                    <option value="2" <?php echo isset($status) && $status == 2 ? 'selected' : '' ?>>On-Progress</option>
                    <option value="3" <?php echo isset($status) && $status == 3 ? 'selected' : '' ?>>On-Hold</option>
                    <option value="4" <?php echo isset($status) && $status == 4 ? 'selected' : '' ?>>Overdue</option>
                    <option value="5" <?php echo isset($status) && $status == 5 ? 'selected' : '' ?>>Done</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
        <div class="form-group">
          <label for="" class="control-label"><b>Start Date</b></label>
          <input type="date" class="form-control form-control-sm" autocomplete="off" name="start_date" value="<?php echo isset($start_date) ? date("Y-m-d",strtotime($start_date)) : '' ?>">
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label for="" class="control-label"><b>End Date</b></label>
          <input type="date" class="form-control form-control-sm" autocomplete="off" name="end_date" value="<?php echo isset($end_date) ? date("Y-m-d",strtotime($end_date)) : '' ?>">
        </div>
      </div>
    </div>
    
    <div class="row">
        <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] == 1 ): ?>
       <div class="col-md-6">
        <div class="form-group">
          <label for="" class="control-label"><b>Project Manager</b></label>
          <select class="form-control form-control-sm select2" name="manager_id">
            <option></option>
            <?php 
            $managers = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where type IN (1, 2) order by concat(firstname,' ',lastname) asc ");
            while($row= $managers->fetch_assoc()):
                $role_label = $row['type'] == 1 ? ' (Admin)' : ' (Manager)';
            ?>
            <option value="<?php echo $row['id'] ?>" <?php echo isset($manager_id) && $manager_id == $row['id'] ? "selected" : '' ?>><?php echo ucwords($row['name']) . $role_label ?></option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>
    <?php else: ?>
    <input type="hidden" name="manager_id" value="<?php echo isset($_SESSION['login_id']) ? $_SESSION['login_id'] : '' ?>">
  <?php endif; ?>
      <div class="col-md-6">
        <div class="form-group">
          <label for="" class="control-label"><b>Project Team Members</b></label>
          <select class="form-control form-control-sm select2" multiple="multiple" name="user_ids[]">
            <option></option>
            <?php 
            $employees = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where type IN (1, 2, 3) order by concat(firstname,' ',lastname) asc ");
            while($row= $employees->fetch_assoc()):
                if ($row['type'] == 1) {
                    $role_label = ' (Admin)';
                } elseif ($row['type'] == 2) {
                    $role_label = ' (Manager)';
                } else {
                    $role_label = ' (Member)'; 
                }
                // === BARIS PHP YANG HILANG ===
            ?>
            <option value="<?php echo $row['id'] ?>" <?php echo isset($user_ids) && in_array($row['id'],explode(',',$user_ids)) ? "selected" : '' ?>><?php echo ucwords($row['name']) . $role_label ?></option>
            <?php endwhile; // === AKHIR DARI LOOP PHP YANG HILANG === ?>
          </select>
        </div>
      </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label for="" class="control-label"><b>Description</b></label>
                <textarea name="description" id="description_summernote" cols="30" rows="10" class="summernote form-control">
                    <?php echo isset($description) ? htmlspecialchars($description) : '' ?>
                </textarea>
            </div>
        </div>
    </div>
    
    <div class="border-info pt-3">
        <div class="d-flex w-100 justify-content-end align-items-center">
            <button class="btn btn-secondary mx-2" type="button" data-dismiss="modal">Close</button>
            <button class="btn text-white" style="background-color:#B75301;" form="manage-project">Save</button>
        </div>
    </div>
</form>

<script>
    // === PERBAIKAN BUG SUMMERNOTE/SELECT2 DI MODAL ===
    // Mengatasi masalah fokus Summernote/Select2 di Bootstrap Modal
    $(document).on('focusin', function(e) {
        if ($(e.target).closest(".note-editor, .select2-container").length) {
            e.stopImmediatePropagation();
        }
    });

    $(document).ready(function(){
        
        // Pastikan select2 diinisialisasi, dan menggunakan parent modal
        // PENTING: dropdownParent: $('#uni_modal') mengatasi z-index Select2
        $('.select2').select2({
            placeholder: "Select here",
            width: '100%',
            dropdownParent: $('#uni_modal')
        });
        
        // Pastikan summernote diinisialisasi
        $('#description_summernote').summernote({
            height: 150,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['view', ['codeview', 'help']]
            ]
        });

        $('#manage-project').submit(function(e){
            e.preventDefault()
            // start_load() 
            $.ajax({
                url:'ajax.php?action=save_project',
                data: new FormData($(this)[0]),
                cache: false,
                contentType: false,
                processData: false,
                method: 'POST',
                type: 'POST',
                success:function(resp){
                    if(resp == 1){
                        // 🎯 REVISI: Panggil alert_toast DAN tutup modal
                        $('#uni_modal').modal('hide'); // Tutup modal
                        alert_toast('Project successfully saved',"success"); // Tampilkan notifikasi
                        
                        setTimeout(function(){
                            location.reload()
                        },1500)
                    } else {
                        alert('Error: ' + resp);
                    }
                }
            })
        })
    });
</script>