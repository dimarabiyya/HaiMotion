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
});
</script>