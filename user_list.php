<?php include 'db_connect.php' ?>
<div class="col-lg-12">
  <div class="card card-outline card-warning">
    <div class="card-header">
      <div class="card-tools">
        <button class="btn btn-block btn-sm btn-default btn-flat border-warning" id="add_user_btn">
          <i class="fa fa-plus"></i> Add New User
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover table-bordered" id="list">
          <thead>
            <tr>
              <th class="text-center">#</th>
              <th>Avatar</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $i = 1;
              $type = array('',"Admin","Project Manager","Employee");
              $qry = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users order by concat(firstname,' ',lastname) asc");
              while($row= $qry->fetch_assoc()):
            ?>
            <tr>
              <th class="text-center"><?php echo $i++ ?></th>
              <td class="text-center">
                <?php 
                  $avatar = !empty($row['avatar']) ? 'assets/uploads/'.$row['avatar'] : 'assets/logo.png';
                ?>
                <img src="<?php echo $avatar ?>" alt="Avatar" width="40" height="40" class="img-thumbnail rounded-circle">  
              </td>
              <td><b><?php echo ucwords($row['name']) ?></b></td>
              <td><b><?php echo $row['email'] ?></b></td>
              <td><b><?php echo $type[$row['type']] ?></b></td>

              <td class="text-center">
                <div class="dropdown dropleft position-relative">
                  <button type="button" class="btn p-0 " data-toggle="dropdown">
                     <i class="fa fa-ellipsis-v"></i>
                  </button>
                  <div class="dropdown-menu">
                    <a class="dropdown-item view_user" href="javascript:void(0)" data-id="<?php echo $row['id'] ?>">
                      <i class="fa fa-eye mr-2"></i> View
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="./index.php?page=edit_user&id=<?php echo $row['id'] ?>">
                      <i class="fa fa-cog mr-2"></i> Edit
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger delete_user_trigger" 
                       href="javascript:void(0)" 
                       data-id="<?php echo $row['id'] ?>" 
                       data-name="<?php echo ucwords($row['name']) ?>">
                      <i class="fa fa-trash mr-2"></i> Delete
                    </a>
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

<!-- MODAL: Konfirmasi Delete -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="fa fa-trash"></i> Konfirmasi Hapus</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        Apakah Anda yakin ingin menghapus user: <b id="deleteUserName"></b>?
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button class="btn btn-danger" id="confirmDeleteBtn">Hapus</button>
      </div>
    </div>
  </div>
</div>

<style>
.table-responsive {
  overflow-x: auto;
}
table.dataTable thead th.sorting:before,
table.dataTable thead th.sorting:after,
table.dataTable thead th.sorting_asc:before,
table.dataTable thead th.sorting_asc:after,
table.dataTable thead th.sorting_desc:before,
table.dataTable thead th.sorting_desc:after {
  display: none !important;
  content: "" !important;
}
</style>

<script>
$(document).ready(function(){
  $('#list').DataTable();

  // Show modal add
  $('#add_user_btn').click(function(){
    $('#userModal').modal('show');
  });

  // View User
  $('.view_user').click(function(){
    uni_modal("<i class='fa fa-id-card'></i> User Details","view_user.php?id="+$(this).attr('data-id'));
  });

  // Trigger delete modal
  $(document).on('click', '.delete_user_trigger', function(){
    var id = $(this).data('id');
    var name = $(this).data('name');
    $('#deleteUserName').text(name);
    $('#confirmDeleteBtn').data('id', id);
    $('#confirmDeleteModal').modal('show');
  });

  // Confirm delete
  $('#confirmDeleteBtn').click(function(){
    var id = $(this).data('id');
    $('#confirmDeleteModal').modal('hide');
    $.ajax({
      url:'ajax.php?action=delete_user',
      method:'POST',
      data:{id:id},
      success:function(resp){
        if(resp == 1){
          Swal.fire({
            icon:'success',
            title:'Terhapus',
            text:'User berhasil dihapus.',
            timer:2000,
            showConfirmButton:false
          });
          setTimeout(()=>location.reload(), 1500);
        } else {
          Swal.fire({
            icon:'error',
            title:'Gagal',
            text:'Terjadi kesalahan saat menghapus user.'
          });
        }
      }
    });
  });

  // Avatar preview
  $('#avatar').change(function(){
    const file = this.files[0];
    if (file){
      let reader = new FileReader();
      reader.onload = function(e){
        $('#avatarPreview').attr('src', e.target.result).show();
      }
      reader.readAsDataURL(file);
    }
  });

  // Password check
  $('#password, #cpass').on('keyup', function(){
    if ($('#password').val() != $('#cpass').val()) {
      $('#passHelp').text('Passwords do not match.');
    } else {
      $('#passHelp').text('');
    }
  });

  // Email uniqueness check
  $('#email').on('blur', function(){
    var email = $(this).val();
    if(email != ''){
      $.ajax({
        url: 'ajax.php?action=check_email',
        method: 'POST',
        data: {email: email},
        success: function(resp){
          if(resp == 1){
            $('#emailHelp').text('Email sudah digunakan.');
          } else {
            $('#emailHelp').text('');
          }
        }
      });
    }
  });

  // Save user
  $('#userForm').submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
      url: 'ajax.php?action=save_user',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(resp){
        if(resp == 1){
          Swal.fire({
            icon: 'success',
            title: 'Sukses',
            text: 'User berhasil ditambahkan.',
            timer: 2000,
            showConfirmButton: false
          });
          $('#userModal').modal('hide');
          setTimeout(() => location.reload(), 2000);
        } else if (resp == 2) {
          Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: 'Email sudah digunakan.'
          });
        }
      }
    });
  });
});
</script>
