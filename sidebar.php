<?php include 'header.php' ?>
  
  <aside class="main-sidebar sidebar-light-dark elevation-3 main-sidebar sidebar-light-dark elevation-3 d-flex flex-column">
    <div class="ml-3 mt-5">
   	<a href="./" class="">
        <h3 class="text-center p-0 m-0">
          <img src="assets/Logo.png" id="logoFull" class="img-fluid d-block" style="width: 80%;" alt="Full Logo">
        </h3>  
        <h3 class="text-center p-0 m-0">
          <img src="assets/Logo1.png" id="logoMini" class="img-fluid d-none" style="width: 40px;" alt="Mini Logo">
        </h3>
    </a>
    <small ><p class="text-dark mt-3 text-center pr-3" style="text-decoration: none">PT Hai Motion Kreatif</p> </small>
    </div>

    <div class="sidebar pb-2 mb-4">
      <nav class=" mb-auto ml-auto">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item dropdown mb-2">
            <a href="./" class="nav-link nav-home ">
              <i class="nav-icon fas fa-th-large"></i>
              <p>
                Overview
              </p>
            </a>  
          </li> 
          <li class="nav-item mb-2">
            <a href="./index.php?page=project_list" class="nav-link nav-project_list">
              <i class="nav-icon fas fa-layer-group"></i>
              <p>
                Projects
              </p>
            </a>
          </li> 
          <li class="nav-item mb-2">
                <a href="./index.php?page=mytask" class="nav-link nav-mytask">
                  <i class="fas fa-clone nav-icon"></i>
                  <p>My Task</p>
                </a>
          </li> 
          <li class="nav-item mb-2">
                <a href="./index.php?page=task_list" class="nav-link nav-task_list">
                  <i class="fas fa-tasks nav-icon"></i>
                  <p>Task</p>
                </a>
          </li> 

          <li class="nav-item dropdown mb-2">
            <a href="./index.php?page=kanban" class="nav-link nav-kanban">
              <i class="nav-icon fas fa-clipboard"></i>
              <p>Kanban</p>
            </a>
          </li>  

          <?php if($_SESSION['login_type'] != 3): ?>
           <li class="nav-item mb-2">
                <a href="./index.php?page=reports" class="nav-link nav-reports">
                  <i class="fas fa-th-list nav-icon"></i>
                  <p>Report</p>
                </a>
          </li> 
          <?php endif; ?> 

          <li class="nav-item  mb-2">
            <a href="./index.php?page=calendar" class="nav-link nav-calendar">
              <i class="nav-icon fas fa-calendar"></i>
              <p>
                Calendar
              </p>
            </a>
          </li> 

          <li class="nav-item  mb-2">
            <a href="./index.php?page=Task_Calendar" class="nav-link nav-Task_Calendar">
              <i class="nav-icon fas fa-calendar-check"></i>
              <p>
                Task Calendar
              </p>
            </a>
          </li>

          <?php if($_SESSION['login_type'] == 1): ?>
          <li class="nav-item mb-2">
            <a href="./index.php?page=user_list" class="nav-link nav-user_list">
              <i class="nav-icon fas fa-users"></i>
              <p>
                Users
              </p>
            </a>
          </li>
            <?php endif; ?>
        </ul>
      </nav>
    </div>
  
    
     <div class="mt-auto p-2">
        <hr>
          <div class="nav-item dropdown mb-2 nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
            <div class="nav-item ">
                <a href="ajax.php?action=logout" class="nav-link  tree-item ">
                  
                  <i class="fa fa-power-off nav-icon"></i>
                  <p>Logout</p>
                </a>
            </div>
          </div>
      </div>
  </aside>

 

  <script>
  	$(document).ready(function(){
      var page = '<?php echo isset($_GET['page']) ? $_GET['page'] : 'home' ?>';
  		var s = '<?php echo isset($_GET['s']) ? $_GET['s'] : '' ?>';
      if(s!='')
        page = page+'_'+s;
      
      // === LOGIKA BARU UNTUK PROJECT AKTIF ===
      // Halaman yang dianggap sebagai bagian dari menu Projects
      var projectPages = ['project_list', 'view_project', 'new_project', 'edit_project'];
      
      if(projectPages.includes(page)){
          // Set Project link (nav-project_list) menjadi aktif
          $('.nav-link.nav-project_list').addClass('active');
          
          // Logika untuk membuka menu tree (jika Projects suatu hari punya submenu)
          if($('.nav-link.nav-project_list').hasClass('tree-item') == true){
            $('.nav-link.nav-project_list').closest('.nav-treeview').siblings('a').addClass('active')
            $('.nav-link.nav-project_list').closest('.nav-treeview').parent().addClass('menu-open')
          }
      } else if($('.nav-link.nav-'+page).length > 0){
          // Logika default untuk halaman lain
          $('.nav-link.nav-'+page).addClass('active')
          if($('.nav-link.nav-'+page).hasClass('tree-item') == true){
            $('.nav-link.nav-'+page).closest('.nav-treeview').siblings('a').addClass('active')
            $('.nav-link.nav-'+page).closest('.nav-treeview').parent().addClass('menu-open')
          }
        if($('.nav-link.nav-'+page).hasClass('nav-is-tree') == true){
          $('.nav-link.nav-'+page).parent().addClass('menu-open')
        }
      }
      // === AKHIR LOGIKA BARU ===
  	})

    document.getElementById('toggleSidebar').addEventListener('click', function () {
    const sidebar = document.getElementById('sidebar');
    const logoFull = document.getElementById('logoFull');
    const logoMini = document.getElementById('logoMini');

    sidebar.classList.toggle('collapsed');

    if (sidebar.classList.contains('collapsed')) {
      logoFull.classList.add('d-none');
      logoMini.classList.remove('d-none');
    } else {
      logoFull.classList.remove('d-none');
      logoMini.classList.add('d-none');
    }
  });
  </script>