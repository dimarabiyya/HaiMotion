<!DOCTYPE html>
<html lang="en">
<?php session_start() ?>
<?php 
  if(!isset($_SESSION['login_id']))
      header('location:login.php');
    include 'db_connect.php';
    ob_start();
    
    // =======================================================
    // 🚨 KODE KEAMANAN PROJECT HEADER CHECK 🚨
    // =======================================================
    $page = $_GET['page'] ?? 'home';
    if ($page == 'view_project' || $page == 'edit_project') {
        $obfuscated_id = $_GET['id'] ?? null;
        $project_id = decode_id($obfuscated_id);
        $user_login_id = $_SESSION['login_id'] ?? 0;
        $user_login_type = $_SESSION['login_type'] ?? 0;

        // A. Cek ID tidak valid (ID disamarkan salah)
        if (is_null($project_id)) {
            header("Location: index.php?page=404");
            exit;
        }

        // B. Ambil data verifikasi
        $qry_check = $conn->query("SELECT manager_id, user_ids FROM project_list WHERE id = $project_id");
        $proj_check = $qry_check->fetch_assoc();

        if (!$proj_check) {
            header("Location: index.php?page=404");
            exit;
        }

        // C. Verifikasi Otorisasi
        $is_member = isset($proj_check['user_ids']) && in_array($user_login_id, explode(',', $proj_check['user_ids']));

        $is_authorized = ($user_login_type == 1) || // Admin
                         ($proj_check['manager_id'] == $user_login_id) || // Manager
                         $is_member; // Anggota Proyek
        
        // D. TOLAK AKSES
        if (!$is_authorized) {
            header("Location: index.php?page=access_denied"); // Asumsi Anda punya access_denied.php
            exit;
        }
        
        // E. Simpan ID numerik yang aman kembali ke $_GET
        $_GET['id'] = $project_id;
    }
    // =======================================================
    // 🚨 AKHIR KODE KEAMANAN
    // =======================================================

  if(!isset($_SESSION['system'])){

    $system = $conn->query("SELECT * FROM system_settings")->fetch_array();
    foreach($system as $k => $v){
      $_SESSION['system'][$k] = $v;
    }
  }
  ob_end_flush();
  include 'header.php'  
?>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">
  <?php include 'topbar.php' ?>
  <?php include 'sidebar.php' ?>

  <div class="content-wrapper">
  	 <div class="toast" id="alert_toast" role="alert" aria-live="assertive" aria-atomic="true">
	    <div class="toast-body text-white">
	    </div>
	  </div>
    <div id="toastsContainerTopRight" class="toasts-top-right fixed"></div>
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0"></h1>
          </div></div></div></div>
    <section class="content">
      <div class="container-fluid">
         <?php 
            $page = isset($_GET['page']) ? $_GET['page'] : 'home';
            if(!file_exists($page.".php")){
                include '404.html';
            }else{
            include $page.'.php';
            }
          ?>
      </div></section>
    <div class="modal fade" id="confirm_modal" role='dialog'>
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title">Confirmation</h5>
      </div>
      <div class="modal-body">
        <div id="delete_content"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id='confirm' onclick="">Continue</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="uni_modal" role='dialog'>
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title"></h5>
      </div>
      <div class="modal-body">
      </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="uni_modal_right" role='dialog'>
    <div class="modal-dialog modal-full-height  modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span class="fa fa-arrow-right"></span>
        </button>
      </div>
      <div class="modal-body">
      </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="viewer_modal" role='dialog'>
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
              <button type="button" class="btn-close" data-dismiss="modal"><span class="fa fa-times"></span></button>
              <img src="" alt="">
      </div>
    </div>
  </div>
  </div>
  <aside class="control-sidebar control-sidebar-dark">
    </aside>
  <footer class="main-footer" style="
    color: #000;
    background-color: white;
    background-image: url('assets/Asset4.png');
    background-repeat: no-repeat;
    background-position: right center;
    background-size: contain;
    padding: 10px;
">
    <strong>&copy; 2025 
        <a style="color:#B75301;">PT HAI MOTION KREATIF</a>
    </strong>
    All rights reserved.
</footer>
</div>

<script>
	 window.start_load = function(){
    $('body').prepend('<di id="preloader2"></di>')
  }
  window.end_load = function(){
    $('#preloader2').fadeOut('fast', function() {
        $(this).remove();
      })
  }

  window.uni_modal = function($title = '' , $url=''){
    start_load()
    $.ajax({
        url:$url,
        error:err=>{
            console.log()
            alert("An error occured")
        },
        success:function(resp){
            if(resp){
                $('#uni_modal .modal-title').html($title)
                $('#uni_modal .modal-body').html(resp)
                $('#uni_modal').modal('show')
                end_load()
            }
        }
    })
}
window._conf = function($msg='',$func='',$params = []){
     $('#confirm_modal #confirm').attr('onclick',$func+"("+$params.join(',')+")")
     $('#confirm_modal .modal-body').html($msg)
     $('#confirm_modal').modal('show')
  }
   window.alert_toast= function($msg = 'TEST',$bg = 'success'){
      $('#alert_toast').removeClass('bg-success')
      $('#alert_toast').removeClass('bg-danger')
      $('#alert_toast').removeClass('bg-info')
      $('#alert_toast').removeClass('bg-warning')

    if($bg == 'success')
      $('#alert_toast').addClass('bg-success')
    if($bg == 'danger')
      $('#alert_toast').addClass('bg-danger')
    if($bg == 'info')
      $('#alert_toast').addClass('bg-info')
    if($bg == 'warning')
      $('#alert_toast').addClass('bg-warning')
    $('#alert_toast .toast-body').html($msg)
    $('#alert_toast').toast({delay:3000}).toast('show');
  }
  $(document).ready(function(){
    $('#preloader').fadeOut('fast', function() {
        $(this).remove();
      })
  })
</script>	


<?php include 'footer.php' ?>
</body>
</html>