<?php 
include 'db_connect.php';

// --- 1. DECODE IDs YANG MASUK DARI URL ---

// a) Decode Progress ID (ID progress saat mode Edit)
$encoded_progress_id = $_GET['id'] ?? null;
$progress_id_decoded = null;
if ($encoded_progress_id) {
    // Asumsi decode_id() tersedia dari db_connect.php
    $decoded = decode_id($encoded_progress_id);
    if (is_numeric($decoded) && $decoded > 0) {
        $progress_id_decoded = $decoded;
    }
}

// b) Decode Project ID (ID proyek, digunakan untuk memfilter daftar task)
$encoded_project_id = $_GET['pid'] ?? null;
$project_id_decoded = null;
if ($encoded_project_id) {
    $decoded = decode_id($encoded_project_id);
    if (is_numeric($decoded) && $decoded > 0) {
        $project_id_decoded = $decoded;
    }
}

// c) Decode Task ID (ID task, digunakan jika form dibuka dari detail task)
$encoded_task_id = $_GET['tid'] ?? null;
$task_id_decoded = null;
if ($encoded_task_id) {
    $decoded = decode_id($encoded_task_id);
    if (is_numeric($decoded) && $decoded > 0) {
        $task_id_decoded = $decoded;
    }
}


// --- 2. FETCH DATA UNTUK MODE EDIT (jika $progress_id_decoded valid) ---
if($progress_id_decoded){
    // Mengambil data progress menggunakan ID numerik
    $qry = $conn->query("SELECT * FROM user_productivity where id = ".$progress_id_decoded);
    
    if($qry->num_rows > 0){
        $data = $qry->fetch_array();
        foreach($data as $k => $v){
            $$k = $v;
        }
        
        // Jika mode Edit, ambil task_id dan project_id dari DB
        $task_id_decoded = $task_id; 
        $project_id_decoded = $project_id; 
    } else {
        // Jika ID didekode tapi data tidak ada
        $progress_id_decoded = null;
    }
}

// --- 3. Set Final Form Values (ID numerik) ---
$form_progress_id = $progress_id_decoded ?? '';
$form_project_id = $project_id_decoded ?? '';
$form_task_id = $task_id_decoded ?? '';


// Jika project ID belum ditemukan (misalnya, diakses tanpa PID valid), hentikan.
if (empty($form_project_id)) {
    echo "<div class='alert alert-danger p-3 text-center'>Project ID tidak valid.</div>";
    exit;
}
?>

<div class="container-fluid">
    <form action="" id="manage-progress">
        <input type="hidden" name="id" value="<?php echo $form_progress_id ?>">
        <input type="hidden" name="project_id" value="<?php echo $form_project_id ?>">
        
        <input type="hidden" name="date" id="progress_date" value="<?php echo isset($date) ? date("Y-m-d",strtotime($date)) : '' ?>">
        
        <input type="hidden" name="start_time" id="progress_start_time" value="<?php echo isset($start_time) ? date("H:i",strtotime("2020-01-01 ".$start_time)) : '' ?>">
        
        <input type="hidden" name="end_time" id="progress_end_time" value="<?php echo isset($end_time) ? date("H:i",strtotime("2020-01-01 ".$end_time)) : '' ?>">

        <div class="col-lg-12">
            <div class="row">
                <div class="col-md-12">
                    <?php if(empty($_GET['tid'])): ?>
                     <div class="form-group">
                      <label for="" class="control-label">Task Name</label>
                      <select class="form-control form-control-sm select2" name="task_id" required>
                        <option></option>
                        <?php 
                        // Menggunakan $form_project_id (ID numerik) untuk memfilter task
                        $tasks = $conn->query("SELECT * FROM task_list where project_id = {$form_project_id} order by task asc ");
                        while($row= $tasks->fetch_assoc()):
                        ?>
                        <option value="<?php echo $row['id'] ?>" <?php echo isset($task_id) && $task_id == $row['id'] ? "selected" : '' ?>><?php echo ucwords($row['task']) ?></option>
                        <?php endwhile; ?>
                      </select>
                     </div>
                    <?php else: ?>
                    <input type="hidden" name="task_id" value="<?php echo $form_task_id ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="">Subject</label>
                        <input type="text" class="form-control form-control-sm" name="subject" value="<?php echo isset($subject) ? $subject : '' ?>" required>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="">Comment</label>
                        <textarea name="comment" id="progress_comment" cols="30" rows="10" class="summernote form-control" required="">
                            <?php echo isset($comment) ? $comment : '' ?>
                        </textarea>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-right">
                <button class="btn btn-primary mr-2">Save</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </form>
</div>

<script>
    function initializeSummernote() {
        // ... (fungsi summernote tidak berubah) ...
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
    }


    $(document).ready(function(){
        initializeSummernote(); 
        
        $('.select2').select2({
            placeholder:"Please select here",
            width: "100%",
            dropdownParent: $('#uni_modal') 
        });
        
        function formatTime(date) {
            let hours = date.getHours();
            let minutes = date.getMinutes();
            hours = (hours < 10 ? '0' : '') + hours;
            minutes = (minutes < 10 ? '0' : '') + minutes;
            return `${hours}:${minutes}`;
        }

        $('#manage-progress').submit(function(e){
            e.preventDefault()
            
            $('#progress_comment').val($('#progress_comment').summernote('code'));

            if ($('#manage-progress input[name="id"]').val() == '') {
                const now = new Date();
                const today = now.toISOString().split('T')[0]; // Format YYYY-MM-DD
                const currentTime = formatTime(now); // Format HH:mm

                $('#progress_date').val(today);
                $('#progress_start_time').val(currentTime);
                $('#progress_end_time').val(currentTime);
            }

            // 💡 PENTING: ID yang dikirim di sini adalah ID NUMERIK AMAN.
            // AJAX action=save_progress di server (admin_class.php)
            // harus memastikan ia menerima ID numerik yang aman ini.

            start_load()
            $.ajax({
                url:'ajax.php?action=save_progress',
                data: new FormData($(this)[0]),
                cache: false,
                contentType: false,
                processData: false,
                method: 'POST',
                type: 'POST',
                success:function(resp){
                    if(resp == 1){
                        alert_toast('Data successfully saved',"success");
                        setTimeout(function(){
                            // Refresh halaman setelah sukses
                            location.reload()
                        },1500)
                    } else {
                         alert_toast('Error saving data: ' + resp,"error");
                    }
                    end_load(); 
                },
                error: function(xhr, status, error) {
                     alert_toast('AJAX Error: ' + error,"error");
                     end_load();
                }
            })
        })
    })
</script>