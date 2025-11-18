<?php 
include 'db_connect.php'; 
// Pastikan sesi dimulai dan variabel sesi ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Untuk menghindari Notice Undefined Index jika sesi belum diset
$login_id = isset($_SESSION['login_id']) ? $_SESSION['login_id'] : '';
$login_avatar = isset($_SESSION['login_avatar']) ? $_SESSION['login_avatar'] : 'default.png';
$login_firstname = isset($_SESSION['login_firstname']) ? $_SESSION['login_firstname'] : 'Guest';

$type_arr = array('', "Admin", "Project Manager", "Employee"); 

// 💡 KRITIS: ENKRIPSI ID LOGIN DI SINI
$encoded_login_id = $login_id ? encode_id($login_id) : '';
?>

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

    <!-- ===================== NOTIFICATION ===================== -->
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
            Mark as read
          </a>
        </div>
      </div>
    </li>

    <!-- ===================== FULLSCREEN ===================== -->
    <li class="nav-item">
      <a class="nav-link" data-widget="fullscreen" href="#" role="button">
        <i class="fas fa-expand-arrows-alt"></i>
      </a>
    </li>

    <li class="nav-item dropdown ml-2 mr-4">
        
        <a class="nav-link p-0" href="#" id="navbarDropdownProfile" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="display: flex; align-items: center;">
            <img src="assets/uploads/<?php echo $login_avatar; ?>" 
                 class="img-circle elevation-2" 
                 alt="User Avatar" 
                 style="width: 38px; height: 38px; object-fit: cover; border-radius: 50%;">
            <span class="ml-2"><b><?php echo ucwords($login_firstname); ?></b></span>
        </a>

        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownProfile">
            
            <a class="dropdown-item view_profile_trigger" 
               href="javascript:void(0)" 
               data-id="<?php echo $encoded_login_id; ?>">
                <i class="fa fa-user mr-2"></i> View Profile
            </a>
            
            <a class="dropdown-item" href="index.php?page=edit_user&id=<?php echo $encoded_login_id; ?>">
                <i class="fa fa-cog mr-2"></i> Settings
            </a>
            
            <div class="dropdown-divider"></div>
            
            <a class="dropdown-item" href="ajax.php?action=logout">
                <i class="fa fa-sign-out-alt mr-2"></i> Log Out
            </a>
        </div>
    </li>
    </ul>
</nav>

<style>
/* ====== Notifikasi Dropdown Style Global ====== */
#notification-dropdown {
  width: 340px !important;
  border-radius: 0.75rem !important;
  overflow: hidden !important;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
  font-size: 14px !important;
}

#notification-dropdown .dropdown-header {
  font-size: 14px !important;
  padding: 0.5rem !important;
}

#notification-list .list-group-item {
  padding: 0.6rem 0.75rem !important;
  border: none !important;
}

#notification-list .list-group-item:hover {
  background-color: #f8f9fa !important;
}

.navbar .fa-bell {
  font-size: 18px !important;
}

#notification-count {
  font-size: 11px !important;
  position: absolute !important;
  top: 6px !important;
  right: 8px !important;
  min-width: 16px !important;
  height: 16px !important;
  padding: 0 !important;
  line-height: 16px !important;
  text-align: center !important;
  border-radius: 50% !important;
}

/* Pastikan dropdown tidak ikut overflow parent */
.navbar-nav > .dropdown .dropdown-menu {
  position: absolute !important;
}
</style>

<script>
// Fungsi untuk mengekstrak ID terenkripsi dan page dari URL
function extractPageAndId(url) {
    const match = url.match(/page=([^&]+)&id=([^&]+)/);
    if (match) {
        return { page: match[1], id: match[2] };
    }
    const chatMatch = url.match(/page=chat&thread_id=([^&]+)/);
    if (chatMatch) {
        return { page: 'chat', id: chatMatch[1], param: 'thread_id' };
    }
    return { page: null, id: null };
}

$(document).ready(function(){

  // ====== Profile Dropdown JS (view_profile_trigger) - TIDAK BERUBAH ======
  // Note: Ini masih menggunakan uni_modal karena tujuannya adalah menampilkan profil
  $(document).on('click', '.view_profile_trigger', function(){
      let encodedUserId = $(this).attr('data-id');
      if(encodedUserId) {
          uni_modal(
              "Profile Details", 
              "view_user.php?id=" + encodedUserId, // Mengirim ID terenkripsi ke file modal
              "md"
          );
      } else {
          alert_toast("User ID tidak ditemukan.", "danger");
      }
      $(this).closest('.dropdown-menu').removeClass('show');
  });

  // ====== Load Notifications (timeAgo dan load_notifications tidak berubah) ======
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
    if (typeof $ === 'undefined' || !$.ajax) {
        console.error("jQuery (AJAX) is not available.");
        return;
    }
    
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

              let short_message = n.message.replace(/\*\*/g, '');
              short_message = short_message.length > 70 ? short_message.substring(0, 70) + '...' : short_message;
              const time = `<small class="text-muted d-block">${timeAgo(n.date_created)}</small>`;
              
              const originalLink = n.link && n.link.trim() !== '' ? n.link : '#';

              const html = `
                <a href="${originalLink}" class="${itemClass} notification-item border-0"
                  data-id="${n.id}" data-original-link="${originalLink}" style="cursor:pointer;">
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

  // ====== Mark as Read (tidak berubah) ======
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

  // 💡 PERBAIKAN KRITIS: Hapus semua event.preventDefault() untuk notifikasi navigasi langsung
  $(document).on('click', '.notification-item', function(e) {
      const id = $(this).data('id');
      
      // 1. Mark as Read (Jika belum dibaca)
      if ($(this).hasClass('font-weight-bold')) { 
          mark_as_read(id);
      }

      // 2. Navigasi Langsung
      // Karena semua link notifikasi sudah diatur di href="${originalLink}" 
      // dan view_task/view_project/chat sudah didecode, kita TIDAK PERLU 
      // memanggil e.preventDefault() lagi. Biarkan browser menavigasi.
      
      // Tutup dropdown setelah klik (ini penting karena navigasi akan memakan waktu)
      $(this).closest('.dropdown-menu').removeClass('show');
      
      // Tidak perlu ada logic uni_modal atau window.location.href di sini.
      // Cukup biarkan browser mengikuti atribut href tag <a>.
  });

  // Panggil load_notifications dan atur interval HANYA setelah DOM siap
  load_notifications();
  setInterval(load_notifications, 30000);
});
</script>