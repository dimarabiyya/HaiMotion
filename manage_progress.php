<?php 
include 'db_connect.php';
// Mengambil data progress jika ada ID (mode Edit)
if(isset($_GET['id'])){
    $qry = $conn->query("SELECT * FROM user_productivity where id = ".$_GET['id'])->fetch_array();
    foreach($qry as $k => $v){
        $$k = $v;
    }
}
?>
<div class="container-fluid">
    <form action="" id="manage-progress">
        <input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">
        <input type="hidden" name="project_id" value="<?php echo isset($_GET['pid']) ? $_GET['pid'] : '' ?>">
        
        <input type="hidden" name="date" id="progress_date" value="<?php echo isset($date) ? date("Y-m-d",strtotime($date)) : '' ?>">
        
        <input type="hidden" name="start_time" id="progress_start_time" value="<?php echo isset($start_time) ? date("H:i",strtotime("2020-01-01 ".$start_time)) : '' ?>">
        
        <input type="hidden" name="end_time" id="progress_end_time" value="<?php echo isset($end_time) ? date("H:i",strtotime("2020-01-01 ".$end_time)) : '' ?>">

        <div class="col-lg-12">
            <div class="row">
                <div class="col-md-12">
                    <?php if(!isset($_GET['tid'])): ?>
                     <div class="form-group">
                      <label for="" class="control-label">Task Name</label>
                      <select class="form-control form-control-sm select2" name="task_id" required>
                        <option></option>
                        <?php 
                        $tasks = $conn->query("SELECT * FROM task_list where project_id = {$_GET['pid']} order by task asc ");
                        while($row= $tasks->fetch_assoc()):
                        ?>
                        <option value="<?php echo $row['id'] ?>" <?php echo isset($task_id) && $task_id == $row['id'] ? "selected" : '' ?>><?php echo ucwords($row['task']) ?></option>
                        <?php endwhile; ?>
                      </select>
                     </div>
                    <?php else: ?>
                    <input type="hidden" name="task_id" value="<?php echo isset($_GET['tid']) ? $_GET['tid'] : '' ?>">
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
            dropdownParent: $('#uni_modal') // Penting jika select2 berada di dalam modal
        });
        
        function formatTime(date) {
            let hours = date.getHours();
            let minutes = date.getMinutes();
            // Pastikan format jam dan menit dua digit
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

                // Set Tanggal saat ini
                $('#progress_date').val(today);
                
                // Set Start Time dan End Time saat ini
                $('#progress_start_time').val(currentTime);
                $('#progress_end_time').val(currentTime);
            }

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
                            location.reload()
                        },1500)
                    } else {
                         // Tambahkan penanganan error jika resp bukan 1 (opsional)
                         alert_toast('Error saving data',"error");
                    }
                    end_load(); // Pastikan loading dihentikan
                },
                error: function(xhr, status, error) {
                     alert_toast('AJAX Error: ' + error,"error");
                     end_load();
                }
            })
        })
    });
</script>