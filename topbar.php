<?php include 'db_connect.php'; ?>
<?php
$type_arr = array('', "Admin", "Project Manager", "Employee");
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
    
    <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#" title="Notifications">
            <i class="fa fa-bell"></i>
            <span class="badge badge-danger navbar-badge" id="notification-count" style="display:none;"></span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right p-0" id="notification-dropdown" style="width: 300px;">
            <span class="dropdown-header text-center" id="notification-header">0 Notifikasi</span>
            <div class="dropdown-divider"></div>
            
            <div id="notification-list" style="max-height: 100px; overflow-y: auto; font-size: 14px;">
                <span class="dropdown-item dropdown-header">Loading...</span>
            </div>
            
            <div class="dropdown-divider"></div>
            <a href="javascript:void(0)" class="dropdown-item dropdown-footer" id="mark-all-read">Mark all as read</a>
        </div>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-widget="fullscreen" href="#" role="button">
        <i class="fas fa-expand-arrows-alt"></i>
      </a>
    </li>

    <li class="nav-item ml-2 mr-4">
      <a class="nav-link p-0 view_user" 
         href="javascript:void(0)" 
         data-id="<?php echo $_SESSION['login_id']; ?>" 
         style="display: flex; align-items: center;">
        <img src="assets/uploads/<?php echo $_SESSION['login_avatar']; ?>" 
             class="img-circle elevation-2" 
             alt="User Avatar" 
             style="width: 38px; height: 38px; object-fit: cover; border-radius: 50%;">
        <span class="ml-2"><b><?php echo ucwords($_SESSION['login_firstname']); ?></b></span>
      </a>
    </li>
  </ul>
</nav>

<script>
$(document).ready(function(){
  $('.view_user').click(function(){
    let userId = $(this).attr('data-id');
    uni_modal("<i class='fa fa-id-card'></i> User Details", "view_user.php?id=" + userId);
  });
  
  // ==============================================
  // JAVASCRIPT NOTIFIKASI
  // ==============================================
  
    // Fungsi untuk memuat notifikasi
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

                    $('#notification-header').text(`${unreadCount} Notifikasi`);
                    countBadge.text(unreadCount).toggle(unreadCount > 0);

                    list.empty();

                    if (notifications.length > 0) {
                        notifications.forEach(n => {
                            // Cek apakah ada notifikasi yang tidak dibaca di list yang dimuat
                            const isUnread = n.is_read == 0 ? 'bg-light font-weight-bold' : '';
                            const readIndicator = n.is_read == 0 ? '<i class="fa fa-circle text-danger float-right small mt-1"></i>' : '';
                            
                            let icon = '<i class="fa fa-bell mr-2 text-secondary"></i>';
                            if (n.type == 1) icon = '<i class="fa fa-tasks mr-2 text-primary"></i>'; 
                            else if (n.type == 2) icon = '<i class="fa fa-sync-alt mr-2 text-warning"></i>'; 
                            else if (n.type == 4) icon = '<i class="fa fa-comment-dots mr-2 text-info"></i>';

                            const short_message = n.message.length > 50 ? n.message.substring(0, 50) + '...' : n.message;
                            
                            // Tambahkan waktu di notifikasi (Anda mungkin perlu fungsi helper untuk ini)
                            const timeAgo = (new Date(n.date_created)).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});


                            const html = `
                                <a href="${n.link}" class="dropdown-item notification-item ${isUnread}" data-id="${n.id}">
                                    ${icon} ${short_message}
                                    ${readIndicator}
                                    <span class="float-right text-muted text-sm d-block">${timeAgo}</span>
                                </a>
                                <div class="dropdown-divider"></div>
                            `;
                            list.append(html);
                        });
                    } else {
                        list.append('<span class="dropdown-item dropdown-header">Tidak ada notifikasi baru.</span>');
                    }
                } else {
                    $('#notification-list').html('<span class="dropdown-item dropdown-header text-danger">Gagal memuat notifikasi.</span>');
                    $('#notification-count').hide();
                }
            },
            error: function() {
                console.error("Failed to fetch notifications");
            }
        });
    }

    // Fungsi Mark as Read
    function mark_as_read(id) {
        $.ajax({
            url: 'ajax.php?action=mark_as_read',
            method: 'POST',
            data: { id: id },
            success: function(resp) {
                if (resp == 1) {
                    load_notifications();
                }
            }
        });
    }
    
    // Handler untuk Mark All as Read
    $('#mark-all-read').click(function(e) {
        e.preventDefault();
        mark_as_read('all');
    });
    
    // Handler untuk klik notifikasi di dropdown (menandai sudah dibaca saat diklik)
    $(document).on('click', '.notification-item', function(e) {
        // Cek jika elemen yang diklik adalah bagian dari item yang belum dibaca
        if ($(this).hasClass('font-weight-bold')) { 
            const id = $(this).data('id');
            mark_as_read(id);
        }
    });

    // Load notifikasi saat halaman dimuat dan refresh setiap 30 detik
    load_notifications();
    setInterval(load_notifications, 30000); 
});
</script>