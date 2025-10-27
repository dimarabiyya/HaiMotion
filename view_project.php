<?php
// FILE: view_project.php (FINAL DENGAN KEAMANAN DI INDEX.PHP)

include 'db_connect.php'; 
session_start(); // Biarkan session_start() di sini, meskipun akan di-ignore

// ID sudah didekode dan diverifikasi di index.php (Project ID numerik)
$id = $_GET['id'] ?? 0; 
if ($id === 0) {
    header("Location: index.php?page=404");
    exit;
}

// 1. Query Proyek
$qry = $conn->query("SELECT * FROM project_list WHERE id = $id");
$project_data = $qry->fetch_array();

// 2. Cek jika proyek benar-benar ditemukan setelah verifikasi (untuk berjaga-jaga)
if (!$project_data) {
    header("Location: index.php?page=404");
    exit;
}

// 3. Deklarasikan Variabel
foreach($project_data as $k => $v){
    $$k = $v;
}

// 4. Deklarasikan $row dan $today untuk kompatibilitas kode lama
$row = $project_data; 
$today = strtotime(date("Y-m-d"));

// =======================================================
// LOGIKA UTAMA ANDA DIMULAI DI SINI (aman)
// =======================================================

$tprog = $conn->query("SELECT * FROM task_list where project_id = {$id}")->num_rows;
$cprog = $conn->query("SELECT * FROM task_list where project_id = {$id} and status = 3")->num_rows;
$prog = $tprog > 0 ? ($cprog/$tprog) * 100 : 0;
$prog = $prog > 0 ?  number_format($prog,2) : $prog;
$prod = $conn->query("SELECT * FROM user_productivity where project_id = {$id}")->num_rows;

// Cek overdue
$endDate = strtotime($row['end_date']);
if($row['status'] != 5 && $row['status'] != 3 && $row['status'] != 0 && $today > $endDate){
    $row['status'] = 4; // Over Due
}

if($status == 0 && strtotime(date('Y-m-d')) >= strtotime($start_date)):
if($prod  > 0  || $cprog > 0)
  $status = 2;
else
  $status = 1;
elseif($status == 0 && strtotime(date('Y-m-d')) > strtotime($end_date)):
$status = 4;
endif;

$manager = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where id = $manager_id");
$manager = $manager->num_rows > 0 ? $manager->fetch_array() : array();

// mapping status sesuai DB
$stat = array(
    0 => "Pending",
	1 => "Started",
    2 => "On-Progress",
    3 => "On-Hold",
	4 => "Over Due",
    5 => "Done"
);
?>

