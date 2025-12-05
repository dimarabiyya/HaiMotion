<?php include 'db_connect.php'; ?>

<?php
// Cek apakah parameter ID ada di URL
if (isset($_GET['id'])) {
    
    // 1. Ambil ID yang dienkripsi dari URL
    $encoded_id = $_GET['id'];
    
    // 2. Gunakan fungsi decode_id untuk mengembalikan ke ID numerik
    // Fungsi ini ada di db_connect.php
    $id = decode_id($encoded_id); // Mengembalikan ID numerik (misalnya 17) atau null jika gagal.

    // 3. Verifikasi apakah decoding berhasil (ID numerik valid)
    if (!is_null($id)) {
        
        $type_arr = array('', "Admin", "Project Manager", "Employee");

        // 4. Lakukan query menggunakan ID numerik yang sudah didapat
        $qry = $conn->query("SELECT *, CONCAT(firstname,' ',lastname) AS name FROM users WHERE id = $id");

        if ($qry && $qry->num_rows > 0) {
            $row = $qry->fetch_assoc();
            foreach ($row as $k => $v) {
                $$k = $v;
            }
        } else {
            // Jika ID numerik valid, tapi tidak ditemukan di DB
            echo "<div class='p-3 text-center text-danger'>User tidak ditemukan.</div>";
            exit;
        }
    } else {
        // Jika parameter ID ada, tapi proses decoding gagal
        echo "<div class='p-3 text-center text-danger'>Parameter ID tidak valid.</div>";
        exit;
    }
} else {
    // Jika parameter ID tidak ada di URL
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
          <dt class="col-sm-4">Bio</dt>
          <dd class="col-sm-8"><?php echo $address ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<style>
  #uni_modal .modal-footer { display: none; }
  #uni_modal .modal-footer.display { display: flex; }
</style>
