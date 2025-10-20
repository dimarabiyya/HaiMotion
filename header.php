<?php 
// Pastikan sesi dimulai sebelum header dimuat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Tambahkan pengaturan timezone default jika belum ada
if (!ini_get('date.timezone')) {
    date_default_timezone_set("Asia/Jakarta");
}
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php 
    $title = isset($title) ? $title : "Project Management System";
    // Ambil variabel sistem dari sesi
    $system_name = isset($_SESSION['system']['name']) ? $_SESSION['system']['name'] : "HaiMotion";
    ?>
    <title><?php echo $title ?> | <?php echo $system_name ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="assets/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/font-awesome/css/all.min.css"> 
    <link rel="stylesheet" href="assets/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="assets/plugins/toastr/toastr.min.css">
    <link rel="stylesheet" href="assets/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <link rel="stylesheet" href="assets/plugins/bootstrap4-toggle/css/bootstrap4-toggle.min.css">
    <link rel="stylesheet" href="assets/plugins/summernote/summernote-bs4.min.css"> 
    
    <link rel="stylesheet" href="assets/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/dist/css/styles.css">
    <link rel="stylesheet" href="css/dark-mode.css">  
    <link href="assets/css/style.css" rel="stylesheet"> 
    
    <script src="assets/plugins/jquery/jquery.min.js"></script>
    <script src="assets/plugins/jquery-ui/jquery-ui.min.js"></script>
    
    <script src="assets/plugins/summernote/summernote-bs4.min.js"></script> 
    <script src="assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="assets/plugins/toastr/toastr.min.js"></script>
    <script src="assets/plugins/bootstrap4-toggle/js/bootstrap4-toggle.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.4.0/fullcalendar.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.4.0/fullcalendar.css" />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>

    <script type="text/javascript" src="assets/js/jquery-te-1.4.0.min.js" charset="utf-8"></script>
    
    <link rel="icon" type="image/png" href="assets/logobw.png">
    
 </head>