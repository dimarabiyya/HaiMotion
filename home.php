<?php include('db_connect.php') ?>
<?php
$twhere ="";
if($_SESSION['login_type'] != 1)
  $twhere = "  ";
?>

<?php
  if (!function_exists('encode_id')) {
    function encode_id($id) {
        return base64_encode($id);
    }
}
?>
<?php 
include 'header.php' 
?>

<head>
  <link rel="stylesheet" href="css/style.css">
</head>
<div class="col-12">    
  <h3 class="font-weight-bold" style="color:#b75301;">
    Hi, <?php echo $_SESSION['login_name'] ?>! 
  </h3>
  <p >Let's finish your taks today! </p>
  </div>
  <hr>
  
  <?php 
    $where = "";
    if($_SESSION['login_type'] == 2){
      $where = " where manager_id = '{$_SESSION['login_id']}' ";
    }elseif($_SESSION['login_type'] == 3){  
      $where = " where concat('[',REPLACE(user_ids,',','],['),']') LIKE '%[{$_SESSION['login_id']}]%' ";
    }
     $where2 = "";
    if($_SESSION['login_type'] == 2){
      $where2 = " where p.manager_id = '{$_SESSION['login_id']}' ";
    }elseif($_SESSION['login_type'] == 3){
      $where2 = " where concat('[',REPLACE(p.user_ids,',','],['),']') LIKE '%[{$_SESSION['login_id']}]%' ";
    }
  ?>

<div class="container-fluid">
  <div class="row">
    <div class="col">
      <?php if($_SESSION['login_type'] == 1): ?>
      <a href="index.php?page=user_list" class="small-box bg-light shadow-sm border p-2 d-block text-dark">
      <?php else: ?>
      <div class="small-box bg-light shadow-sm border p-2"> <?php endif; ?>
              <div class="inner">
                <h3><?php echo $conn->query('SELECT * FROM users')->num_rows ?></h3>
                <p class="mb-0">Total Users</p>
              </div>
              <div class="icon">
                <i class="fa fa-solid fa-users" style="color:#d49867;"></i>
              </div>
      <?php if($_SESSION['login_type'] == 1): ?>
      </a>
      <?php else: ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="col">
      <a href="index.php?page=project_list" class="small-box bg-light shadow-sm border p-2 d-block text-dark">
              <div class="inner">
                <h3><?php echo $conn->query("SELECT * FROM project_list $where")->num_rows; ?></h3>
                <p class="mb-0">Total Projects</p>
              </div>
              <div class="icon">
                <i class="fa fa-solid fa-folder-open" style="color:#d49867;"></i>
              </div>
      </a>
    </div>

    <div class="col">
      <a href="index.php?page=task_list" class="small-box bg-light shadow-sm border p-2 d-block text-dark">
              <div class="inner">
                <h3><?php echo $conn->query("SELECT t.*,p.name as pname,p.start_date,p.status as pstatus, p.end_date,p.id as pid FROM task_list t inner join project_list p on p.id = t.project_id $where2")->num_rows; ?></h3>
                <p class="mb-0">Total Tasks</p>
              </div>
              <div class="icon">
                <i class="fa fa-solid fa-tasks" style="color:#d49867;"></i>
              </div>
      </a>
    </div>
  </div>
</div>

