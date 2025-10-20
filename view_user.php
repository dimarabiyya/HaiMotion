<?php include 'db_connect.php'; ?>

<?php
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $type_arr = array('', "Admin", "Project Manager", "Employee");

    $qry = $conn->query("SELECT *, CONCAT(firstname,' ',lastname) AS name FROM users WHERE id = $id");

    if ($qry && $qry->num_rows > 0) {
        $row = $qry->fetch_assoc();
        foreach ($row as $k => $v) {
            $$k = $v;
        }
    } else {
        echo "<div class='p-3 text-center text-danger'>User tidak ditemukan.</div>";
        exit;
    }
} else {
    echo "<div class='p-3 text-center text-danger'>Parameter ID tidak valid.</div>";
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
      <?php if(empty($avatar) || !is_file('assets/uploads/'.$avatar)): ?>
        <span class="brand-image img-circle elevation-2 d-flex justify-content-center align-items-center bg-primary text-white font-weight-500" 
              style="width: 90px; height: 90px;">
          <h4><?php echo strtoupper(substr($firstname, 0,1).substr($lastname, 0,1)) ?></h4>
        </span>
      <?php else: ?>
        <img class="img-circle elevation-2" 
             src="assets/uploads/<?php echo $avatar ?>" 
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
          <dd class="col-sm-8"><?php echo $nik ?></dd>
          <dt class="col-sm-4">Jabatan</dt>
          <dd class="col-sm-8"><?php echo $address ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<div class="modal-footer display p-0 m-0">
  <a href="./index.php?page=edit_user&id=<?php echo $row['id'] ?>" class="btn text-white font-weight-bold" style="background-color:#B75301;">Edit Profile</a>
  <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
</div>

<style>
  #uni_modal .modal-footer { display: none; }
  #uni_modal .modal-footer.display { display: flex; }
</style>
