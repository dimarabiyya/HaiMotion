<?php include 'db_connect.php' ?>
<div class="col-md-12">
  <div class="card card-outline">
    <div class="card-header">
      <b>Project Progress</b>
      <div class="card-tools">
        <button class="btn text-white" id="print" style="background-color:#B75301;">
          <i class="fa fa-print"></i> Print
        </button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive" id="printable">
        <table class="table m-0 table-bordered">
          <thead>
            <th>No</th>
            <th>Project</th>
            <th>Total Task</th>
            <th>Completed Task</th>
            <th>Progress</th>
            <th>Status</th>
          </thead>
          <tbody>
            <?php
            $i = 1;
            $where = "";
            if($_SESSION['login_type'] == 2){
              $where = " where manager_id = '{$_SESSION['login_id']}' ";
            }elseif($_SESSION['login_type'] == 3){
              $where = " where concat('[',REPLACE(user_ids,',','],['),']') LIKE '%[{$_SESSION['login_id']}]%' ";
            }
            $qry = $conn->query("SELECT * FROM project_list $where order by name asc");
            
            // mapping status project
            $statusLabel = [
              0 => "Pending",
              1 => "Started",
              2 => "On-Progress",
              3 => "On-Hold",
              4 => "Over Due",
              5 => "Done"
            ];
            $badgeClass = [
              0 => 'badge-secondary',
              1 => 'badge-info',
              2 => 'badge-primary',
              3 => 'badge-warning',
              4 => 'badge-danger',
              5 => 'badge-success'
            ];

            while($row= $qry->fetch_assoc()):
              // Total task (semua task project)
              $tprog = $conn->query("SELECT id FROM task_list WHERE project_id = {$row['id']}")->num_rows;

              // Completed task (status = 5 → Done)
              $cprog = $conn->query("SELECT id FROM task_list WHERE project_id = {$row['id']} AND status = 5")->num_rows;

              // Hitung persentase
              $prog = $tprog > 0 ? ($cprog / $tprog) * 100 : 0;
              $prog = number_format($prog, 2);

              // update status project otomatis
              $prod = $conn->query("SELECT id FROM user_productivity where project_id = {$row['id']}")->num_rows;
              $dur = $conn->query("SELECT sum(time_rendered) as duration FROM user_productivity where project_id = {$row['id']}");
              $dur = $dur->num_rows > 0 ? $dur->fetch_assoc()['duration'] : 0;

              if($row['status'] == 0 && strtotime(date('Y-m-d')) >= strtotime($row['start_date'])):
                if($prod  > 0  || $cprog > 0)
                  $row['status'] = 2; // On-Progress
                else
                  $row['status'] = 1; // Started
              elseif($row['status'] == 0 && strtotime(date('Y-m-d')) > strtotime($row['end_date'])):
                $row['status'] = 4; // Over Due
              endif;
            ?>
              <tr>
                <td><?php echo $i++ ?></td>
                <td>
                  <a><?php echo ucwords($row['name']) ?></a>
                  <br>
                  <small>Due: <?php echo date("Y-m-d",strtotime($row['end_date'])) ?></small>
                </td>
                <td class="text-center"><?php echo number_format($tprog) ?></td>
                <td class="text-center"><?php echo number_format($cprog) ?></td>
                <td class="project_progress">
                  <div class="progress progress-sm">
                    <div class="progress-bar 
                      <?php 
                        if($prog == 100){
                          echo 'bg-success';
                        } elseif($prog >= 50){
                          echo 'bg-primary';
                        } elseif($prog > 0){
                          echo 'bg-warning';
                        } else {
                          echo 'bg-danger';
                        }
                      ?>" 
                      role="progressbar" 
                      style="width: <?php echo $prog ?>%">
                    </div>
                  </div>
                  <small><?php echo $prog ?>% Complete</small>
                </td>
                <td class="project-state text-center">
                  <?php
                    $statusIndex = (int)$row['status'];
                    $label = $statusLabel[$statusIndex] ?? "Unknown";
                    $class = $badgeClass[$statusIndex] ?? 'badge-dark';
                    echo "<span class='badge {$class}'>{$label}</span>";
                  ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>  
        </table>
      </div>
    </div>
  </div>
</div>

<script>
	$('#print').click(function(){
  start_load()

  // Ambil seluruh konten yang ingin dicetak
  var content = $('#printable').clone()
  var printWindow = window.open('', '', 'width=900,height=600')

  var headContent = `
    <html>
      <head>
        <title>Project Progress Report</title>
        <link rel="stylesheet" href="assets/plugins/bootstrap/css/bootstrap.min.css">
        <style>
          body { font-family: Arial, sans-serif; margin: 20px; }
          table { width: 100%; border-collapse: collapse; }
          th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
          .text-center { text-align: center; }
          .badge { padding: 5px 10px; font-size: 90%; }
        </style>
      </head>
      <body>
        <h4 class="text-center mb-4"><b>Project Progress Report as of (<?php echo date("F d, Y") ?>)</b></h4>
  `;
  var footContent = `
      </body>
    </html>
  `;

  // Tulis HTML lengkap ke jendela baru
  printWindow.document.write(headContent + content.html() + footContent)
  printWindow.document.close()

  // Tunggu sejenak lalu print
  printWindow.focus()
  setTimeout(function () {
    printWindow.print()
    printWindow.close()
    end_load()
  }, 1000)
})
</script>