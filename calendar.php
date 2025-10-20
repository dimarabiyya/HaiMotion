<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kalender</title>

  <!-- FullCalendar & Bootstrap -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css" />
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  <!-- SweetAlert2 v11 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" />

    <!-- Tambahkan di <head> -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <!-- Tambahkan sebelum </body> -->
  

  
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
      background-color: #f78923;
      padding: 10px 0;
      font-weight: bold;
      color: white;
    }
    .swal2-actions {
      justify-content: space-between !important;
    }
  </style>
</head>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<body>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Kalender Kegiatan</h3>
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
    <input id="eventColor" class="swal2-input" list="colors" value="${isEdit ? event.className[0] : ''}" placeholder="Choose Color">
    <datalist id="colors">
      <option value="bg-primary">Blue</option>
      <option value="bg-success">Green</option>
      <option value="bg-warning">Yellow</option>
      <option value="bg-danger">Red</option>
    </datalist>
  `,
  showCancelButton: isEdit,
  showConfirmButton: true,
  confirmButtonText: isEdit ? 'Save' : 'Add',
  cancelButtonText: isEdit ? 'Delete' : null,
  reverseButtons: true,

  preConfirm: () => {
    const title = $('#eventTitle').val();
    const color = $('#eventColor').val();
    const start = $('#eventStart').val();
    const end = $('#eventEnd').val();
    const description = $('#eventDescription').val();
    if (!title || !color || !start || !end) {
      Swal.showValidationMessage('You must answer all fields!');
      return false;
    }
    return { title, color, start, end, description };
  }
}).then((result) => {
  if (result.isConfirmed) {
    const data = {
      title: result.value.title,
      color: result.value.color,
      start: result.value.start,
      end: result.value.end,
      description: result.value.description
    };
    if (isEdit) {
      data.id = event.id;
      $.post('update.php', data, function () {
        $('#calendar').fullCalendar('refetchEvents');
        Swal.fire('Done!', 'Event Updated.', 'success');
      });
    } else {
      $.post('insert.php', data, function () {
        $('#calendar').fullCalendar('refetchEvents');
        Swal.fire('Done!', 'Event Created.', 'success');
      });
    }
  } else if (result.dismiss === Swal.DismissReason.cancel && isEdit) {
    confirmDelete(event.id);
  }
});
}

  function confirmDelete(id) {
    Swal.fire({
      title: 'Do you realy want to delete event?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Delete',
      cancelButtonText: 'Cancel',
      reverseButtons: true,
    }).then(result => {
      if (result.isConfirmed) {
        $.ajax({
          url: 'delete.php',
          type: 'POST',
          data: { id: id },
          success: function () {
            $('#calendar').fullCalendar('refetchEvents');
            Swal.fire('Done!', 'Event Deleted.', 'success');
          },
          error: function () {
            Swal.fire('Warning', 'cant Delete event', 'error');
          }
        });
      }
    });
  }

  function updateEvent(event) {
    const data = {
      id: event.id,
      title: event.title,
      color: event.className[0],
      start: moment(event.start).format('YYYY-MM-DD HH:mm:ss'),
      end: moment(event.end).format('YYYY-MM-DD HH:mm:ss')
    };
    $.post('update.php', data, function () {
      $('#calendar').fullCalendar('refetchEvents');
    });
  }
});
</script>

</body>
</html>
