<?php include 'db_connect.php' ?>

<div class="col-md-12">
    <div class="container-fluid pb-3">
        <div class="row">
            <div class="col-md-6">
                <div class="d-flex justify-content-start">
                    <h4>Project Progress</h4>
                </div>
            </div>

            <div class="col-md-6">
                <div class="d-flex justify-content-end">
                    <button class="btn text-white" id="print" style="background-color:#B75301;">
                        <i class="fa fa-print"> </i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-outline">
        <div class="card-body p-0">
            <div class="table-responsive" id="printable">
                <table class="table table-hover table-condensed">
                    <colgroup>
                        <col width="5%">
                        <col width="28%">
                        <col width="12%">
                        <col width="12%">
                        <col width="10%">
                        <col width="25%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-left">No</th>
                            <th class="text-left">Project</th>
                            <th class="text-left">Due Date</th>
                            <th class="text-left">Status</th> 
                            <th class="text-left">Task</th>
                            <th class="text-left">Progress</th>
                        </tr>
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
                            <td class="text-left"><?php echo $i++ ?></td>
                            <td>
                                <b><?php echo ucwords($row['name']) ?></b>
                            </td>
                            
                            <td class="text-left">
                                <b><?php echo date("Y-m-d",strtotime($row['end_date'])) ?></b>
                            </td>

                            <td class="project-state text-left">
                                <?php
                                $statusIndex = (int)$row['status'];
                                $label = $statusLabel[$statusIndex] ?? "Unknown";
                                $class = $badgeClass[$statusIndex] ?? 'badge-dark';
                                echo "<span class='badge {$class} p-2'>{$label}</span>";
                                ?>
                            </td>

                            <td class="text-left">
                                <?php echo number_format($cprog) . ' / ' . number_format($tprog) ?>
                            </td>
                            
                            <td class="project_progress">
                                <div class="progress progress-sm mb-1 progress-custom">
                                    <div class="progress-bar progress-bar-custom" 
                                        role="progressbar" 
                                        style="width: <?php echo $prog ?>%">
                                    </div>
                                </div>
                                <small><?php echo $prog ?>% Complete</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>  
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Style agar sama dengan project_list.php */
table p { margin: 0 !important; }
table td { vertical-align: middle !important; }

.progress-custom {
    /* Atur border-radius untuk keseluruhan wadah progress bar */
    border-radius: 10px !important; 
    height: 10px; 
    overflow: hidden; 
    background-color: #e9ecef; /* Warna latar belakang kontainer */
}

.progress-bar-custom {
    /* Mengganti warna latar belakang menjadi coklat #B75301 */
    background-color: #B75301 !important; 
    /* Mengatur border-radius untuk bar yang mengisi */
    border-radius: 10px !important;
    height: 100%;
}
</style>

<script>
	$('#print').click(function(){
  // start_load() // Asumsi fungsi ini didefinisikan secara global

  // Ambil seluruh konten yang ingin dicetak
  var content = $('#printable').clone()
  var printWindow = window.open('', '', 'width=900,height=600')

  var headContent = `
    <html>
      <head>
        <title>Project Progress Report</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <style>
          /* Style untuk tampilan cetak */
          body { font-family: Arial, sans-serif; margin: 20px; }
          table { width: 100%; border-collapse: collapse; }
          th, td { padding: 8px; text-align: left; border: 1px solid #000; }
          .text-center { text-align: center; }
          .text-left { text-align: left; }
          .badge { padding: 5px 10px; font-size: 90%; }
          .progress { background-color: #e9ecef !important; height: 10px; border-radius: 5px; overflow: hidden;}
          /* Warna progress bar untuk print (agar tidak menggunakan custom #B75301 jika browser tidak mencetak warna latar) */
          .progress-bar-custom { background-color: #007bff !important; } 
          
          /* KOREKSI: Memastikan tampilan data proyek di print */
          table small { display: block; margin-top: 2px; color: #6c757d; }
          
          /* Sembunyikan progress bar di print dan tampilkan angka jika perlu (opsional) */
          @media print {
              .progress { display: none; }
              .project_progress small { display: block; font-weight: bold; }
          }
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
    // end_load() // Asumsi fungsi ini didefinisikan secara global
  }, 1000)
})
</script>