<?php include 'db_connect.php'; ?>

<?php
$encoded_id = $_GET['id'] ?? null;
$id = decode_id($encoded_id); 
if (!is_numeric($id) || $id <= 0) {
    echo "<div class='p-3 text-center text-danger'>Parameter ID tidak valid atau tidak dapat didekode.</div>";
    exit;
}

// Lanjutkan proses dengan ID numerik yang aman
$type_arr = array('', "Admin", "Project Manager", "Employee");

// Query menggunakan ID numerik yang sudah diverifikasi
$qry = $conn->query("SELECT *, CONCAT(firstname,' ',lastname) AS name, address as bio FROM users WHERE id = $id");

if ($qry && $qry->num_rows > 0) {
    $row = $qry->fetch_assoc();
    foreach ($row as $k => $v) {
        $$k = $v;
    }
} else {
    echo "<div class='p-3 text-center text-danger'>User tidak ditemukan.</div>";
    exit;
}
?>

<div class="container-fluid">
  <div class="card card-widget widget-user shadow">
    <div class="widget-user-header bg-dark">
      <h3 class="widget-user-username mb-0"><?php echo ucwords($name) ?></h3>
      <h5 class="widget-user-desc"><?php echo $email ?></h5>
    </div>

    <div class="widget-user-image mt-2">
      <?php 
      // Ganti $address menjadi $bio karena alias di query
      $avatar_path = 'assets/uploads/'.$avatar; 
      ?>
      <?php if(empty($avatar) || !is_file($avatar_path)): ?>
        <span class="brand-image img-circle elevation-2 d-flex justify-content-center align-items-center bg-primary text-white font-weight-500" 
              style="width: 90px; height: 90px;">
          <h4><?php echo strtoupper(substr($firstname, 0,1).substr($lastname, 0,1)) ?></h4>
        </span>
      <?php else: ?>
        <img class="img-circle elevation-2" 
             src="<?php echo $avatar_path ?>" 
             alt="User Avatar" 
             style="width: 90px; height: 90px; object-fit: cover;">
      <?php endif; ?>
    </div>

    <div class="card-footer">
      <div class="container-fluid">
        <dl class="row mb-0">
          <dt class="col-sm-4">Role</dt>
          <dd class="col-sm-8"><?php echo $type_arr[$type] ?></dd>
          <dt class="col-sm-4">Staff ID</dt>
          <dd class="col-sm-8"><?php echo $nik ?? 'N/A' ?></dd>
          <dt class="col-sm-4">Bio</dt>
          <dd class="col-sm-8"><?php echo $bio ?? 'No bio provided' ?></dd> 
        </dl>
      </div>
    </div>
  </div>
</div>

<style>
  #uni_modal .modal-footer { display: none; }
  #uni_modal .modal-footer.display { display: flex; }
</style>