<div class="container-fluid">
  <div class="row">
    <div class="col-6 col-md custom-width-20 col-sm-6 mb-3">
      <a href="index.php?page=task_list&status=0" class="small-box bg-light shadow-sm border p-2 text-center d-block text-dark">
        <div class="inner">
          <h3>
            <?php echo $conn->query("SELECT t.*, p.name as pname, p.start_date, p.status as pstatus, p.end_date, p.id as pid FROM task_list t INNER JOIN project_list p ON p.id = t.project_id $where2 AND t.status = 0")->num_rows; ?>
          </h3>
          <p>Task Pending</p>
        </div>
      </a>
    </div>

    <div class="col-6 col-md custom-width-20 col-sm-6 mb-3">
      <a href="index.php?page=task_list&status=1" class="small-box bg-light shadow-sm border p-2 text-center d-block text-dark">
        <div class="inner">
          <h3>
            <?php echo $conn->query("SELECT t.*, p.name as pname, p.start_date, p.status as pstatus, p.end_date, p.id as pid FROM task_list t INNER JOIN project_list p ON p.id = t.project_id $where2 AND t.status = 1")->num_rows; ?>
          </h3>
          <p>Task Started</p>
        </div>
      </a>
    </div>

    <div class="col-6 col-md custom-width-20 col-sm-6 mb-3">
      <a href="index.php?page=task_list&status=2" class="small-box bg-light shadow-sm border p-2 text-center d-block text-dark">
        <div class="inner">
          <h3>
            <?php echo $conn->query("SELECT t.*, p.name as pname, p.start_date, p.status as pstatus, p.end_date, p.id as pid FROM task_list t INNER JOIN project_list p ON p.id = t.project_id $where2 AND t.status = 2")->num_rows; ?>
          </h3>
          <p>Task On-Progress</p>
        </div>
      </a>
    </div>

    <div class="col-6 col-md custom-width-20 col-sm-6 mb-3">
      <a href="index.php?page=task_list&status=3" class="small-box bg-light shadow-sm border p-2 text-center">
        <div class="inner">
          <h3>
            <?php echo $conn->query("SELECT t.*, p.name as pname, p.start_date, p.status as pstatus, p.end_date, p.id as pid FROM task_list t INNER JOIN project_list p ON p.id = t.project_id $where2 AND t.status = 3")->num_rows; ?>
          </h3>
          <p>Task On-Hold</p>
        </div>
      </a>
    </div>

    <div class="col-6 col-md custom-width-20 col-sm-6 mb-3">
      <a href="index.php?page=task_list&status=4" class="small-box bg-light shadow-sm border p-2 text-center">
        <div class="inner">
          <h3>
            <?php echo $conn->query("SELECT t.*, p.name as pname, p.start_date, p.status as pstatus, p.end_date, p.id as pid FROM task_list t INNER JOIN project_list p ON p.id = t.project_id $where2 AND t.status = 4")->num_rows; ?>
          </h3>
          <p>Task Overdue</p>
        </div>
      </a>
    </div>

    <div class="col-6 col-md custom-width-20 col-sm-6 mb-3">
      <a href="index.php?page=task_list&status=5" class="small-box bg-light shadow-sm border p-2 text-center">
        <div class="inner">
          <h3>
            <?php echo $conn->query("SELECT t.*, p.name as pname, p.start_date, p.status as pstatus, p.end_date, p.id as pid FROM task_list t INNER JOIN project_list p ON p.id = t.project_id $where2 AND t.status = 5")->num_rows; ?>
          </h3>
          <p>Task Done</p>
        </div>
      </a>
    </div>
  </div>
</div>

