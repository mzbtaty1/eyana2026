@extends('layouts.login')

@section('title' , 'تسجيل الدخول')
@section('content')
<style>

  
    .loader {
  width: 48px;
  height: 48px;
  display: inline-block;
  position: relative;
}
.loader::after,
.loader::before {
  content: '';  
  box-sizing: border-box;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: 2px solid #0ab39c;
  position: absolute;
  left: 0;
  top: 0;
  animation: animloader 2s linear infinite;
}
.loader::after {
  animation-delay: 1s;
}

@keyframes animloader {
  0% {
    transform: scale(0);
    opacity: 1;
  }
  100% {
    transform: scale(1);
    opacity: 0;
  }
}
  .disabledBtn{
        
        display: none;
    }
    .error_msg{
        display: none;
    } 
    .is-invalid{
/*        color:#f06548;*/
    }
</style>

    <div class="auth-page-wrapper pt-5">
    
        <!-- auth page content -->
        <div class="auth-page-content">
            <div class="container">
           

                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        <div class="card mt-4">

                            <div class="card-body p-4">
                                <div class="text-center mt-2">
                                    <center>
                                    <img src="{{asset('assets/images/flymix_colored.png')}}"  style="  width: 154px;">
                                    </center>
                                    <br>
                                    <h5 class="text-primary" dir="rtl">مرحباً بك!</h5>
                                    <p class="text-muted">
                                    سجل دخول لكي تتمكن من استخدام نظام شركة ايانا تورز
                                     </p>
                                     @if($errors->any())
   <div class="alert alert-danger border-0">
      {{$errors->first()}}
   </div>
   @endif
                                    
                                    <div class="alert alert-danger border-0 error_msg" id="error_msg"></div>
                                    
                                </div>
                                <div class="p-2 mt-4">
                                     <form method="POST" name="myForm" onsubmit="return dologin()" autocomplete="off" required>
                                        @csrf

                                        <div class="mb-3">
                                            <label for="username" class="form-label">البريد الالكتروني</label>
                                            <input type="email" value="{{old('email')}}" name="email" class="form-control" id="email" placeholder="info@example.com" oninput="myFunction()">
                                            
                                             <div id="validationServerUsernameFeedback" class="invalid-feedback">
        اكتب البريد الالكتروني
      </div>

                                            
                                            @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong> 
                                    </span>
                                @enderror
                                        </div>
                                        

                                        <div class="mb-3">
                                          
                                            <label class="form-label" for="password-input">كلمة السر</label>
                                            <div class="position-relative auth-pass-inputgroup mb-3">
                                                <input type="password" name="password" class="form-control password-input" placeholder="•••••••••" id="password" style="text-align: left;" oninput="myFunction()">
                                                                             <div id="validationServerUsernameFeedback" class="invalid-feedback">
        اكتب كلمة السر
      </div>
                                                 @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                            </div>
                                        </div>
 
                                        <div class="form-check">
                                            <input class="form-check-input" name="remember" type="checkbox" value="" id="auth-remember-check" checked="">
                                            <label class="form-check-label" for="auth-remember-check">تذكر الجلسة</label>
                                        </div>

                                        <div class="mt-4">
                                            <input type="button" class="btn btn-success w-100" id="LoginBtn" type="submit" value="تسجيل الدخول" onclick="dologin();"> 
                      <center>
                                               <span class="disabledBtn loader" id="disabledBtn"></span> 
                                            </center>
                                        </div>

                                    
                                    </form>
                                </div>
                            </div>
                            <!-- end card body -->
                        </div>
                        <!-- end card -->

                       
                    </div>
                </div>
                <!-- end row -->
            </div>
            <!-- end container -->
        </div>
        <!-- end auth page content -->


    </div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
function myFunction() {

   var element = document.getElementById("email");
   element.classList.remove("is-invalid");
   var element = document.getElementById("password");
   element.classList.remove("is-invalid");


}

function dologin() {
   document.getElementById("LoginBtn").style.display = "none";
   document.getElementById("disabledBtn").style.display = "block";


   var email = document.forms["myForm"]["email"].value;
   if (email == "") {
      var element = document.getElementById("email");
      element.classList.add("is-invalid");


      document.getElementById("LoginBtn").style.display = "block";
      document.getElementById("disabledBtn").style.display = "none";
      return false;
   }

   var password = document.forms["myForm"]["password"].value;
   if (password == "") {
      var element = document.getElementById("password");
      element.classList.add("is-invalid");


      document.getElementById("LoginBtn").style.display = "block";
      document.getElementById("disabledBtn").style.display = "none";
      return false;
   }


   $(document).ready(function () {

      $.ajax({
         type: "POST",
         url: "{{route('fmx_login')}}",
         headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
         },
         data: "email=" + email + "&password=" + password,
         //   beforeSend: function() {
         //   $('.message_box').html(
         //   '<img src="Loader.gif" width="25" height="25"/>'
         //   );
         //   },
         success: function (data) {
            //var dataResult = JSON.parse(data);

            //console.log(data.status_code);

            var status_code = data.status_code;

            if (status_code == 901) {


               document.getElementById("error_msg").style.display = "block";
               document.getElementById("LoginBtn").style.display = "block";
               document.getElementById("disabledBtn").style.display = "none";
               document.getElementById("error_msg").innerHTML = "برجاء كتابة البريد الالكتروني وكلمة السر";
            }
            if (status_code == 404) {


               document.getElementById("error_msg").style.display = "block";
               document.getElementById("LoginBtn").style.display = "block";
               document.getElementById("disabledBtn").style.display = "none";
               document.getElementById("error_msg").innerHTML = "خطأ في البريد الالكتروني أو كلمة السر";


            }
            if (status_code == 403) {


               document.getElementById("error_msg").style.display = "block";
               document.getElementById("LoginBtn").style.display = "block";
               document.getElementById("disabledBtn").style.display = "none";
               document.getElementById("error_msg").innerHTML = "تم حظر حسابك ، يرجي مراسلة المسئول لفك الحظر";


            }

            if (status_code == 200) {


               window.location.href = "{{route('site.index')}}";


            }


         }
      });
      //   });

   });


   //     alert(1);


}
</script>
@endsection
