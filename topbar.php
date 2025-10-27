<?php include 'db_connect.php'; ?>
<?php $type_arr = array('', "Admin", "Project Manager", "Employee"); ?>

<nav class="main-header navbar navbar-expand navbar-light">
  <ul class="navbar-nav">
    <?php if (isset($_SESSION['login_id'])): ?>
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button">
          <i class="fas fa-bars"></i>
        </a>
      </li>
    <?php endif; ?>
  </ul>
  

  <ul class="navbar-nav ml-auto align-items-center">

    <li class="nav-item dropdown">
      <a class="nav-link position-relative" data-toggle="dropdown" href="#" title="Notifikasi">
        <i class="fa fa-bell"></i>
        <span class="badge badge-danger navbar-badge" id="notification-count" style="display:none;"></span>
      </a>
      <div class="dropdown-menu dropdown-menu-right shadow-lg border-0 p-0" id="notification-dropdown" style="width: 340px; border-radius: 0.75rem; overflow:hidden;">
        <div class="text-center py-2">
          <p id="notification-header"></p>
          <hr>
        </div>
        <div id="notification-list" style="max-height: 300px; overflow-y: auto;" class="list-group list-group-flush">
          <div class="list-group-item text-center text-muted small">Loading...</div>
        </div>
        <div class="text-center py-2 border-top bg-light">
          <a href="javascript:void(0)" id="mark-all-read" class="small text-primary font-weight-bold">
            Mark all as read
          </a>
        </div>
      </div>
    </li>

    <li class="nav-item">
      <a class="nav-link" data-widget="fullscreen" href="#" role="button">
        <i class="fas fa-expand-arrows-alt"></i>
      </a>
    </li>

    <li class="nav-item ml-2 mr-4">
      <a class="nav-link p-0 view_user" href="javascript:void(0)" data-id="<?php echo $_SESSION['login_id']; ?>" style="display: flex; align-items: center;">
        <img src="assets/uploads/<?php echo $_SESSION['login_avatar']; ?>" class="img-circle elevation-2" alt="User Avatar" style="width: 38px; height: 38px; object-fit: cover; border-radius: 50%;">
        <span class="ml-2"><b><?php echo ucwords($_SESSION['login_firstname']); ?></b></span>
      </a>
    </li>
  </ul>
</nav>

<style>
/* ... (Gaya CSS yang sama) ... */
</style>

<script>
// Fungsi untuk mengekstrak ID numerik dari URL notifikasi (Robust)
function extractTaskId(url) {
    // Mencari pola id=NUMERIC_ID (mengabaikan ID non-numerik yang dienkripsi)
    const match = url.match(/id=(\d+)/);
    return match ? match[1] : null; 
}

