
<?php 
session_start();
include('./db_connect.php');
  ob_start();
  // if(!isset($_SESSION['system'])){

    $system = $conn->query("SELECT * FROM system_settings")->fetch_array();
    foreach($system as $k => $v){
      $_SESSION['system'][$k] = $v;
    }
  // }
  ob_end_flush();
?>
<?php 
if(isset($_SESSION['login_id']))
header("location:index.php?page=home");

?>

<?php include 'header.php' ?>


<!DOCTYPE html>
<html lang="en">
<style>
	

  #img{
    background-color: #FFFFFF;
    border-radius: 100% ;
    background-position: center;
    width: 40%;
    align: center;
    margin-left:100px;
  }

  #text{
    font-weight : bold;
  }

	main#main{
		width:100%;
		height: calc(100%);
		background:white;
	}
	
	#login-right .card{
		margin: auto
	}
	.logo {
    margin: auto;
    font-size: 8rem;
    background: white;
    padding: .5em 0.8em;
    border-radius: 50% 50%;
    color: #000000b3;
}

html, body {
  height: 100%;
  margin: 0;
  padding: 0;
}

.full-height {
  height: 100vh;
}

.grad {
  background: linear-gradient(135deg, #9BB1C8, #E7ECF0, #DBA980); /* Ungu → Biru → Toska */
}

.animasi{
  animation: slideInRight 1s ease-out;
}
.fade-in{
  animation: fadeIn 1.2s ease-out;
}

.logo-bg {
  position: absolute;
  opacity: 0.08;
  animation: spin 20s linear infinite;
  z-index: 0;
  width: 120px;
}

.logo1 { top: 5%; left: 5%; }
.logo2 { top: 10%; right: 45%; }
.logo3 { bottom: 5%; left: 10%; }
.logo4 { bottom: 12%; right: 50%; }

/* Animasi rotate */
@keyframes spin {
  from { transform: rotate(0deg);}
  to { transform: rotate(360deg);}
}

/* Pastikan container utama punya posisi relatif */
.container-fluid.grad {
  position: relative;
  overflow: hidden;
  z-index: 1;
}

</style>

<body >

<div class="container-fluid grad">
  
  <div class="row">
      <img src="images/bnw.png" class="logo-bg logo1" alt="logo background">
      <img src="images/bnw.png" class="logo-bg logo2" alt="logo background">
      <img src="images/bnw.png" class="logo-bg logo3" alt="logo background">
      <img src="images/bnw.png" class="logo-bg logo4" alt="logo background">

    <div class="col d-flex justify-content-center align-items-center m-4" style="color: #B75301;">
      <div class="text-center fade-in">
        <h1> <b>“Great men are not born great, they grow great. ”</b></h1>
        <p class="text-secondary">- Don Vito Corleone, The Godfather </p>
      </div>
    </div>
    <div class="col-md-auto card full-height  justify-content-center align-items-center animasi" style="border-radius: 50px 0px 0px 50px;">
      <div class="container " >
            <div class="form-body without-side mt-4 ">
              <div class="iofrm-layout">
                  <div class="img-holder">
                      <div class="bg"></div>
                      <div class="info-holder text-center">
                          <img src="assets/Logo1.png" alt="" width="150px">
                      </div>
                  </div>
                  <div class="form-holder m-4">
                      <div class="form-content">
                          <div class="form-items text-center">
                              <h3>Login to account</h3>
                              <p>Access to the most powerfull tool in the entire design and web industry.</p>
                          </div>
                      </div>
                  </div>
                    
                </div>
              </div>
              <form action="" id="login-form" class="m-3">
                <p>Email</p>
                <div class="input-group mb-3">
                  <input type="email" class="form-control" name="email" required placeholder="Email">
                  <div class="input-group-append">
                    <div class="input-group-text">
                      <span class="fas fa-envelope"></span>
                    </div>
                  </div>
                </div>
                <!-- Password -->
                <div class="form-group">
                  <p for="password">Password</p>
                  <div class="input-group">
                    <input type="password" class="form-control" name="password" id="password" required placeholder="Enter password">
                    <div class="input-group-append">
                      <span class="input-group-text" id="togglePassword"><i class="fas fa-eye"></i></span>
                    </div>
                  </div>
                </div>

                <div class="icheck-primary">
                      <input   type="checkbox" id="remember">
                      <label for="remember">
                        <small> Remember Me </small>
                      </label>
                    </div>
                <br>
                <div class="">
                  <button type="submit" class="btn btn-block text-white" style="background-color:#B75301;">Login</button>
                </div>
              </form>
            </div>  
    </div>
  </div>
</div>
</body>
<!-- /.login-logo -->
      
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<!-- /.login-box -->
<script>
  $(document).ready(function(){
    $('#login-form').submit(function(e){
    e.preventDefault()
    start_load()
    if($(this).find('.alert-danger').length > 0 )
      $(this).find('.alert-danger').remove();
    $.ajax({
      url:'ajax.php?action=login',
      method:'POST',
      data:$(this).serialize(),
      error:err=>{
        console.log(err)
        end_load();

      },
      success:function(resp){
        if(resp == 1){
          location.href ='index.php?page=home';
        }else{
          $('#login-form').prepend('<div class="alert alert-danger">Username or password is incorrect.</div>')
          end_load();
        }
      }
    })
  })
  })

  // Toggle Show/Hide Password
    $(document).on('click', '#togglePassword', function(){
      let input = $('#password');
      let icon = $(this).find('i');
      if(input.attr('type') === 'password'){
        input.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
      } else {
        input.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
      }
    });
</script>
<?php include 'footer.php' ?>

</body>
</html>