<div class="col-lg-12">
	<div class="row">
		<div class="col-md-12">
			<div class="card border p-2">
				<div class="col-md-12">	
					<div class="row">
						<div class="col-sm-6">
							<dl>
								<dt><b class="border-warning">Project Name</b></dt>
								<dd><?php echo ucwords($name) ?></dd>
								<dt><b class="border-warning">Description</b></dt>
								<dd>
									<?php 
									$decoded_description = html_entity_decode(html_entity_decode($description));
									echo strip_tags($decoded_description); 
									?>
								</dd>
							</dl>
							<dl>
								<dt><b class="border-warning">Project Manager</b></dt>
								<dd>
									<?php if(isset($manager['id'])) : ?>
									<div class="d-flex align-items-center mt-1">
										<img class="img-circle img-thumbnail p-0 shadow-sm border-info img-sm mr-3" src="assets/uploads/<?php echo $manager['avatar'] ?>" alt="Avatar">
										<b><?php echo ucwords($manager['name']) ?></b>
									</div>
									<?php else: ?>
										<small><i>Manager Deleted from Database</i></small>
									<?php endif; ?>
								</dd>
							</dl>
						</div>
						<div class="col-md-6">
							<dl>
								<dt><b class="border-warning">Start Date</b></dt>
								<dd><?php echo date("F d, Y",strtotime($start_date)) ?></dd>
							</dl>
							<dl>
								<dt><b class="border-warning">End Date</b></dt>
								<dd><?php echo date("F d, Y",strtotime($end_date)) ?></dd>
							</dl>
							<dl>
								<dt><b class="border-warning">Status</b></dt>
								<dd>
									<?php 
									$badgeClass = [
										0 => "secondary",  // Pending
										1 => "info",
										2 => "primary",    // On-Progress
										3 => "warning",    // On-Hold
										4 => "danger",
										5 => "success"     // Done
									];
									$class = isset($badgeClass[$status]) ? $badgeClass[$status] : "dark";
									echo "<span class='badge badge-{$class}'>".$stat[$status]."</span>";
									?>
								</dd>
							</dl>

						</div>
					</div>
						<div class="p-2">
							<div class="">
								<span><b>Team Member/s:</b></span>
								<div class="card-tools">	
									</div>
							</div>
							<div class="card-body">
								<ul class="users-list clearfix">
									<?php 
									if(!empty($user_ids)):
										$members = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where id in ($user_ids) order by concat(firstname,' ',lastname) asc");
										while($row=$members->fetch_assoc()):
									?>
											<li>
												<img src="assets/uploads/<?php echo $row['avatar'] ?>" alt="User Image">
												<a class="users-list-name" href="javascript:void(0)"><?php echo ucwords($row['name']) ?></a>
												</li>
									<?php 
										endwhile;
									endif;
									?>
								</ul>
							</div>
						</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="container-fluid">
			<div class="card card-outline">
				<div class="card-header">
					<span><b>Task List</b></span>
					<div class="card-tools">
						<button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
                        	<i class="fa fa-plus mr-1"></i> Add Task
                    	</button>
					</div>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
					<table class="table table-condensed m-0">
						<colgroup>
							<col width="5%">
							<col width="20%">
							<col width="37%">
							<col width="10%">
							<col width="20%">
							<col width="5%">
						</colgroup>
						<thead>
							<th class="text-left">No</th>
							<th>Task</th>
							<th>Description</th>
							<th>Status</th>
							<th class="text-left">Assigned To</th>
							<th> </th>
						</thead>
						<tbody>
							<?php 
							$i = 1;
							$today = strtotime(date("Y-m-d"));
							$tasks = $conn->query("SELECT * FROM task_list WHERE project_id = {$id} ORDER BY task ASC");
							while($row = $tasks->fetch_assoc()):
								$trans = get_html_translation_table(HTML_ENTITIES,ENT_QUOTES);
								unset($trans["\""], $trans["<"], $trans[">"], $trans["<h2"]);
								$desc = strtr(html_entity_decode($row['description']),$trans);
								$desc = str_replace(array("<li>","</li>"), array("",", "), $desc);

								// Cek overdue
								$endDate = strtotime($row['end_date']);
								if($row['status'] != 5 && $row['status'] != 3 && $today > $endDate){
									$row['status'] = 4; // paksa jadi Over Due
								}
							?>
								<tr class="view_task_row" data-id="<?php echo $row['id'] ?>" style="cursor:pointer;">
									<td class="text-left"><?php echo $i++ ?></td>
									<td><b><?php echo ucwords($row['task']) ?></b></td>
									<td><p class="truncate"><?php echo strip_tags($desc) ?></p></td>
									<td>
										<?php 
										if($row['status'] == 0){
												echo "<span class='badge badge-secondary'>Pending</span>";
											}elseif($row['status'] == 1){
												echo "<span class='badge badge-info'>Started</span>";
											}elseif($row['status'] == 2){
												echo "<span class='badge badge-primary'>On-Progress</span>";
											}elseif($row['status'] == 3){
												echo "<span class='badge badge-warning'>On-Hold</span>";
											}elseif($row['status'] == 4){
												echo "<span class='badge badge-danger'>Over Due</span>";
											}elseif($row['status'] == 5){
												echo "<span class='badge badge-success'>Done</span>";
											}
										?>
									</td>
									<?php 
									// === Assigned Users ===
									$task_assigned_users = [];
									if (!empty($row['user_ids'])) {
										$task_user_ids = explode(',', $row['user_ids']);
										$task_user_ids = array_map('intval', $task_user_ids);
										if (!empty($task_user_ids)) {
											$ids_str = implode(',', $task_user_ids);
											$task_users_q = $conn->query("SELECT id, avatar, firstname, lastname 
																		FROM users 
																		WHERE id IN ($ids_str)");
											while ($u = $task_users_q->fetch_assoc()) {
												$task_assigned_users[] = $u;
											}
										}
									}
									?>
									<td class="text-left">
										<?php if (!empty($task_assigned_users)): ?>
											<div class="d-flex justify-content-left">
												<?php foreach ($task_assigned_users as $au): ?>
													<img src="assets/uploads/<?php echo !empty($au['avatar']) ? $au['avatar'] : 'default.png'; ?>" 
														alt="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>" 
														class="rounded-circle border border-secondary" 
														style="width:30px; height:30px; object-fit:cover; margin-left:-8px;" 
														title="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>">
												<?php endforeach; ?>
											</div>
										<?php else: ?>
											<span class="text-muted">No User</span>
										<?php endif; ?>
									</td>

									<td class="text-left">
										<div class="dropdown">
											<button class="btn text-secondary" type="button" id="dropdownMenu<?= $row['id'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
												<i class="fa fa-ellipsis-v"></i>
											</button>
											<div class="dropdown-menu">
												<h6 class="dropdown-header">Action</h6>
												<a class="dropdown-item view_task" href="javascript:void(0)" data-id="<?php echo $row['id'] ?>" data-task="<?php echo $row['task'] ?>"><i class="fa fa-eye mr-3"></i>View</a>
												<a class="dropdown-item edit_task" href="javascript:void(0)" data-id="<?php echo $row['id'] ?>" data-task="<?php echo $row['task'] ?>"><i class="fa fa-cog mr-3"></i>Edit</a>
												<a class="dropdown-item text-danger delete_task" href="javascript:void(0)" data-id="<?php echo $row['id'] ?>"><i class="fa fa-trash mr-3"></i>Delete</a>
											</div>
										</div>
									</td>
								</tr>
							<?php endwhile; ?>
							</tbody>

					</table>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="card">
				<div class="card-header">
					<b>Comment</b>
					<div class="card-tools">
						<button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
                        	<i class="fa fa-plus mr-1"></i> Add Comment
                    	</button>
					</div>
				</div>
				<div class="card-body">
					<?php 
					$progress = $conn->query("SELECT p.*,concat(u.firstname,' ',u.lastname) as uname,u.avatar,t.task FROM user_productivity p inner join users u on u.id = p.user_id inner join task_list t on t.id = p.task_id where p.project_id = $id order by unix_timestamp(p.date_created) desc ");
					while($row = $progress->fetch_assoc()):
					?>
						<div class="post">
		                      <div class="user-block">
		                      	<?php if($_SESSION['login_id'] == $row['user_id']): ?>
		                      	<span class="btn-group dropleft float-right">
								  <span class="btndropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="cursor: pointer;">
								    <i class="fa fa-ellipsis-v"></i>
								  </span>
								  <div class="dropdown-menu">
									<h6 class="dropdown-header">Action</h6>
								  	<a class="dropdown-item manage_progress" href="javascript:void(0)" data-id="<?php echo $row['id'] ?>"  data-task="<?php echo $row['task'] ?>">Edit</a>
									<a class="dropdown-item delete_progress" href="javascript:void(0)" data-id="<?php echo $row['id'] ?>">Delete</a>
								  </div>
								</span>
								<?php endif; ?>
		                        <img class="img-circle img-bordered-sm" src="assets/uploads/<?php echo $row['avatar'] ?>" alt="user image">
		                        <span class="username">
		                          <a href="#"><?php echo ucwords($row['uname']) ?>[ <?php echo ucwords($row['task']) ?> ]</a>
		                        </span>
		                        <span class="description">
		                        	<span class="fa fa-calendar-day"></span>
		                        	<span><b><?php echo date('M d, Y',strtotime($row['date'])) ?></b></span>
		                        	<span class="fa fa-user-clock"></span>
                      				<span>Start: <b><?php echo date('h:i A',strtotime($row['date'].' '.$row['start_time'])) ?></b></span>
		                        	<span> | </span>
                      				<span>End: <b><?php echo date('h:i A',strtotime($row['date'].' '.$row['end_time'])) ?></b></span>
	                        	</span>
		                      </div>
		                      <div>
		                       <?php echo html_entity_decode($row['comment']) ?>
		                      </div>

		                      <p>
		                        </p>
	                    </div>
	                    <div class="post clearfix"></div>
                    <?php endwhile; ?>
				</div>
			</div>
		</div>
	</div>
</div>
<style>
	.users-list>li img {
	    border-radius: 50%;
	    height: 67px;
	    width: 67px;
	    object-fit: cover;
	}
	.users-list>li {
		width: 33.33% !important
	}
	.truncate {
		-webkit-line-clamp:1 !important;
	}
</style>

<script>
    // Pastikan start_load() dan end_load() sudah didefinisikan secara global
    // Pastikan _conf() sudah didefinisikan secara global

    // =========================================================
    // MODAL HANDLER: uni_modal (Implementasi Opsi A)
    // =========================================================
    function uni_modal(title, url, size = "mid-large") {
        // 1. CLEANUP (PENTING): Hancurkan instance Summernote yang ada
        // Ini dijalankan SEBELUM konten baru dimuat, yang mencegah konflik inisialisasi ganda.
        if ($('#uni_modal .summernote').length) {
            $('#uni_modal .summernote').summernote('destroy');
        }

        // 2. Load Content
        // Asumsi: Anda memiliki fungsi start_load()
        if (typeof start_load !== 'undefined') { start_load(); } 
        
        $.ajax({
            url: url,
            success: function(resp) {
                if (resp) {
                    // Isi modal
                    $('#uni_modal .modal-title').html(title);
                    $('#uni_modal .modal-body').html(resp);
                    // Pastikan kelas ukuran modal dihandle dengan benar
                    $('#uni_modal .modal-dialog').removeClass('mid-large large').addClass(size); 

                    // 3. RE-INITIALIZE: Inisialisasi ulang SETELAN konten dimuat
                    if ($('#uni_modal .summernote').length) {
                        $('#uni_modal .summernote').summernote({ 
                            height: 200,
                            // Tambahkan opsi Select2 jika manage_task.php menggunakannya
                            callbacks: {
                                onInit: function() {
                                    if ($('.select2').length) {
                                        $('.select2').select2({
                                            dropdownParent: $('#uni_modal'),
                                            width: '100%'
                                        });
                                    }
                                }
                            }
                        });
                    }

                    // Tampilkan Modal
                    $('#uni_modal').modal('show');
                }
                if (typeof end_load !== 'undefined') { end_load(); } 
            },
            error: function() {
                if (typeof end_load !== 'undefined') { end_load(); } 
                // Opsional: alert_toast("Gagal memuat modal", 'danger');
            }
        });
    }

    function delete_progress($id){
        if (typeof start_load !== 'undefined') { start_load(); }
        $.ajax({
            url:'ajax.php?action=delete_progress',
            method:'POST',
            data:{id:$id},
            success:function(resp){
                if(resp==1){
                    alert_toast("Data successfully deleted",'success')
                    setTimeout(function(){ location.reload() },1500)
                }
            }
        })
    }
    function delete_task(id){
        if (typeof start_load !== 'undefined') { start_load(); }
        $.ajax({
            url: 'ajax.php?action=delete_task',
            method: 'POST',
            data: { id: id },
            success: function(resp){
                if(resp == 1){
                    alert_toast("Task successfully deleted", 'success')
                    setTimeout(function(){  location.reload() }, 1500)
                }
            }
        })
    }
    
    // Fungsi helper untuk memicu Edit Task modal
    function edit_task(id, taskName = 'Task'){
        // Panggil uni_modal yang sudah diperbaiki
        uni_modal("Edit Task: " + taskName, "manage_task.php?pid=<?php echo $id ?>&id=" + id, "mid-large");
    }

    // =========================================================
    // EVENT HANDLERS
    // =========================================================
    
    // 1. New Task
    $('#new_task').click(function(){
        edit_task(0, "New Task For <?php echo ucwords($name) ?>"); // ID 0 untuk task baru
    })
    
    // 2. Edit Task (Dropdown)
    $('.edit_task').click(function(){
        const taskId = $(this).attr('data-id');
        const taskName = $(this).attr('data-task');
        edit_task(taskId, taskName);
    });

    // 3. View Task
    $('.view_task').click(function(){
        uni_modal("Task Details","view_task.php?id="+$(this).attr('data-id'),"mid-large")
    })

    // 4. Delete Task
    $('.delete_task').click(function(){
        _conf("Are you sure to delete this task?", "delete_task", [$(this).attr('data-id')])
    })
    
    // 5. Productivity/Comment buttons
    $('#new_productivity').click(function(){
        uni_modal("<i class='fa fa-plus'></i> New Progress","manage_progress.php?pid=<?php echo $id ?>",'large')
    })
    $('.manage_progress').click(function(){
        uni_modal("<i class='fa fa-edit'></i> Edit Progress","manage_progress.php?pid=<?php echo $id ?>&id="+$(this).attr('data-id'),'large')
    })
    $('.delete_progress').click(function(){
        _conf("Are you sure to delete this progress?","delete_progress",[$(this).attr('data-id')])
    })

    // 6. Klik Baris Task -> MENGEDIT
    $('.view_task_row').click(function(e){
        if(!$(e.target).closest('.dropdown').length && !$(e.target).is('button') && !$(e.target).is('a')){
            const taskId = $(this).data('id');
            const taskName = $(this).data('task'); 
            edit_task(taskId, taskName);
        }
    });

    // 7. PENTING: Hapus listener hidden.bs.modal lama
    // Logika cleanup sekarang berada di uni_modal, jadi handler ini dihapus
    $('#uni_modal').off('hidden.bs.modal');
</script>