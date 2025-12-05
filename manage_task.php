<?php 
session_start();
include 'db_connect.php';

// ---------------------------------------------
// ➡️ 1. DECODE INCOMING IDs DARI URL
// ---------------------------------------------
$id_decoded = null;
$pid_decoded = null; 
$id_encoded = $_GET['id'] ?? null;
$pid_encoded = $_GET['pid'] ?? null;

// Decode Task ID (id)
if (!empty($id_encoded)) {
    $decoded = decode_id($id_encoded);
    if (is_numeric($decoded) && $decoded > 0) {
        $id_decoded = $decoded;
    }
}

// Decode Project ID (pid)
if (!empty($pid_encoded)) {
    $decoded = decode_id($pid_encoded);
    if (is_numeric($decoded) && $decoded > 0) {
        $pid_decoded = $decoded;
    }
}
// ---------------------------------------------


// 2. Fetch existing task data if $id_decoded is set (Edit Mode)
if(isset($id_decoded)){
    // Gunakan ID numerik yang sudah didekode
    $qry = $conn->query("SELECT * FROM task_list where id = ".$id_decoded);
    
    if($qry->num_rows > 0){
        $data = $qry->fetch_array();
        foreach($data as $k => $v){
            $$k = $v;
        }
        // Pastikan $pid_decoded diisi dari project_id di DB jika ini mode edit
        $pid_decoded = isset($project_id) ? $project_id : $pid_decoded;
    } else {
        $id_decoded = null; // Task tidak ditemukan, kembali ke mode 'Add New'
    }
}


// 3. Set Project ID Numerik yang aman untuk seluruh form logic
// Ambil dari pid_decoded atau dari project_id hasil query jika ada, fallback ke 0
$pid = $pid_decoded ?? (isset($project_id) ? $project_id : 0);

if ($pid === 0) {
    echo "<div class='alert alert-danger p-3'>Project ID tidak valid atau hilang.</div>";
    exit;
}

$project_user_ids = [];
$proj = $conn->query("SELECT user_ids, manager_id FROM project_list WHERE id = $pid"); // Query menggunakan $pid (numeric)
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
?>

<? include 'header.php'?>
<div class="container-fluid">
    <form action="" id="manage-task">
        <input type="hidden" name="id" value="<?php echo isset($id_decoded) ? $id_decoded : '' ?>">
        <input type="hidden" name="project_id" value="<?php echo $pid ?>">
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
            <label>Content Pillar</label>
            <br>
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
            <label>Platform</label><br>
            <?php $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Twitter', 'LinkedIn', 'Website','IGs','Reels','Feeds', 'Lainnya'];
            $selected_platforms = isset($platform) ? explode(',', $platform) : [];
            foreach($platforms as $plat): ?>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="platform[]" value="<?= $plat ?>" <?= in_array($plat, $selected_platforms) ? 'checked' : '' ?>>
                <label class="form-check-label"><?= $plat ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="form-group">
            <label>Reference Links</label>
            <textarea name="reference_links" class="form-control" rows="3"><?= isset($reference_links) ? $reference_links : '' ?></textarea>
            <small class="text-muted">Pisahkan link dengan enter.</small>
        </div>

        <div class="form-group">
            <label for="">Status</label>
            <select name="status" id="status" class="form-control form-control-sm">
                <option value="0" <?php echo isset($status) && $status == 0 ? 'selected' : '' ?>>Pending</option>
                <option value="2" <?php echo isset($status) && $status == 2 ? 'selected' : '' ?>>On-Progress</option>
                <option value="3" <?php echo isset($status) && $status == 3 ? 'selected' : '' ?>>Hold</option>
                <option value="5" <?php echo isset($status) && $status == 5 ? 'selected' : '' ?>>Done</option>
            </select>
        </div>

        <div class="form-group">
            <label for="" class="control-label">Assignee</label>
            <select class="form-control form-control-sm select2" multiple="multiple" name="user_ids[]">
                <option></option>
                <?php 
                // Loop melalui SEMUA user yang diambil dari database
                foreach($all_users_data as $user){
                    // Tentukan label role
                    $role_label = $user['type'] == 1 ? ' (Admin)' : ($user['type'] == 2 ? ' (Manager)' : '');
                    
                    // Cek apakah user ini sudah di-assign sebelumnya
                    $selected = in_array($user['id'], $current_users) ? 'checked' : ''; // menggunakan 'checked' karena ini bukan select2
                    
                    // Gunakan in_array yang benar: $current_users adalah array ID numerik
                    $selected = in_array($user['id'], $current_users) ? 'selected' : '';
                    
                    // Tampilkan user
                    echo "<option value='{$user['id']}' $selected>".ucwords($user['firstname'].' '.$user['lastname']) . $role_label ."</option>";
                }
                ?>
            </select>
        </div>
        </form>
        
        <div class="d-flex justify-content-end pt-3">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn ml-2 text-white" style="background-color: #B75301;" form="manage-task">Save</button>
        </div>
