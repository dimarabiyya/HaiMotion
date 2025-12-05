<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kalender</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css" />
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" />
  
  <link rel="stylesheet" href="assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  
  <style>
    html, body {
      height: 100%;
      margin: 0;
      background-color: #f8f9fa;
      font-family: 'Segoe UI', sans-serif;
    }
    .container-fluid {
      padding: 5px;
    }
    #calendar {
      background: #fff;
      border-radius: 8px;
      padding: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .fc-event.bg-primary,
    .fc-event.bg-success,
    .fc-event.bg-warning,
    .fc-event.bg-danger {
      color: #fff !important;
      height: 30px;
    }
    .fc-day-header {
      background-color: #B75301;
      padding: 10px 0;
      font-weight: bold;
      color: white;
    }
    /* PENTING: Untuk memastikan tombol SweetAlert2 rapi */
    .swal2-actions {
      justify-content: space-between !important;
    }
  </style>
</head>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1/dist/sweetalert2.all.min.js"></script>
<body>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Event Calendar</h3>
    <button id="addEventBtn" class="btn text-white font-weight-bold" style="background-color:#B75301;">+ Add Event</button>
  </div>
  <div id="calendar"></div>
</div>

<script>
$(document).ready(function () {
  let currentEventId = null;

  $('#calendar').fullCalendar({
    editable: true,
    selectable: true,
    eventResizableFromStart: true,
    eventDurationEditable: true,
    header: {
      left: 'prev,next today',
      center: 'title',
      right: 'month,agendaWeek,agendaDay'
    },
    events: 'load.php',

    select: function () {
      showEventModal();
    },

    eventClick: function (event) {
      currentEventId = event.id;
      showEventModal(event);
    },

    eventDrop: function (event) {
      updateEvent(event);
    },

    eventResize: function (event) {
      updateEvent(event);
    },

    eventRender: function (event, element) {
      if (event.className) {
        element.addClass(event.className);
      }
    }
  });

  $('#addEventBtn').click(() => {
    currentEventId = null;
    showEventModal();
  });

  function showEventModal(event = null) {
  const isEdit = !!event;

  Swal.fire({
  title: isEdit ? 'Edit Event' : 'Add Event',
  html: `
    <small><p class="text-left">Title Event</p></small>
    <input id="eventTitle" class="swal2-input" placeholder="Judul" value="${isEdit ? event.title : ''}">
    <form>
      <div class="row g-3">
        <div class="col-md-6">
          <small><p class="text-left">Start Date</p></small>
          <input id="eventStart" type="datetime-local" class="swal2-input" value="${isEdit ? moment(event.start).format('YYYY-MM-DDTHH:mm') : ''}">
        </div>
        <div class="col-md-6">
          <small><p class="text-left">End Date</p></small>
          <input id="eventEnd" type="datetime-local" class="swal2-input" value="${isEdit && event.end ? moment(event.end).format('YYYY-MM-DDTHH:mm') : ''}">
        </div>
      </div>
    </form>
    <small><p class="text-left">Description</p></small>
    <textarea id="eventDescription" class="swal2-textarea" placeholder="Deskripsi kegiatan">${isEdit ? (event.description || '') : ''}</textarea>
    <small><p class="text-left">Colour</p></small>
    <input id="eventColor" class="swal2-input" list="colors" value="${isEdit ? (event.className && event.className.length > 0 ? event.className[0] : 'bg-primary') : 'bg-primary'}" placeholder="Choose Color">
    <datalist id="colors">
      <option value="bg-primary">Blue</option>
      <option value="bg-success">Green</option>
      <option value="bg-warning">Yellow</option>
      <option value="bg-danger">Red</option>
    </datalist>
  `,
  showCancelButton: true, // Tombol Batal
  showDenyButton: isEdit, // Tombol Delete
  showConfirmButton: true,
  confirmButtonText: isEdit ? 'Save' : 'Add',
  denyButtonText: 'Delete Event',
  reverseButtons: true,

  preConfirm: () => {
    const title = $('#eventTitle').val();
    const color = $('#eventColor').val();
    const start = $('#eventStart').val();
    const end = $('#eventEnd').val();
    const description = $('#eventDescription').val();
    if (!title || !color || !start || !end) {
      Swal.showValidationMessage('Anda harus mengisi semua kolom wajib!');
      return false;
    }
    return { title, color, start, end, description };
  }
}).then((result) => {
  // Aksi Simpan/Tambah
  if (result.isConfirmed) {
    const data = {
      title: result.value.title,
      color: result.value.color,
      start: result.value.start,
      end: result.value.end,
      description: result.value.description
    };
    
    let url = 'insert.php';
    if (isEdit) {
      url = 'update.php';
      data.id = event.id;
    }
    
    $.post(url, data, function () {
      // *** MODIFIKASI PENTING: Hanya panggil reload ***
      // Ini akan memicu SweetAlert2 dari sesi di header.php
      location.reload(); 
    }).fail(function() {
        // Tampilkan error jika AJAX gagal
        Swal.fire('Error', 'Gagal terhubung ke server untuk menyimpan event.', 'error');
    });
  } 
  // Aksi Hapus (jika tombol Deny ditekan)
  else if (result.isDenied && isEdit) {
     confirmDelete(event.id);
  }
});
}

  function confirmDelete(id) {
    Swal.fire({
      title: 'Hapus Event?',
      text: 'Anda yakin ingin menghapus event ini? Tindakan ini tidak dapat dibatalkan!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
      reverseButtons: true,
    }).then(result => {
      if (result.isConfirmed) {
        $.ajax({
          url: 'delete.php',
          type: 'POST',
          data: { id: id },
          success: function () {
            // *** MODIFIKASI PENTING: Hanya panggil reload ***
            // Ini akan memicu SweetAlert2 dari sesi di header.php
            location.reload(); 
          },
          error: function () {
            // Tampilkan error SweetAlert2 jika AJAX gagal 
            Swal.fire('Error Jaringan', 'Gagal terhubung ke server untuk menghapus event.', 'error');
          }
        });
      }
    });
  }

  function updateEvent(event) {
    const data = {
      id: event.id,
      title: event.title,
      // Asumsi event.className adalah array, ambil elemen pertama
      color: event.className && event.className.length > 0 ? event.className[0] : 'bg-primary', 
      start: moment(event.start).format('YYYY-MM-DD HH:mm:ss'),
      end: moment(event.end).format('YYYY-MM-DD HH:mm:ss')
    };
    
    // Untuk drag and drop / resize, biarkan tanpa notifikasi pop-up yang mengganggu.
    // Hanya refetch events untuk memastikan tampilan diperbarui.
    $.post('update.php', data, function (response) {
      // Asumsi: Jika respons adalah '1' atau sukses, refetch events
      if(response.trim() === '1') {
          $('#calendar').fullCalendar('refetchEvents');
      } else {
          console.error("Gagal update event via drag/resize:", response);
      }
    }).fail(function() {
        console.error("AJAX error during event drop/resize.");
    });
  }
});
</script>

</body>
</html>