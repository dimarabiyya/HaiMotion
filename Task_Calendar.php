<?php
// task_calendar.php
include 'header.php';
?>
<!DOCTYPE html>
<html>
<head>
  <title>Task Calendar</title>

  <!-- CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

  <!-- JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
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
      background-color: #f78923ff;
      padding: 10px 0;
      font-weight: bold;
      color: white;
    }

    .fc-event {
        background-color: #ffffff !important; /* putih bersih */
        border: 1px solid #e5e7eb !important; /* abu muda */
        border-radius: 8px; /* lebih halus */
        padding: 6px 10px;
        font-weight: 500;
        font-size: 14px;
        color: #111827; /* teks abu gelap */
        text-align: left; /* seperti Notion */
        box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* efek card */
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        height: auto !important; /* biar menyesuaikan isi */
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
  </style>
</head>

<body>
  <div class="text-right mb-3">
    <button id="printCalendar" class="btn btn-danger">
      <i class="fa fa-file-pdf-o"></i> Export to PDF
    </button>
  </div>

  <div class="container-fluid mt-4 calendar-container border border-dark rounded">
    <div id="calendar"></div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="taskModal" tabindex="-1" role="dialog" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow">
        <div class="modal-header">
          <h5 class="modal-title" id="taskModalLabel">Task Detail</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          Loading...
        </div>
      </div>
    </div>
  </div>

  <!-- html2pdf.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
$('#calendar').fullCalendar({
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
  events: 'loadtask.php',

  eventRender: function(event, element) {
    // Mapping status ke badge bootstrap
    let statusMap = {
      0: '<span class="badge badge-secondary">Pending</span>',
      1: '<span class="badge badge-info">Started</span>',
      2: '<span class="badge badge-primary">On-Progress</span>',
      3: '<span class="badge badge-warning">Hold</span>',
      4: '<span class="badge badge-danger">Over Due</span>',
      5: '<span class="badge badge-success">Done</span>'
    };

    element.find('.fc-title').remove();

    // Masukkan custom HTML
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
          ${event.description ? event.description.substring(0,40) + '...' : ''}
        </div>
      </div>
    `;
    element.find('.fc-content').html(html);
  },

  eventClick: function(event) {
    $.ajax({
      url: 'get_task_detail.php',
      method: 'POST',
      data: { id: event.id },
      success: function(response) {
        $('#taskModal .modal-body').html(response);
        $('#taskModal').modal('show');
      },
      error: function() {
        Swal.fire('Error', 'Gagal mengambil data task.', 'error');
      }
    });
  }
});

$('#printCalendar').click(function() {
  const calendarElement = document.querySelector('.calendar-container');

  // Set ukuran area render ke rasio A4 landscape
  const originalWidth = calendarElement.scrollWidth;
  calendarElement.style.width = '1123px'; // kira-kira 11.69 inch * 96 dpi

  const opt = {
    margin:       [0.25, 0.25, 0.25, 0.25], // margin tipis biar penuh
    filename:     'Task_Calendar.pdf',
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  {
      scale: 2,
      useCORS: true,
      scrollY: 0, // hindari potongan karena scroll
      backgroundColor: '#ffffff' // biar gak transparan
    },
    jsPDF: {
      unit: 'in',
      format: [11.69, 8.27], // ukuran A4 landscape manual
      orientation: 'landscape'
    },
    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
  };

  html2pdf()
    .set(opt)
    .from(calendarElement)
    .save()
    .then(() => {
      // Kembalikan lebar asli agar layout web tidak rusak
      calendarElement.style.width = originalWidth + 'px';
    });
});

function delete_task(id){
    start_load();
    $.ajax({
        url: 'ajax.php?action=delete_task',
        method: 'POST',
        data: { id: id },
        success: function(resp){
            if(resp == 1){
                alert_toast("Task berhasil dihapus", "success");
                setTimeout(() => location.reload(), 1500);
            } else {
                alert_toast("Gagal menghapus task", "danger");
            }
        }
    });
}
</script>
</body>
</html>
