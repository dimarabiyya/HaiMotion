<!DOCTYPE html>
<html lang="en">
<?php session_start() ?>
<?php 
  if(!isset($_SESSION['login_id']))
      header('location:login.php');
    include 'db_connect.php';
    ob_start();
    
    // =======================================================
    //  KODE KEAMANAN PROJECT HEADER CHECK 
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
  
  </div>

  <?php include 'new_task.php'; ?>
  
  <!-- Speed Dial Floating Button -->
  <div class="speed-dial-container">
    <!-- Speed Dial Options (Must be BEFORE main button for proper stacking) -->
    <div class="speed-dial-options" id="speedDialOptions">
      <?php if($_SESSION['login_type'] == 1): ?>
      <a href="javascript:void(0)" class="speed-dial-action" data-label="Add User" onclick="closeSpeedDial(); window.location.href='index.php?page=new_user'">
        <i class="fa fa-user-plus"></i>
      </a>
      <?php endif; ?>
      
      <a a href="javascript:void(0)" class="speed-dial-action" data-label="Messenger" onclick="closeSpeedDial(); window.location.href='index.php?page=chat'">
        <i class="fa fa-comment"></i>
      </a>
      
      <?php if($_SESSION['login_type'] == 1 || $_SESSION['login_type'] == 2): ?>
      <a href="javascript:void(0)" class="speed-dial-action" data-label="Add Project" onclick="closeSpeedDial(); window.location.href='index.php?page=new_project'">
        <i class="fa fa-folder-open"></i>
      </a>
      <?php endif; ?>
      
      <?php if($_SESSION['login_type'] == 1 || $_SESSION['login_type'] == 2): ?>
      <a href="#addTaskModal" class="speed-dial-action" data-toggle="modal" data-label="Add Task" onclick="closeSpeedDial()">
        <i class="fa fa-tasks"></i>
      </a>
      <?php endif; ?>
    </div>
    
    <!-- Main Floating Button -->
    <button class="float speed-dial-main" id="speedDialBtn" title="Add New">
      <i class="fa fa-plus my-float"></i>
    </button>
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
    <strong style="color:#B75301;">&copy; 2025 
        <a style="color:#B75301;">PT HAI MOTION KREATIF</a>
    </strong>
    All rights reserved.
</footer>
</div>

<style>
  /* ============================================== */
  /* SPEED DIAL FLOATING BUTTON STYLES             */
  /* ============================================== */
  
  .speed-dial-container {
    position: fixed;
    bottom: 60px;
    right: 40px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0;
  }
  
  /* Speed Dial Options Container */
  .speed-dial-options {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    margin-bottom: 12px;
    opacity: 0;
    visibility: hidden;
    transform: scale(0.5);
    transform-origin: bottom right;
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    pointer-events: none;
  }
  
  .speed-dial-options.active {
    opacity: 1;
    visibility: visible;
    transform: scale(1);
    pointer-events: auto;
  }
  
  /* Individual Speed Dial Action Buttons */
  .speed-dial-action {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    background-color: #fff;
    color: #B75301;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    text-decoration: none;
    transition: all 0.3s ease;
    opacity: 0;
    transform: translateY(10px);
  }
  
  .speed-dial-options.active .speed-dial-action {
    opacity: 1;
    transform: translateY(0);
  }
  
  .speed-dial-options.active .speed-dial-action:nth-child(1) {
    transition-delay: 0.05s;
  }
  
  .speed-dial-options.active .speed-dial-action:nth-child(2) {
    transition-delay: 0.1s;
  }
  
  .speed-dial-options.active .speed-dial-action:nth-child(3) {
    transition-delay: 0.15s;
  }
  
  .speed-dial-options.active .speed-dial-action:nth-child(4) {
    transition-delay: 0.2s;
  }
  
  .speed-dial-action:hover {
    background-color: #B75301;
    color: #fff;
    transform: translateY(0) scale(1.1);
    box-shadow: 0 4px 12px rgba(183, 83, 1, 0.4);
  }
  
  .speed-dial-action i {
    font-size: 18px;
  }
  
  /* Tooltip Label */
  .speed-dial-action::before {
    content: attr(data-label);
    position: absolute;
    right: 60px;
    background-color: #2c3e50;
    color: #fff;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transform: translateX(10px);
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    pointer-events: none;
    z-index: 10;
  }
  
  .speed-dial-action::after {
    content: '';
    position: absolute;
    right: 52px;
    border: 6px solid transparent;
    border-left-color: #2c3e50;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 10;
  }
  
  .speed-dial-action:hover::before,
  .speed-dial-action:hover::after {
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
  }
  
  /* Main Floating Button */
  .float {
    position: relative;
    width: 60px;
    height: 60px;
    background-color: #B75301;
    color: #FFF;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(183, 83, 1, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 1001;
  }

  .float:hover {
    background-color: #8f4001;
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(183, 83, 1, 0.6);
  }
  
  .float.active {
    transform: rotate(45deg);
    background-color: #8f4001;
  }

  .my-float {
    font-size: 22px;
    line-height: 1;
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .fa-plus:before {
    content: "\f067";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    display: block;
    line-height: 1;
  }
  
  /* Mobile Responsive */
  @media (max-width: 768px) {
    .speed-dial-container {
      bottom: 70px;
      right: 20px;
    }
    
    .float {
      width: 56px;
      height: 56px;
    }
    
    .my-float {
      font-size: 20px;
    }
    
    .speed-dial-action {
      width: 46px;
      height: 46px;
    }
    
    .speed-dial-action i {
      font-size: 17px;
    }
    
    .speed-dial-action::before {
      font-size: 12px;
      padding: 6px 10px;
    }
  }
  
  @media (max-width: 576px) {
    .speed-dial-container {
      bottom: 60px;
      right: 15px;
    }
    
    .float {
      width: 52px;
      height: 52px;
    }
    
    .my-float {
      font-size: 18px;
    }
    .speed-dial-action {
      width: 44px;
      height: 44px;
    }
    
    .speed-dial-action i {
      font-size: 16px;
    }
  }
  
  /* FORCE MODAL CENTER ON MOBILE */
  @media (max-width: 768px) {
    #uni_modal .modal-dialog {
      max-width: 90vw !important;
      width: 90vw !important;
      margin: 1rem auto !important;
      position: relative !important;
      transform: none !important;
      left: 0 !important;
      right: 0 !important;
    }
    
    #uni_modal .modal-content {
      width: 100% !important;
      margin: 0 auto !important;
    }
  }
</style>

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
    
    // Force close sidebar on page load for mobile
    if (window.innerWidth <= 991) {
      $('body').removeClass('sidebar-open');
      $('body').removeClass('sidebar-collapse');
    }
    
    // ============================================
    // SPEED DIAL TOGGLE FUNCTIONALITY
    // ============================================
    $('#speedDialBtn').on('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const options = $('#speedDialOptions');
      const button = $(this);
      
      // Toggle active state
      button.toggleClass('active');
      options.toggleClass('active');
    });
    
    // Close speed dial when clicking outside
    $(document).on('click', function(e) {
      const speedDialContainer = $('.speed-dial-container');
      
      if (!speedDialContainer.is(e.target) && speedDialContainer.has(e.target).length === 0) {
        $('#speedDialBtn').removeClass('active');
        $('#speedDialOptions').removeClass('active');
      }
    });
    
    // Function to close speed dial (called from action buttons)
    window.closeSpeedDial = function() {
      $('#speedDialBtn').removeClass('active');
      $('#speedDialOptions').removeClass('active');
    };

  });
</script>	


<?php include 'footer.php' ?>
</body>
</html>