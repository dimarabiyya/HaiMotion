<?php 
session_start();
include 'db_connect.php';

if(isset($_GET['id'])){
    $qry = $conn->query("SELECT * FROM task_list where id = ".$_GET['id'])->fetch_array();
    foreach($qry as $k => $v){
        $$k = $v;
    }
}

// pastikan ada project_id dari task
$pid = isset($_GET['pid']) ? $_GET['pid'] : (isset($project_id) ? $project_id : 0);

$project_user_ids = [];
if($pid){
    $proj = $conn->query("SELECT user_ids, manager_id FROM project_list WHERE id = $pid");
    if($proj->num_rows > 0){
        $proj_data = $proj->fetch_assoc();
        
        // Tambahkan semua user_ids (anggota)
        if(!empty($proj_data['user_ids'])){
            $project_user_ids = array_merge($project_user_ids, explode(',', $proj_data['user_ids']));
        }
        
        // Tambahkan manager_id
        if(!empty($proj_data['manager_id'])){
            $project_user_ids[] = $proj_data['manager_id'];
        }
    }
}

// Jika ini adalah task yang sudah ada, pastikan semua user yang sudah di-assign juga masuk dalam daftar
$current_users = isset($user_ids) ? explode(',', $user_ids) : [];

// Gabungkan dan filter ID unik
$potential_user_ids_array = array_unique(array_filter(array_merge($project_user_ids, $current_users)));
$potential_user_ids_string = !empty($potential_user_ids_array) ? implode(',', $potential_user_ids_array) : '0';

// Ambil data user HANYA untuk ID yang terkait dengan proyek
$all_users_data = [];
$all_users_q = $conn->query("SELECT id, firstname, lastname, type FROM users WHERE id IN ($potential_user_ids_string) ORDER BY CONCAT(firstname, ' ', lastname) ASC");
while($row = $all_users_q->fetch_assoc()){
    $all_users_data[] = $row;
}
// =========================================================================
?>

<? include 'header.php'?>
<div class="container-fluid">
    <form action="" id="manage-task">
        <input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">
        <input type="hidden" name="project_id" value="<?php echo isset($_GET['pid']) ? $_GET['pid'] : '' ?>">
        <input type="hidden" name="created_by" value="<?php echo $_SESSION['login_id']; ?>"> 
        <div class="form-group">
            <label for="">Task</label>
            <input type="text" class="form-control form-control-sm" name="task" value="<?php echo isset($task) ? $task : '' ?>" required>
        </div>
        <div class="form-group">
            <label for="start_date">Start Date</label>
            <input type="date" id="start_date" name="start_date" class="form-control" required value="<?php echo isset($start_date) ? $start_date : '' ?>">
            </div>
            <div class="form-group">
            <label for="end_date">End Date</label>
            <input type="date" id="end_date" name="end_date" class="form-control" required value="<?php echo isset($end_date) ? $end_date : '' ?>">
        </div>
        <div class="form-group">
            <label for="">Description</label>
            <textarea name="description" id="" cols="30" rows="10" class="summernote form-control">
                <?php echo isset($description) ? $description : '' ?>
            </textarea>
        </div>
        <div class="form-group">
    <label><b>Content Pillar</b></label><br>
    <?php
    $pillars = ['Edukasi', 'Tips', 'Behind The Scene', 'Testimoni', 'Portofolio', 'Awareness', 'Engagement', 'Promo', 'Lainnya'];
    $selected_pillars = isset($content_pillar) ? explode(',', $content_pillar) : [];
    foreach($pillars as $pillar):
    ?>
    <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" name="content_pillar[]" value="<?= $pillar ?>" <?= in_array($pillar, $selected_pillars) ? 'checked' : '' ?>>
        <label class="form-check-label"><?= $pillar ?></label>
    </div>
    <?php endforeach; ?>
</div>

        <div class="form-group">
            <label><b>Platform</b></label><br>
            <?php
            $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Twitter', 'LinkedIn', 'Website', 'Lainnya'];
            $selected_platforms = isset($platform) ? explode(',', $platform) : [];
            foreach($platforms as $plat):
            ?>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="platform[]" value="<?= $plat ?>" <?= in_array($plat, $selected_platforms) ? 'checked' : '' ?>>
                <label class="form-check-label"><?= $plat ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="form-group">
            <label><b>Link Referensi</b></label>
            <textarea name="reference_links" class="form-control" rows="3"><?= isset($reference_links) ? $reference_links : '' ?></textarea>
            <small class="text-muted">Pisahkan link dengan enter.</small>
        </div>

        <div class="form-group">
            <label for="">Status</label>
            <select name="status" id="status" class="custom-select custom-select-sm">
                <option value="0" <?php echo isset($status) && $status == 0 ? 'selected' : '' ?>>Pending</option>
                <option value="2" <?php echo isset($status) && $status == 2 ? 'selected' : '' ?>>On-Progress</option>
                <option value="3" <?php echo isset($status) && $status == 3 ? 'selected' : '' ?>>Hold</option>
                <option value="5" <?php echo isset($status) && $status == 5 ? 'selected' : '' ?>>Done</option>
            </select>
        </div>

        <div class="form-group">
            <label for="" class="control-label">Assign To</label>
            <select class="form-control form-control-sm select2" multiple="multiple" name="user_ids[]">
                <option></option>
                <?php 
                // Loop melalui SEMUA user yang diambil dari database
                foreach($all_users_data as $user){
                    // Tentukan label role
                    $role_label = $user['type'] == 1 ? ' (Admin)' : ($user['type'] == 2 ? ' (Manager)' : '');
                    
                    // Cek apakah user ini sudah di-assign sebelumnya
                    $selected = in_array($user['id'], $current_users) ? 'selected' : '';
                    
                    // Tampilkan user
                    echo "<option value='{$user['id']}' $selected>".ucwords($user['firstname'].' '.$user['lastname']) . $role_label ."</option>";
                }
                ?>
            </select>
        </div>
        </form>
        
        <div class="d-flex justify-content-end pt-3 border-top">
            <button type="submit" class="btn btn-primary" form="manage-task">Save</button>
            <button type="button" class="btn btn-secondary ml-2" data-dismiss="modal">Cancel</button>
        </div>
</div>

<script>
    $(document).ready(function(){
    // Inisialisasi Summernote
    $('.summernote').summernote({
        height: 200,
        toolbar: [
            [ 'style', [ 'style' ] ],
            [ 'font', [ 'bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'clear'] ],
            [ 'fontname', [ 'fontname' ] ],
            [ 'fontsize', [ 'fontsize' ] ],
            [ 'color', [ 'color' ] ],
            [ 'para', [ 'ol', 'ul', 'paragraph', 'height' ] ],
            [ 'table', [ 'table' ] ],
            [ 'view', [ 'undo', 'redo', 'fullscreen', 'codeview', 'help' ] ]
        ]
    });

    // Inisialisasi Select2
    $('.select2').select2({
        placeholder: "Select Employee",
        width: "100%"
    });
});

    
    $('#manage-task').submit(function(e){
    e.preventDefault();
    start_load(); // fungsi loading spinner, kalau kamu pakai
    $.ajax({
        url: 'ajax.php?action=save_task',
        method: 'POST',
        data: $(this).serialize(),
        success: function(resp){
            if(resp == 1){
                alert_toast("Task Save", 'success');
                setTimeout(function(){
                    $('#uni_modal').modal('hide'); // Tutup modal dengan ID uni_modal
                    location.reload(); // Refresh halaman
                }, 1500);
            } else {
                alert_toast(resp, 'danger'); // Menampilkan pesan error dari backend
            }
        }
    });
});
</script>