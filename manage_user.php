<?php
// FILE: manage_user.php
include 'db_connect.php'; 

if(isset($_GET['id'])){
    // 1. Ambil ID yang dienkripsi dari URL
    $encoded_id = $_GET['id'];

    // ➡️ 2. DECODE ID
    // Fungsi decode_id() mengembalikan ID numerik atau null jika gagal.
    $id = decode_id($encoded_id);

    // 3. Verifikasi ID yang telah didekode
    if (!is_numeric($id) || $id <= 0) {
        // Hentikan proses jika ID tidak valid
        echo "<div class='alert alert-danger p-3 text-center'>ID User tidak valid atau tidak dapat didekode.</div>";
        exit;
    }
    
    // 4. Lanjutkan query menggunakan ID numerik yang aman
    // Note: Anda perlu memulai sesi di index.php sebelum menyertakan manage_user.php
    $qry = $conn->query("SELECT * FROM users WHERE id = " . $id);
    
    if($qry->num_rows > 0){
        $data = $qry->fetch_array();
        foreach($data as $k => $v){
            // Memuat variabel seperti $id, $firstname, $email, dll.
            $$k = $v;
        }
    } else {
        echo "<div class='alert alert-danger p-3 text-center'>User tidak ditemukan di database.</div>";
        exit;
    }
}
// Sesi harus sudah dimulai di index.php
?>
<div class="col-lg-12">
    <div class="card">
        <div class="card-body">
            <form action="" id="manage_user">
                <input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">
                <div class="row">
                    <div class="col-md-6 border-right">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="firstname" class="form-control form-control-sm" required value="<?= isset($firstname) ? $firstname : '' ?>">
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="lastname" class="form-control form-control-sm" required value="<?= isset($lastname) ? $lastname : '' ?>">
                        </div>
                        <div class="form-group">
                            <label>Staff Code</label>
                            <input type="text" name="nik" class="form-control form-control-sm" required value="<?= isset($nik) ? $nik : '' ?>">
                        </div>
                        <div class="form-group">
                            <label>Position</label>
                            <textarea name="address" rows="3" class="form-control form-control-sm" required><?= isset($address) ? $address : '' ?></textarea>
                        </div>
                        <?php if($_SESSION['login_type'] == 1): ?>
                        <div class="form-group">
                            <label>User Role</label>
                            <select name="type" class="custom-select custom-select-sm">
                                <option value="3" <?= isset($type) && $type == 3 ? 'selected' : '' ?>>Employee</option>
                                <option value="2" <?= isset($type) && $type == 2 ? 'selected' : '' ?>>Project Manager</option>
                                <option value="1" <?= isset($type) && $type == 1 ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="type" value="3">
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Avatar</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="img" onchange="displayImg(this)">
                                <label class="custom-file-label">Choose file</label>
                            </div>
                        </div>
                        <div class="form-group d-flex justify-content-center">
                            <img src="<?= isset($avatar) ? 'assets/uploads/'.$avatar : '' ?>" alt="Foto Profil" id="cimg" class="img-thumbnail">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email (Login / Username)</label>
                            <input type="email" name="email" class="form-control form-control-sm" required value="<?= isset($email) ? $email : '' ?>">
                            <small id="msg_email"></small>
                        </div>
                        <div class="form-group">
                            <label>Notification Email (Gmail)</label>
                            <input type="email" name="notification_email" class="form-control form-control-sm" value="<?= isset($notification_email) ? $notification_email : '' ?>">
                            <small class="text-muted">Opsional: Email lain untuk menerima push notifikasi.</small>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control form-control-sm" <?= !isset($id) ? 'required' : '' ?>>
                            <small><i><?= isset($id) ? 'Kosongkan jika tidak diubah' : '' ?></i></small>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="cpass" class="form-control form-control-sm" <?= !isset($id) ? 'required' : '' ?>>
                            <small id="pass_match" data-status=''></small>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="text-right">
                    <button class="btn btn-primary mr-2">Save</button>
                    <a href="index.php?page=user_list" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
#cimg {
    height: 15vh;
    width: 15vh;
    object-fit: cover;
    border-radius: 50%;
}
</style>
<script>
// ... (Bagian JavaScript tidak berubah, karena ia bekerja dengan ID numerik dari hidden input)
    function displayImg(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#cimg').attr('src', e.target.result);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    $('[name="password"], [name="cpass"]').keyup(function() {
        let pass = $('[name="password"]').val();
        let cpass = $('[name="cpass"]').val();
        if (cpass === '' || pass === '') {
            $('#pass_match').attr('data-status', '');
        } else if (cpass === pass) {
            $('#pass_match').attr('data-status', '1').html('<span class="text-success">Password matched.</span>');
        } else {
            $('#pass_match').attr('data-status', '2').html('<span class="text-danger">Passwords do not match.</span>');
        }
    });
    $('[name="email"]').on('blur', function() {
        let email = $(this).val();
        let id = $('[name="id"]').val();
        $.post('ajax.php?action=check_email', { email: email, id: id }, function(resp) {
            if (resp == 1) {
                $('#msg_email').html('<div class="text-danger">Email sudah digunakan.</div>');
                $('[name="email"]').addClass("border-danger");
            } else {
                $('#msg_email').html('');
                $('[name="email"]').removeClass("border-danger");
            }
        });
    });
    $('#manage_user').submit(function(e) {
        e.preventDefault();
        // Validasi Password
        if ($('[name="password"]').val() !== '' && $('[name="cpass"]').val() !== '') {
            if ($('#pass_match').attr('data-status') != 1) {
                alert("Password tidak cocok.");
                return false;
            }
        }
        
        // Cek Email Utama apakah sudah digunakan
        if ($('[name="email"]').hasClass("border-danger")) {
             alert("Email login sudah digunakan. Silakan periksa kembali.");
             return false;
        }

        start_load();
        $.ajax({
            url: 'ajax.php?action=save_user',
            data: new FormData(this),
            cache: false,
            contentType: false,
            processData: false,
            method: 'POST',
            success: function(resp) {
                if (resp == 1) {
                    alert_toast("User berhasil disimpan.", "success");
                    setTimeout(function() {
                        location.replace('index.php?page=user_list');
                    }, 1000);
                } else if (resp == 2) {
                    $('#msg_email').html('<div class="text-danger">Email sudah digunakan.</div>');
                    $('[name="email"]').addClass("border-danger");
                    end_load();
                }
            }
        });
    });
</script>