$(document).ready(function(){

  // ====== Profile Modal ======
  $('.view_user').click(function(){
    let userId = $(this).attr('data-id');
    uni_modal("<i class='fa fa-id-card'></i> Profil Pengguna", "view_user.php?id=" + userId);
  });

  // ====== Load Notifications ======
  function timeAgo(dateStr) {
    const now = new Date();
    const then = new Date(dateStr);
    const diff = Math.floor((now - then) / 1000);

    if (diff < 60) return "Baru saja";
    if (diff < 3600) return Math.floor(diff / 60) + " menit lalu";
    if (diff < 86400) return Math.floor(diff / 3600) + " jam lalu";
    return Math.floor(diff / 86400) + " hari lalu";
  }

  function load_notifications() {
    $.ajax({
      url: 'ajax.php?action=fetch_notifications',
      method: 'GET',
      dataType: 'json',
      success: function(resp) {
        if (resp && resp.status == 1) {
          const list = $('#notification-list');
          const countBadge = $('#notification-count');
          const notifications = resp.notifications;
          const unreadCount = resp.unread_count;

          $('#notification-header').text(`${unreadCount} Notification`);
          countBadge.text(unreadCount).toggle(unreadCount > 0);
          list.empty();

          if (notifications.length > 0) {
            notifications.forEach(n => {
              const isUnread = n.is_read == 0;
              const itemClass = isUnread ? 'list-group-item bg-light font-weight-bold' : 'list-group-item';
              const dot = isUnread ? '<span class="badge badge-danger ml-2" style="width:8px; height:8px; border-radius:50%;"></span>' : '';

              let icon = '<i class="fa fa-bell text-secondary mr-2"></i>';
              if (n.type == 1) icon = '<i class="fa fa-tasks text-primary mr-2"></i>';
              else if (n.type == 2) icon = '<i class="fa fa-sync-alt text-warning mr-2"></i>';
              else if (n.type == 4) icon = '<i class="fa fa-comment-dots text-info mr-2"></i>';

              // Hilangkan markdown bold dari notifikasi
              let short_message = n.message.replace(/\*\*/g, '');
              short_message = short_message.length > 70 ? short_message.substring(0, 70) + '...' : short_message;
              const time = `<small class="text-muted d-block">${timeAgo(n.date_created)}</small>`;
              
              // Simpan link asli di data-href dan buat href menjadi # (untuk intercept)
              const linkIsTask = n.link.includes('get_task_detail.php?id=') || n.link.includes('view_task&id=');
              const finalHref = linkIsTask ? 'javascript:void(0)' : n.link;

              const html = `
                <a href="${finalHref}" class="${itemClass} notification-item border-0" 
                   data-id="${n.id}" data-original-link="${n.link}" style="cursor:pointer;">
                  <div class="d-flex align-items-start">
                    ${icon}
                    <div class="flex-fill">
                      <div>${short_message} ${dot}</div>
                      ${time}
                    </div>
                  </div>
                </a>`;
              list.append(html);
            });
          } else {
            list.append('<div class="list-group-item text-center text-muted small">Tidak ada notifikasi baru.</div>');
          }
        } else {
          $('#notification-list').html('<div class="list-group-item text-center text-danger small">Gagal memuat notifikasi.</div>');
          $('#notification-count').hide();
        }
      },
      error: function() {
        console.error("Gagal mengambil notifikasi");
      }
    });
  }

  // ====== Mark as Read ======
  function mark_as_read(id) {
    $.ajax({
      url: 'ajax.php?action=mark_as_read',
      method: 'POST',
      data: { id: id },
      success: function(resp) {
        if (resp == 1) load_notifications();
      }
    });
  }

  $('#mark-all-read').click(function(e) {
    e.preventDefault();
    mark_as_read('all');
  });

  // Handler untuk klik notifikasi di dropdown (Membuka modal jika itu notifikasi task)
  $(document).on('click', '.notification-item', function(e) {
      const originalLink = $(this).data('original-link');
      const id = $(this).data('id');
      
      // 1. Periksa apakah ini adalah notifikasi tugas/progress
      if (originalLink && (originalLink.includes('get_task_detail.php?id=') || originalLink.includes('view_task&id='))) {
          e.preventDefault(); 
          
          const taskId = extractTaskId(originalLink);

          if (taskId) {
              // Jika ID numerik valid ditemukan, buka modal
              // Target URL adalah get_task_detail.php
              uni_modal("Task Details", 'get_task_detail.php?id=' + taskId, "mid-large"); 
              
              // Tutup dropdown setelah membuka modal
              $(this).closest('.dropdown-menu').removeClass('show');
          } else {
              // Notifikasi lama/rusak (ID dienkripsi), kita bisa arahkan ke halaman utama task_list
              console.warn("Notifikasi lama terdeteksi, navigasi ke task list.");
              window.location.href = 'index.php?page=mytask';
          }
      }
      
      // 2. Mark as Read (Jika belum dibaca)
      if ($(this).hasClass('font-weight-bold')) { 
          mark_as_read(id);
      }
  });

  load_notifications();
  setInterval(load_notifications, 30000);
});
</script>