</div>

<script>
    $(document).ready(function(){
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

    // Inisialisasi Select2 untuk ASSIGN TO (Class: .select2)
    $('.select2').select2({
        placeholder: "Select Employee",
        width: "100%",
        theme: 'bootstrap4',
        dropdownParent: $('body'), // Attach ke body bukan modal, fix overflow!
        maximumSelectionLength: 30,
        allowClear: true,
        closeOnSelect: false,
        dropdownAutoWidth: true,
        dropdownPosition: 'below'
    });
});

    
    $('#manage-task').off('submit').submit(function(e){
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
                    $('#uni_modal').modal('hide'); 
                    location.reload();
                }, 1500);
            } else if (resp == 2) {
                 alert_toast("Task gagal disimpan: Data duplikat atau error server", 'danger');
            }
             else {
                alert_toast(resp, 'danger');
            }
             end_load(); // Pastikan loading dihentikan
        },
        error: function(xhr, status, error) {
            alert_toast('AJAX Error: ' + error, "error");
            end_load();
        }
    });
});
</script>

<style>
/* ===== MODAL OVERFLOW FIX - PENTING UNTUK DROPDOWN! ===== */
/* Fix sama seperti bug dropdown status sebelumnya */
#uni_modal .modal-body {
    overflow: visible !important;
    overflow-y: visible !important;
    overflow-x: hidden !important;
}

#uni_modal .modal-content {
    overflow: visible !important;
}

#uni_modal {
    overflow-y: auto !important;
}

/* ===== SELECT2 DROPDOWN FIX ===== */
/* Pastikan dropdown Select2 tidak terpotong */
.select2-container {
    z-index: 9999 !important;
}

.select2-dropdown {
    z-index: 10000 !important;
    min-width: 0 !important;
    width: auto !important;
}

/* Paksa dropdown muncul di bawah */
.select2-container--open .select2-dropdown--below {
    border-top: none;
}

/* Atur tinggi maksimal dropdown */
.select2-results {
    max-height: 200px !important;
    overflow-y: auto !important;
}

/* Kompak hasil Select2 */
.select2-results__option {
    padding: 6px 12px !important;
    font-size: 0.9rem !important;
    white-space: nowrap !important;
}

/* Ukuran selection box yang lebih compact */
.select2-selection--multiple .select2-selection__choice {
    padding: 2px 6px !important;
    font-size: 0.85rem !important;
    margin: 2px !important;
}

.select2-container--bootstrap4 .select2-selection--multiple {
    min-height: 38px !important;
}

/* Fix lebar dropdown agar tidak terlalu lebar */
.select2-container--bootstrap4 .select2-dropdown {
    width: fit-content !important;
    min-width: 200px !important;
    max-width: 400px !important;
}

/* Fix untuk modal */
#uni_modal .select2-container {
    width: 100% !important;
}

/* ===== RESPONSIVE MOBILE STYLES FOR MANAGE TASK ===== */
@media (max-width: 768px) {
    /* Center modal dialog on mobile */
    #uni_modal .modal-dialog,
    .modal-xl {
        max-width: 90% !important;
        width: 90% !important;
        margin: 1rem auto !important;
        display: block !important;
        position: relative !important;
        left: 0 !important;
        right: 0 !important;
    }
    
    #uni_modal .modal-content {
        border-radius: 8px !important;
        width: 100% !important;
        margin: 0 auto !important;
    }
    
    #uni_modal .modal-body {
        padding: 1rem !important;
        overflow-x: hidden !important;
    }
    
    .container-fluid {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
        width: 100% !important;
    }
    
    form#manage-task {
        width: 100% !important;
    }
    
    /* Make all columns full width on mobile */
    .col-md-4, .col-md-6, .col-md-8, .col-md-12 {
        width: 100% !important;
        max-width: 100% !important;
        flex: 0 0 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 0.75rem !important;
    }
    
    .row {
        width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    /* Form adjustments */
    .form-group {
        margin-bottom: 1rem !important;
    }
    
    .form-group label {
        font-size: 0.9rem !important;
        font-weight: 600;
    }
    
    .form-control,
    .form-control-sm {
        font-size: 0.9rem !important;
        padding: 0.5rem !important;
    }
    
    textarea.form-control {
        min-height: 80px !important;
    }
    
    /* Select2 adjustments */
    .select2-container {
        width: 100% !important;
    }
    
    .select2-selection {
        min-height: 38px !important;
    }
    
    /* Button adjustments */
    .btn {
        font-size: 0.9rem !important;
        padding: 0.5rem 1rem !important;
    }
}

@media (max-width: 576px) {
    #uni_modal .modal-body {
        padding: 0.75rem !important;
    }
    
    .form-group {
        margin-bottom: 0.75rem !important;
    }
    
    .form-group label {
        font-size: 0.85rem !important;
    }
    
    .form-control,
    .form-control-sm {
        font-size: 0.85rem !important;
        padding: 0.4rem !important;
    }
}
</style>