<div class="container-fluid">
  <div class="row">
    <div class="col-md-8 mb-4">
      <div class="card card-outline shadow-sm h-70">
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
          <b>Project Progress</b>
        </div>

        <div class="card-body" style="max-height: 600px; overflow-y: auto;">
          <div class="mb-3">
            <span class="badge badge-secondary">Pending</span>
            <span class="badge badge-info">Started</span>
            <span class="badge badge-primary">On-Progress</span>
            <span class="badge badge-warning">On-Hold</span>
            <span class="badge badge-danger">Over Due</span>
            <span class="badge badge-success">Done</span>
          </div>

          <div class="d-flex overflow-auto" style="gap: 1rem; padding-bottom: .5rem;">
            <?php
            $chart_scripts = "";
            $qry = $conn->query("SELECT * FROM project_list $where ORDER BY name ASC");
            while ($row = $qry->fetch_assoc()):
              $task_counts = [0, 0, 0, 0, 0, 0];
              $tasks = $conn->query("SELECT status FROM task_list WHERE project_id = {$row['id']}");
              while ($task = $tasks->fetch_assoc()) {
                $s = intval($task['status']);
                if (isset($task_counts[$s])) $task_counts[$s]++;
              }
              $chart_id = "chart_" . $row['id'];
              $encoded_proj_id = encode_id($row['id']); // Encoding ID di sini
            ?>
              <a href="index.php?page=view_project&id=<?php echo $encoded_proj_id; ?>" class="card p-3 shadow-sm project-card" data-id="<?php echo $row['id'] ?>" style="min-width: 280px; cursor: pointer; text-decoration: none; color: inherit;">
                <h6 class="font-weight-bold text-truncate"><?php echo ucwords($row['name']) ?></h6>
                <p class="mb-2 text-muted small">Due: <?php echo date("d M Y", strtotime($row['end_date'])) ?></p>
                <canvas id="<?php echo $chart_id ?>" height="180"></canvas>
              </a>
                <?php
                $chart_scripts .= "<script>
                document.addEventListener('DOMContentLoaded', function() {
                  var ctx = document.getElementById('{$chart_id}').getContext('2d');
                  new Chart(ctx, {
                    type: 'pie',
                    data: {
                      labels: ['Pending','Started','On-Progress','On-Hold','Over Due','Done'],
                      datasets: [{
                        data: [{$task_counts[0]}, {$task_counts[1]}, {$task_counts[2]}, {$task_counts[3]}, {$task_counts[4]}, {$task_counts[5]}],
                        backgroundColor: [
                          '#6c757d','#17a2b8','#007bff','#ffc107','#dc3545','#28a745'
                        ],
                        borderWidth: 1
                      }]
                    },
                      options: {
                              responsive: true,
                              plugins: {
                                  legend: {
                                      display: false 
                                  },
                                  tooltip: {
                                      enabled: true
                                  },
                              }
                      }
                  });
                });
                </script>";
            endwhile;
            echo $chart_scripts;
            ?>
          </div>
        </div>
      </div>
    </div>
 
    <div class="col-md-4">
      <div class="card card-outline shadow-sm h-70">
        <div class="card-header py-2">
          <b>Recent Activities</b>
        </div>
    
        <div class="card-body py-2" style="max-height: 440px; overflow-y: auto;">
          <ul class="timeline list-unstyled position-relative pl-3 mb-0">
            <?php
            // Pastikan variabel session tersedia
            $user_id = $_SESSION['login_id'];
            $login_type = $_SESSION['login_type'];
            
            $activity_where = " WHERE 1=1 "; // Default untuk Admin (Role 1)
    
            if ($login_type == 2) {
                // Project Manager (Role 2): Hanya melihat aktivitas di proyek yang dia kelola
                $activity_where .= " AND a.project_id IN (
                                        SELECT id FROM project_list WHERE manager_id = '$user_id'
                                    ) ";
            } elseif ($login_type == 3) {
                // User (Role 3): Hanya melihat aktivitas yang melibatkan dirinya
                // Yaitu aktivitas yang dia buat (a.user_id = $user_id)
                $activity_where .= " AND a.user_id = '$user_id' ";
            }
    
            $logs = $conn->query("
              SELECT DISTINCT a.*,             
                    u.firstname, u.lastname, u.avatar, 
                    p.name AS project_name, 
                    t.task AS task_name,
                    t.status AS task_status
              FROM activity_log a
              LEFT JOIN users u ON a.user_id = u.id
              LEFT JOIN project_list p ON a.project_id = p.id
              LEFT JOIN task_list t ON a.task_id = t.id
              $activity_where 
              ORDER BY a.created_at DESC
            ");
            
            if ($logs && $logs->num_rows > 0):
              while ($log = $logs->fetch_assoc()):
                $avatar = !empty($log['avatar']) ? 'assets/uploads/'.$log['avatar'] : 'assets/logo.png';
    
                  // Warna badge berdasarkan status task (1–5)
                // Asumsi: Status 1=Started, 2=On-Progress, 3=On-Hold, 4=Over Due, 5=Done
                switch ((int)$log['task_status']) {
                  case 5: $color = '#4c9a2a'; break; // Done - hijau
                  case 4: $color = '#c62828'; break; // Over Due - merah
                  case 3: $color = '#e66a00'; break; // On-Hold - kuning
                  case 2: $color = '#95c0dc'; break; // On-Progress - biru
                  case 1: $color = '#f3dc80'; break; // Started - biru muda
                  default: $color = '#3a495c'; break; // Pending/lainnya - abu
                }
            ?>
              <li class="timeline-item">
                <span class="timeline-badge" style="background: <?= $color ?>;"></span>
                <div class="d-flex align-items-center mb-1">
                  <img src="<?= $avatar ?>" class="avatar" alt="Avatar" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%; margin-right: 8px;">
                  <div>
                    <strong><?= ucwords($log['firstname'] . ' ' . $log['lastname']) ?></strong><br>
                    <small class="text-muted"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></small>
                  </div>
                </div>
                <div class="timeline-content">
                  <span><?= htmlspecialchars($log['description']) ?></span>
                  <?php if (!empty($log['project_name'])): ?>
                    <br><small class="text-muted">Project: <?= $log['project_name'] ?></small>
                  <?php endif; ?>
                </div>
              </li>
            <?php 
              endwhile;
            else: ?>
              <p class="text-muted text-center py-3">No Activities</p>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
    
<?php
// Filter project sesuai role login
$where = "";
if($_SESSION['login_type'] == 2){
  $where = " WHERE manager_id = '{$_SESSION['login_id']}' ";
}elseif($_SESSION['login_type'] == 3){
  $where = " WHERE concat('[',REPLACE(user_ids,',','],['),']') LIKE '%[{$_SESSION['login_id']}]%' ";
}

// Ambil semua project sesuai filter
$projects = $conn->query("SELECT * FROM project_list $where ORDER BY name ASC");

$project_stats = [];
while($proj = $projects->fetch_assoc()){
    $pid = $proj['id'];

    // Total task
    $total_tasks = $conn->query("SELECT COUNT(*) as total FROM task_list WHERE project_id = $pid")->fetch_assoc()['total'];

    // Task by Status
    $status_data = [];
    $res = $conn->query("SELECT status, COUNT(*) as total FROM task_list WHERE project_id = $pid GROUP BY status");
    while($row = $res->fetch_assoc()){
        $status_data[$row['status']] = $row['total'];
    }

    // Task by Content Pillar
    $pillar_data = [];
    $res = $conn->query("SELECT content_pillar, COUNT(*) as total FROM task_list WHERE project_id = $pid GROUP BY content_pillar");
    while($row = $res->fetch_assoc()){
        $pillar_data[$row['content_pillar'] ?: 'Undefined'] = $row['total'];
    }

    // Task by Platform
    $platform_data = [];
    $res = $conn->query("SELECT platform, COUNT(*) as total FROM task_list WHERE project_id = $pid GROUP BY platform");
    while($row = $res->fetch_assoc()){
        $platform_data[$row['platform'] ?: 'Unknown'] = $row['total'];
    }

    // Manager Info
    $manager = null;
    if(!empty($proj['manager_id'])){
        $m_qry = $conn->query("SELECT id, firstname, lastname, avatar 
                               FROM users 
                               WHERE id = {$proj['manager_id']}");
        if($m_qry->num_rows > 0){
            $m = $m_qry->fetch_assoc();
            $manager = [
                'id' => $m['id'],
                'name' => ucwords($m['firstname'].' '.$m['lastname']),
                'avatar' => !empty($m['avatar']) ? 'assets/uploads/'.$m['avatar'] : 'assets/uploads/default.png'
            ];
        }
    }

    // Members (Assignments)
    $members = [];
    if(!empty($proj['user_ids'])){
        $uids = array_filter(explode(",", $proj['user_ids']));
        if(count($uids) > 0){
            $users_qry = $conn->query("SELECT id, firstname, lastname, avatar 
                                       FROM users 
                                       WHERE id IN (".implode(",", $uids).")");
            while($u = $users_qry->fetch_assoc()){
                $members[] = [
                    'id' => $u['id'],
                    'name' => ucwords($u['firstname'].' '.$u['lastname']),
                    'avatar' => !empty($u['avatar']) ? 'assets/uploads/'.$u['avatar'] : 'assets/uploads/default.png'
                ];
            }
        }
    }

    $project_stats[] = [
        'id' => $proj['id'],
        'name' => $proj['name'],
        'total_tasks' => $total_tasks,
        'status' => $status_data,
        'pillar' => $pillar_data,
        'platform' => $platform_data,
        'manager' => $manager,
        'members' => $members
    ];

}
?>

<div class="container-fluid">
  <div class="row">
    <?php foreach($project_stats as $p): ?>
      <?php $encoded_id = encode_id($p['id']); // Encoding ID untuk URL ?>
      <div class="col-md-12 mb-4">
        <div class="card shadow-sm project-card-link" data-id="<?= $p['id'] ?>" data-encoded-id="<?= $encoded_id ?>" style="cursor: pointer;">
          <div class="card-header py-2">
            <h5 class="m-0">
              <b><?= $p['name'] ?></b> (<?= $p['total_tasks'] ?> Tasks)
            </h5>
          </div>
          <div class="card-body">
            <div class="row">
              
              <div class="col-md-3 px-3">
                <h6 class="text font-weight-bold">Task Status </h6>
                <div class="chart-container" style="max-height:250px; padding-bottom: 20px;">
                    <canvas id="statusChart_<?= $p['id'] ?>"></canvas>
                </div>
              </div>

              <div class="col-md-3 px-3">
                <h6 class="text font-weight-bold">Content Pillar </h6>
                <div class="chart-container" style="max-height:250px; padding-bottom: 20px;">
                    <canvas id="pillarChart_<?= $p['id'] ?>"></canvas>
                </div>
              </div>

              <div class="col-md-3 px-3">
                <h6 class="font-weight-bold">Platform</h6>
                <div class="chart-container" style="max-height:250px; padding-top:30px">
                    <canvas id="platformChart_<?= $p['id'] ?>"></canvas>
                </div>
              </div>
              
              <div class="col-md-3 px-3">
                <h6 class="font-weight-bold">Assignment</h6>
                
                <div class="p-2 mb-3 rounded" style="background-color: #f8f9fa;">
                  <small class="text-muted d-block">Project Manager</small>
                  <?php if($p['manager']): ?>
                    <div class="d-flex align-items-center">
                      <img src="<?= $p['manager']['avatar'] ?>" 
                          class="rounded-circle border mr-2" 
                          style="width:35px; height:35px; object-fit:cover;">
                      <strong class="text-truncate" title="<?= $p['manager']['name'] ?>"><?= $p['manager']['name'] ?></strong>
                    </div>
                  <?php else: ?>
                    <span class="text-muted">No Manager Assigned</span>
                  <?php endif; ?>
                </div>

                <small class="text-muted d-block">Team Members (<?= count($p['members']) ?>)</small>
                <div class="d-flex flex-wrap align-items-center mt-1 assignment-list">
                  <?php if(!empty($p['members'])): ?>
                    <?php foreach($p['members'] as $m): ?>
                      <img src="<?= $m['avatar'] ?>" 
                          class="rounded-circle border border-white avatar-member" 
                          style="width:30px; height:30px; object-fit:cover;" 
                          title="<?= $m['name'] ?>">
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span class="text-muted">No Members</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
/* ================================================= */
/* CSS Fix untuk Timeline Badge (Recent Activities) */
/* ================================================= */

/* Pastikan list item memiliki posisi relatif */
.timeline-item {
    position: relative;
    list-style: none;
}

/* Garis vertikal (Timeline line) */
.card-body .timeline.pl-3::before { 
    content: '';
    padding-left: 0 !important;
    position: absolute;
    top: 0;
    bottom: 0;
    left: 12px !important;
    width: 2px;
    background-color: #e9ecef; 
    z-index: 0;
}

.card-body .timeline-item .timeline-badge {
    position: absolute;
    top: 5px; 
    left: 10px !important; 
    transform: translateX(-50%) !important; 
    width: 10px; 
    height: 10px; 
    border-radius: 50%;
    z-index: 1;
    border: 2px solid white; 
}
.card-body .timeline-item > div {
  padding-left: 20px !important;
}

/* Menghapus margin default dari item (jika ada) */
.timeline li {
    margin-bottom: 15px; 
}

/* Pastikan kartu Project Progress (atas) berfungsi sebagai link */
.project-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    transform: translateY(-2px);
    transition: all 0.2s ease-in-out;
}
</style>

<script>
$(document).ready(function(){
  // Event listener untuk mengklik kartu proyek di bagian bawah (yang memiliki grafik detail)
  $(document).on('click', '.project-card-link', function(e){
    // Pastikan klik bukan pada elemen interaktif di dalam kartu (misalnya canvas chart)
    if ($(e.target).closest('canvas').length === 0) {
      var encoded_pid = $(this).data('encoded-id'); 
      if(encoded_pid){
        window.location.href = "index.php?page=view_project&id=" + encoded_pid;
      }
    }
  });
});

<?php foreach($project_stats as $p): ?>

// === STATUS CHART ===
new Chart(document.getElementById('statusChart_<?= $p['id'] ?>'), {
  type: 'doughnut',
  data: {
    labels: ['Pending','Started','On-Progress','On-Hold','Over Due','Done'],
    datasets: [{
      data: [
        <?= $p['status'][0] ?? 0 ?>,
        <?= $p['status'][1] ?? 0 ?>,
        <?= $p['status'][2] ?? 0 ?>,
        <?= $p['status'][3] ?? 0 ?>,
        <?= $p['status'][4] ?? 0 ?>,
        <?= $p['status'][5] ?? 0 ?>
      ],
      backgroundColor: ['#3a495c','#E6B800','#2A80B9','#B0B0B0','#C62828','#4C9A2A'],
      borderWidth: 1
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        enabled: true
      }
    }
  }
});

// === CONTENT PILLAR CHART ===
new Chart(document.getElementById('pillarChart_<?= $p['id'] ?>'), {
  type: 'pie',
  data: {
    labels: <?= json_encode(array_keys($p['pillar'])) ?>,
    datasets: [{
      data: <?= json_encode(array_values($p['pillar'])) ?>,
      backgroundColor: ['#3a495c','#E6B800','#2A80B9','#B0B0B0','#C62828','#4C9A2A'],
      borderWidth: 1
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        enabled: true
      }
    }
  }
});

// === PLATFORM CHART ===
new Chart(document.getElementById('platformChart_<?= $p['id'] ?>'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($p['platform'])) ?>,
    datasets: [{
      label: 'Tasks',
      data: <?= json_encode(array_values($p['platform'])) ?>,
      backgroundColor: '#E66A00'
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        enabled: true
      }
    },
    scales: {
      y: {
        beginAtZero: true
      }
    }
  }
});

<?php endforeach; ?>
</script>