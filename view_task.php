<?php 
include 'db_connect.php';

if(isset($_GET['id'])){
	$qry = $conn->query("SELECT * FROM task_list WHERE id = ".$_GET['id'])->fetch_array();
	foreach($qry as $k => $v){
		$$k = $v;
	}
}

// 🔹 Ambil assigned users (user_ids pakai koma)
$task_assigned_users = [];
if (!empty($user_ids)) {
    $task_user_ids = array_map('intval', explode(',', $user_ids));
    if (!empty($task_user_ids)) {
        $ids_str = implode(',', $task_user_ids);
        $task_users_q = $conn->query("SELECT id, avatar, firstname, lastname FROM users WHERE id IN ($ids_str)");
        while ($u = $task_users_q->fetch_assoc()) {
            $task_assigned_users[] = $u;
        }
    }
}
?>

<div class="container-fluid notion-style">

	<!-- Title & Status -->
	<div class="mb-4">
		<h2 class="task-title"><?= ucwords($task) ?></h2>
		<div>
			<?php 
	        	if($status == 0){
			  		echo "<span class='badge badge-secondary text-dark'> Pending</span>";
	        	}elseif($status == 1){
			  		echo "<span class='badge badge-info'> Started</span>";
				}elseif($status == 2){
			  		echo "<span class='badge badge-primary'>On-Progress</span>";
	        	}elseif($status == 3){
			  		echo "<span class='badge badge-warning'>Hold</span>";
				}elseif($status == 4){
			  		echo "<span class='badge badge-danger'>Over Due</span>";
				}elseif($status == 5){
			  		echo "<span class='badge badge-success'>Done</span>";
	        	}
        	?>
		</div>
	</div>

	<!-- Assigned Users -->
	<div class="section mb-4">
		<h6 class="section-title">Assignment User</h6>
		<?php if (!empty($task_assigned_users)): ?>
			<div class="d-flex pl-2">
				<?php foreach ($task_assigned_users as $au): ?>
					<img src="assets/uploads/<?php echo !empty($au['avatar']) ? $au['avatar'] : 'default.png'; ?>" 
						alt="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>" 
						class="rounded-circle border border-secondary avatar-overlap" 
						title="<?php echo ucwords($au['firstname'].' '.$au['lastname']); ?>">
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<span class="text-muted">No User</span>
		<?php endif; ?>
	</div>

	<!-- Dates -->
	<div class="section mb-4">
		<h6 class="section-title">Timeline</h6>
		<p><b>Start:</b> <?= !empty($start_date) ? date("F d, Y", strtotime($start_date)) : "<i>Not set</i>" ?></p>
		<p><b>End:</b> <?= !empty($end_date) ? date("F d, Y", strtotime($end_date)) : "<i>Not set</i>" ?></p>
		<p><b>Created:</b> <?= !empty($date_created) ? date("F d, Y h:i A", strtotime($date_created)) : "<i>Unknown</i>" ?></p>
	</div>

	<!-- Content Pillar & Platform -->
	<div class="section mb-4">
		<h6 class="section-title">Content</h6>
		<p><b>Pillar:</b> <?= !empty($content_pillar) ? $content_pillar : "<i>—</i>" ?></p>
		<p><b>Platform:</b> <?= !empty($platform) ? $platform : "<i>—</i>" ?></p>
	</div>

	<!-- Reference Links -->
	<div class="section mb-4">
		<h6 class="section-title">References</h6>
		<?php if(!empty($reference_links)): ?>
			<a href="<?= $reference_links ?>" target="_blank" class="ref-link"><?= $reference_links ?></a>
		<?php else: ?>
			<p><i>No References</i></p>
		<?php endif; ?>
	</div>

	<!-- Description -->
	<div class="section mb-4">
		<h6 class="section-title">Description</h6>
		<div class="desc-box">
			<?= !empty($description) ? html_entity_decode($description) : "<i>No Description</i>" ?>
		</div>
	</div>

</div>

<style>
	.notion-style {
		font-family: "Inter", "Segoe UI", sans-serif;
		color: #2d2d2d;
	}
	.task-title {
		font-size: 1.6rem;
		font-weight: 600;
		margin-bottom: .3rem;
	}
	.badge {
		font-size: 0.85rem;
		padding: 0.4em 0.7em;
		border-radius: 6px;
	}
	.section-title {
		font-size: 0.8rem;
		font-weight: 600;
		text-transform: uppercase;
		color: #888;
		margin-bottom: 0.3rem;
		letter-spacing: 0.5px;
	}
	.ref-link {
		color: #0d6efd;
		text-decoration: none;
	}
	.ref-link:hover {
		text-decoration: underline;
	}
	.desc-box {
		background: #fafafa;
		padding: 0.8rem 1rem;
		border-radius: 8px;
		font-size: 0.95rem;
		line-height: 1.5;
	}
	.avatar-overlap {
		width: 35px;
		height: 35px;
		object-fit: cover;
		margin-left: -8px;
	}
	.avatar-overlap:first-child {
		margin-left: 0;
	}
</style>
