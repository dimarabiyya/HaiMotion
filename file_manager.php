<?php
// Pastikan sesi dimulai dan user login
if(!isset($_SESSION['login_id']))
    header('location:login.php');

// Mendapatkan ID folder yang sedang dilihat, default ke NULL (root)
$folder_id = $_GET['folder_id'] ?? null;
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><i class="fas fa-folder-open"></i> File Manager</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./">Home</a></li>
                    <li class="breadcrumb-item active">File Manager</li>
                </ol>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div id="breadcrumb-list" class="pt-2">
                    </div>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <div class="card-tools">
                    <button class="btn btn-primary btn-sm" id="btn-add-folder"><i class="fa fa-folder-plus"></i> Buat Folder</button>
                    <button class="btn btn-success btn-sm" id="btn-upload-file"><i class="fa fa-upload"></i> Unggah File</button>
                    <button class="btn btn-secondary btn-sm" id="btn-go-back" style="display:none;"><i class="fa fa-arrow-left"></i> Kembali</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th width="10%">Tipe</th>
                                <th>Nama</th>
                                <th width="15%">Ukuran</th>
                                <th width="15%">Tanggal Dibuat</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="file-list-body">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="upload-modal" tabindex="-1" role="dialog" aria-labelledby="upload-modal-label" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="upload-modal-label">Unggah File Baru</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="upload-form" enctype="multipart/form-data">
        <div class="modal-body">
            <div class="form-group">
                <label for="upload_file_input">Pilih File</label>
                <input type="file" class="form-control" name="file" id="upload_file_input" required>
            </div>
            <input type="hidden" name="action" value="upload_file">
            <input type="hidden" name="folder_id" id="upload_folder_id">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary">Unggah</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="permission-modal" tabindex="-1" role="dialog" aria-labelledby="permission-modal-label" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="permission-modal-label">Atur Izin: <span id="resource-name"></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="permission-form">
            <input type="hidden" id="perm_resource_id" name="resource_id">
            <input type="hidden" id="perm_resource_type" name="resource_type">
            <input type="hidden" name="action" value="manage_permission">

            <div class="form-group">
                <label>Akses Publik</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_public_switch" name="is_public_switch">
                    <label class="custom-control-label" for="is_public_switch">Akses Publik: <span id="public-status">Tidak Aktif</span></label>
                </div>
                <small class="form-text text-muted">Jika diaktifkan, siapapun dapat melihat/mengunduh (View).</small>
            </div>
            
            <hr>

            <div class="form-group">
                <label for="user_selector">Tambah Akses Pengguna Lain</label>
                <select id="user_selector" class="form-control" style="width:100%;">
                     <option value="" disabled selected>Pilih Pengguna...</option>
                     </select>
            </div>
             <div id="current-permissions">
                </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>


