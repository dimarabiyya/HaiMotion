<?php
// task_calendar.php
include 'header.php';
include 'db_connect.php'; // WAJIB ada di sini

if (!isset($_SESSION['login_id']) || !isset($_SESSION['login_type'])) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['login_id']) || !isset($_SESSION['login_type'])) {
        die('Unauthorized access.');
    }
}

$current_user_id = $_SESSION['login_id'] ?? 0;
$login_type = $_SESSION['login_type'] ?? 0;

// 1. Ambil daftar project yang diizinkan
$where = "";
if ($login_type == 2) { // Manager
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids) OR manager_id = $current_user_id";
} elseif ($login_type == 3) { // Member
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids)";
}

$projects = [];
$project_q = $conn->query("SELECT id, name FROM project_list $where ORDER BY name ASC");
if ($project_q) {
    while ($row = $project_q->fetch_assoc()) $projects[] = $row;
}
$allowed_project_ids = array_column($projects, 'id');

// 2. Tentukan Project ID yang dipilih dari URL dan dekode
$encoded_project_id = $_GET['project_id'] ?? null;
$project_id = $encoded_project_id ? decode_id($encoded_project_id) : 0;

// 3. Validasi dan atur Project ID default
if (!in_array($project_id, $allowed_project_ids) && !empty($allowed_project_ids)) {
    $project_id = $allowed_project_ids[0]; 
    $encoded_project_id = encode_id($project_id);
} elseif (empty($allowed_project_ids)) {
    $project_id = 0; 
    $encoded_project_id = null;
} else {
    $encoded_project_id = encode_id($project_id);
}

// 4. Siapkan URL untuk FullCalendar events
$load_task_url = "loadtask.php";
if ($encoded_project_id) {
    // Mengirim ID project terenkripsi ke loadtask.php
    $load_task_url .= "?project_id=" . urlencode($encoded_project_id);
}