<script>
    const API_URL = 'file_api.php';
    let current_folder_id = '<?php echo $folder_id ?? get_root_folder_id($conn, $_SESSION['login_id'] ?? 0); ?>';
    let path_history = [current_folder_id]; // Untuk navigasi 'Kembali'

    // Fungsi utilitas untuk format ukuran file
    function formatSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Fungsi untuk mendapatkan ID folder root user (disederhanakan di client)
    // Sebaiknya fungsi ini ada di PHP, tapi untuk demonstrasi kita simpan di JS juga.
    // Asumsi: Root folder ID adalah ID folder yang parent_id-nya NULL dan owner_id-nya sama dengan user_id.
    const ROOT_FOLDER_ID = path_history[0]; 

    // -------------------------------------------------------------
    // LOGIKA PERMINTAAN DATA (AJAX)
    // -------------------------------------------------------------

    function loadFolderContent(folder_id) {
        $('#file-list-body').html('<tr><td colspan="5" class="text-center"><i class="fa fa-sync fa-spin"></i> Memuat...</td></tr>');
        
        // Update URL and history for proper navigation
        $('#current_folder_id').val(folder_id);
        
        // Cek tombol 'Kembali'
        if (path_history.length > 1) {
            $('#btn-go-back').show();
        } else {
            $('#btn-go-back').hide();
        }
        
        // Update Breadcrumb
        renderBreadcrumb();

        $.ajax({
            url: API_URL,
            type: 'GET',
            dataType: 'json',
            data: { action: 'list', folder_id: folder_id },
            success: function(response) {
                if (response.status === 'success') {
                    renderFileList(response.data);
                } else {
                    $('#file-list-body').html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat konten: ' + response.message + '</td></tr>');
                    // Jika gagal, pastikan history dikembalikan ke yang valid
                    if (path_history.length > 1) {
                         path_history.pop();
                    }
                    if (path_history.length === 0) {
                         path_history = [ROOT_FOLDER_ID];
                    }
                }
            },
            error: function() {
                $('#file-list-body').html('<tr><td colspan="5" class="text-center text-danger">Terjadi kesalahan koneksi server.</td></tr>');
            }
        });
    }

    // -------------------------------------------------------------
    // LOGIKA RENDERING UI
    // -------------------------------------------------------------
    
    function renderBreadcrumb() {
        // Logika breadcrumb ini harusnya terintegrasi dengan data struktur folder dari backend.
        // Untuk saat ini, kita akan buat sederhana berdasarkan history path_history
        $('#breadcrumb-list').html('<small>Jalur: <span class="badge badge-secondary">Root</span> ' + path_history.map(id => {
            // Ini akan sulit tanpa data nama folder dari backend. 
            // Kita akan buat API endpoint terpisah untuk mengambil jalur lengkap jika perlu.
            return `<span class="breadcrumb-item-custom" data-id="${id}">...</span>`;
        }).join(' > ') + '</small>');
    }

    function renderFileList(data) {
        let html = '';
        if (data.folders.length === 0 && data.files.length === 0) {
            html += '<tr><td colspan="5" class="text-center">Folder ini kosong.</td></tr>';
        }

        // Folders
        data.folders.forEach(function(item) {
            html += `<tr class="folder-item" data-id="${item.id}" data-type="folder" style="cursor: pointer;">
                         <td><i class="far fa-folder" style="color: #ffc107;"></i></td>
                         <td>${item.name}</td>
                         <td>-</td>
                         <td>${item.created_at}</td>
                         <td>
                             <button class="btn btn-sm btn-info btn-view" title="Masuk" onclick="enterFolder(${item.id})"><i class="fa fa-eye"></i></button>
                             <button class="btn btn-sm btn-secondary btn-permission" title="Izin" data-id="${item.id}" data-type="folder" data-name="${item.name}"><i class="fa fa-share-alt"></i></button>
                         </td>
                     </tr>`;
        });

        // Files
        data.files.forEach(function(item) {
            const downloadUrl = `${API_URL}?action=download&file_id=${item.id}&user_id=<?php echo $_SESSION['login_id']; ?>`;
            html += `<tr class="file-item" data-id="${item.id}" data-type="file">
                         <td><i class="far fa-file" style="color: #17a2b8;"></i></td>
                         <td>${item.name}</td>
                         <td>${formatSize(item.size)}</td>
                         <td>${item.created_at}</td>
                         <td>
                             <a href="${downloadUrl}" class="btn btn-sm btn-success" title="Unduh"><i class="fa fa-download"></i></a>
                             <button class="btn btn-sm btn-secondary btn-permission" title="Izin" data-id="${item.id}" data-type="file" data-name="${item.name}"><i class="fa fa-share-alt"></i></button>
                         </td>
                     </tr>`;
        });
        
        $('#file-list-body').html(html);
    }


    // -------------------------------------------------------------
    // EVENT HANDLERS
    // -------------------------------------------------------------

    // Aksi Masuk Folder (Double Click atau Button View)
    window.enterFolder = function(folder_id) {
        current_folder_id = folder_id;
        path_history.push(folder_id);
        loadFolderContent(folder_id);
    };

    // Aksi Kembali
    $('#btn-go-back').on('click', function() {
        if (path_history.length > 1) {
            path_history.pop(); 
            current_folder_id = path_history[path_history.length - 1];
            loadFolderContent(current_folder_id);
        } else {
             // Jika sudah di root, refresh saja
             loadFolderContent(ROOT_FOLDER_ID);
        }
    });

    // 1. Buat Folder
    $('#btn-add-folder').on('click', function() {
        const folder_name = prompt("Masukkan nama folder baru:");
        if (folder_name) {
            $.ajax({
                url: API_URL,
                type: 'POST',
                dataType: 'json',
                data: { 
                    action: 'create_folder', 
                    name: folder_name, 
                    parent_id: current_folder_id 
                },
                success: function(response) {
                    if (response.status === 'success') {
                        alert('Folder berhasil dibuat!');
                        loadFolderContent(current_folder_id);
                    } else {
                        alert('Gagal membuat folder: ' + response.message);
                    }
                }
            });
        }
    });

    // 2. Unggah File (Tampilkan Modal)
    $('#btn-upload-file').on('click', function() {
        $('#upload_folder_id').val(current_folder_id);
        $('#upload-modal').modal('show');
    });

    // 3. Submit Form Unggah File
    $('#upload-form').on('submit', function(e) {
        e.preventDefault();
        
        if ($('#upload_file_input').get(0).files.length === 0) {
            alert("Mohon pilih file untuk diunggah.");
            return;
        }

        var formData = new FormData(this);
        
        // Tambahkan user_id ke formData secara eksplisit (penting untuk security di backend)
        formData.append('user_id', '<?php echo $_SESSION['login_id']; ?>');

        $.ajax({
            url: API_URL,
            type: 'POST',
            data: formData,
            processData: false, 
            contentType: false, 
            dataType: 'json',
            beforeSend: function() {
                $('button[type="submit"]', '#upload-form').attr('disabled', true).text('Mengunggah...');
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    $('#upload-modal').modal('hide');
                    $('#upload-form')[0].reset();
                    loadFolderContent(current_folder_id);
                } else {
                    alert('Gagal: ' + response.message);
                }
            },
            error: function() {
                alert('Terjadi kesalahan saat mengunggah file.');
            },
            complete: function() {
                 $('button[type="submit"]', '#upload-form').attr('disabled', false).text('Unggah');
            }
        });
    });

    // 4. Pengaturan Izin (Tampilkan Modal)
    $('#file-list-body').on('click', '.btn-permission', function() {
        const id = $(this).data('id');
        const type = $(this).data('type');
        const name = $(this).data('name');

        $('#perm_resource_id').val(id);
        $('#perm_resource_type').val(type);
        $('#resource-name').text(name);

        // TODO: Panggil API untuk mendapatkan status public dan daftar izin saat ini
        // dan perbarui UI modal (switch dan daftar)
        
        $('#permission-modal').modal('show');
    });


    // Inisialisasi awal
    loadFolderContent(current_folder_id);
});

// Anda juga perlu menambahkan fungsi JS untuk manajemen izin, seperti toggle public access
// dan menambahkan/menghapus user, yang akan memanggil file_api.php dengan action=manage_permission.
// Contoh implementasi ini akan sangat bergantung pada cara Anda memuat daftar user.
</script>