// Ambil nama project yang sedang dipilih
$current_project_name = "Semua Project";
if ($project_id > 0) {
    $found_keys = array_keys(array_column($projects, 'id'), $project_id);
    if (!empty($found_keys)) {
        $current_project_name = $projects[$found_keys[0]]['name'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Task Calendar</title>

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">


  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    /* ... (CSS yang sama) ... */
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', sans-serif;
    }

    .calendar-container {
      max-width: 100%;
      margin: auto;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
      
    }

    .fc-day-header {
      background-color: #B75301;
      height: 50px;
      padding: 0;                    
      font-weight: bold;
      color: white;                
      justify-content: center;      
      align-items: center;           
      text-align: center;      
    }


    .fc-event {
        background-color: #ffffff !important; 
        border: 1px solid #e5e7eb !important; 
        border-radius: 8px; 
        padding: 6px 10px;
        font-weight: 500;
        font-size: 14px;
        color: #111827;
        text-align: left; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* efek card */
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        height: auto !important; 
        line-height: 1.4;

    }

    /* Hover efek seperti Notion */
    .fc-event:hover {
      background-color: #f9fafb !important;
      box-shadow: 0 4px 6px rgba(0,0,0,0.12);
    }

    /* Untuk judul event */
    .fc-event .fc-title {
      display: block;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 2px;
    }

    /* Untuk waktu event */
    .fc-event .fc-time {
      display: block;
      font-size: 12px;
      font-weight: 400;
      color: #6b7280;
    }
    .fc-today {
      background-color: #fff3cd !important;
    }

    .fc-row {
      min-height: 40px !important;
      height: auto !important;
    }

    .fc-day, 
    .fc-widget-content {
      height: auto !important;
    }

    .fc-day-grid-container {
      height: auto !important;
    }

    /* Setiap box hari full height otomatis */
    .fc-day-grid .fc-row .fc-content-skeleton {
      position: relative !important;
      height: auto !important;
    }

    /* Event tidak dipotong */
    .fc-event-container {
      overflow: visible !important;
    }

    /* Agar banyak event -> kotak hari ikut panjang */
    .fc-row .fc-bg {
      height: auto !important;
    }

    .fc-day-grid-event {
      white-space: normal !important;  /* event text wrap */
    }

    /* Custom filter area */
    .filter-area {
        margin-bottom: 15px;
        padding-left: 0;
        display: flex;
        align-items: center;
    }
  </style>
</head>

<body>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="filter-area">
        <div class="dropdown">
            <button class="btn text-white dropdown-toggle" type="button" id="projectDropdown"
                data-toggle="dropdown" aria-expanded="false" style="background-color: #B75301 !important;">
                <i class="fas fa-clipboard"></i>
                <?= htmlspecialchars($current_project_name) ?>
            </button>
            <div class="dropdown-menu" aria-labelledby="projectDropdown">
                <?php foreach ($projects as $project): ?>
                    <li>
                        <a class="dropdown-item <?= $project_id == $project['id'] ? 'active' : '' ?>"
                           href="index.php?page=task_calendar&project_id=<?= encode_id($project['id']) ?>">
                            <?= htmlspecialchars($project['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php if (!empty($projects)): ?>
                    <div class="dropdown-divider"></div>

                     <li>
                          <a class="dropdown-item" href="./index.php?page=calendar">
                              <i class="fas fa-calendar-alt mr-2"></i> Event Calendar
                          </a>
                      </li>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="text-right">
        <button id="printCalendar" class="btn text-white" style="background-color: #B75301">
            <i class="fa fa-file-pdf-o"></i> Export Calendar
        </button>
    </div>
  </div>


  <div class="container-fluid mt-4 calendar-container border border-dark rounded">
    <div id="calendar"></div>
  </div>

  <div class="modal fade" id="taskModal" tabindex="-1" role="dialog" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow">
        <div class="modal-header">
          <h5 class="modal-title" id="taskModalLabel">Task Detail</h5>
          <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          Loading...
        </div>
      </div>
    </div>
  </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
$('#calendar').fullCalendar({
  // ... (Pengaturan FullCalendar yang sudah ada) ...
  height: 'auto',
  contentHeight: 'auto',
  aspectRatio: 1.35, 
  fixedWeekCount: false, 
  editable: true,
  selectable: true,
  selectHelper: true,
  defaultView: 'month',
  eventLimit: true,
  header: {
    left: 'prev,today,next',
    center: 'title',
    right: ''
  },
  // URL events sekarang berisi filter project_id terenkripsi
  events: '<?= $load_task_url ?>',

  eventRender: function(event, element) {
    // ... (Kode eventRender tetap sama) ...
    let statusMap = {
      0: '<span class="badge badge-secondary">Pending</span>',
      1: '<span class="badge badge-info">Started</span>',
      2: '<span class="badge badge-primary">On-Progress</span>',
      3: '<span class="badge badge-warning">Hold</span>',
      4: '<span class="badge badge-danger">Over Due</span>',
      5: '<span class="badge badge-success">Done</span>'
    };

    element.find('.fc-title').remove();

    let cleanDescription = '';
    if (event.description) {
        let rawText = event.description.replace(/<[^>]*>?/gm, '');
        cleanDescription = rawText.substring(0, 40) + (rawText.length > 40 ? '...' : '');
    }


    let html = `
      <div>
        <div style="font-weight:600; font-size:14px; margin-bottom:2px;">
          <small>Task</small><br> ${event.title}
        </div>
        <div style="font-size:12px; color:#6b7280;">
          ${event.project_name || ''}
        </div>
        <div style="font-size:12px; margin-bottom:2px;">
          ${statusMap[event.status] || ''}
        </div>
        <div style="font-size:12px; color:#2563eb; font-weight:500;">
        ${event.content_pillar || ''}
        </div>
        <div style="font-size:12px; color:#059669;">
          ${event.platform || ''}
        </div>
        <div style="font-size:12px; color:#374151; margin-top:4px; white-space:normal;">
          ${cleanDescription}
        </div>
      </div>
    `;
    element.find('.fc-content').html(html);
  },

  eventClick: function(event) {
    const encodedId = event.id;
    
    if (!encodedId) return;

    start_load(); // Asumsi start_load/end_load ada di scope global

    $.ajax({
      url: 'get_task_detail.php',
      method: 'POST',
      data: { id: encodedId }, 
      success: function(response) {
        end_load();
        $('#taskModal .modal-body').html(response);
        $('#taskModal').modal('show');
      },
      error: function() {
        end_load();
        Swal.fire('Error', 'Gagal mengambil data task.', 'error');
      }
    });
  }
});

// MODIFIKASI LENGKAP: MENGGUNAKAN SWEETALERT2 DAN MENGHAPUS alert_toast()
function delete_task(encodedId){
    // 1. Tampilkan konfirmasi SweetAlert2
    Swal.fire({
        title: 'Hapus Tugas?',
        text: "Anda yakin ingin menghapus tugas ini? Tindakan ini tidak dapat dibatalkan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            start_load();
            
            // 2. Panggil AJAX jika dikonfirmasi
            $.ajax({
                url: 'ajax.php?action=delete_task',
                method: 'POST',
                // Mengirim ID terenkripsi dengan kunci 'id'
                data: { id: encodedId },
                success: function(resp){
                    end_load();

                    // TIDAK ADA LAGI PANGGILAN alert_toast() di sini.
                    // Cukup reload halaman. PHP akan menampilkan SweetAlert2 dari header.php
                    // karena admin_class.php sudah mengatur $_SESSION['notification'] sebelum kembali.
                    
                    // Cek resp untuk kepastian (1: Sukses, 0/2: Gagal/Error dari backend)
                    // Tidak peduli sukses atau gagal, kita reload untuk menampilkan notifikasi sesi.
                    if (resp.trim() === '1' || resp.trim() === '0' || resp.trim() === '2') {
                         // Tunggu sejenak agar browser memproses penghapusan modal
                         setTimeout(() => location.reload(), 100); 
                    } else {
                         // Kasus jika respons dari server tidak terduga
                         Swal.fire('Error Respon', 'Gagal terhubung atau respon server tidak valid.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    end_load();
                    Swal.fire('Error Jaringan', 'Gagal memproses permintaan server: ' + error, 'error');
                }
            });
        }
    });
}
</script>