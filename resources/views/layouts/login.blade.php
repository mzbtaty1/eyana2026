<!--

  ░█████╗░██╗░░░██╗░█████╗░██████╗░░█████╗░████████╗███████╗░█████╗░██╗░░██╗
  ██╔══██╗██║░░░██║██╔══██╗██╔══██╗██╔══██╗╚══██╔══╝██╔════╝██╔══██╗██║░░██║
  ██║░░╚═╝██║░░░██║██║░░██║██████╔╝███████║░░░██║░░░█████╗░░██║░░╚═╝███████║
  ██║░░██╗██║░░░██║██║░░██║██╔══██╗██╔══██║░░░██║░░░██╔══╝░░██║░░██╗██╔══██║
  ╚█████╔╝╚██████╔╝╚█████╔╝██║░░██║██║░░██║░░░██║░░░███████╗╚█████╔╝██║░░██║
  ░╚════╝░░╚═════╝░░╚════╝░╚═╝░░╚═╝╚═╝░░╚═╝░░░╚═╝░░░╚══════╝░╚════╝░╚═╝░░╚═╝
   
   
   # About Cuoratech #
   * "Innovation meets technology! Cuoratech: a leader in software technologies, sustainable digitization, and innovative artificial intelligence."
   
   # You Can Contact US With *
   - https://fb.com/cuoratech
   - support@cuoratech.com 
   - https://www.linkedin.com/company/cuoratech/
   
   # All Rights Reserved © 2024 , Created By : Cuoratech
   -->
<!doctype html>
 <?php
     $value = Cookie::get('site_mode');
       ?>

<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-bs-theme="{{$value}}" data-theme-colors="default" dir="rtl">
    
<head>

    <meta charset="utf-8" />
    <title>ايانا تورز - @yield('title')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
      <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
      <meta name="csrf-token" content="{{csrf_token()}}">
      <meta http-equiv="Pragma" content="no-cache" />
    

    <!-- jsvectormap css -->
    <link href="{{asset('assets/libs/jsvectormap/css/jsvectormap.min.css')}}" rel="stylesheet" type="text/css" />

    <!--Swiper slider css-->
    <link href="{{asset('assets/libs/swiper/swiper-bundle.min.css')}}" rel="stylesheet" type="text/css" />

    <!-- Layout config Js -->
    <script src="{{asset('assets/js/layout.js')}}"></script>
    <!-- Bootstrap Css -->
    <link href="{{asset('assets/css/bootstrap.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="{{asset('assets/css/icons.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="{{asset('assets/css/app.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- custom Css-->
    <link href="{{asset('assets/css/custom.min.css')}}" rel="stylesheet" type="text/css" />

</head>

<body>


               @yield('content')

    
    

    <!-- JAVASCRIPT -->
    <script src="{{asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
    <script src="{{asset('assets/libs/simplebar/simplebar.min.js')}}"></script>
    <script src="{{asset('assets/libs/node-waves/waves.min.js')}}"></script>
    <script src="{{asset('assets/libs/feather-icons/feather.min.js')}}"></script>
    <script src="{{asset('assets/js/pages/plugins/lord-icon-2.1.0.js')}}"></script>
    <script src="{{asset('assets/js/plugins.js')}}"></script>

    <!-- apexcharts -->
    <script src="{{asset('assets/libs/apexcharts/apexcharts.min.js')}}"></script>

    <!-- Vector map-->
    <script src="{{asset('assets/libs/jsvectormap/js/jsvectormap.min.js')}}"></script>
    <script src="{{asset('assets/libs/jsvectormap/maps/world-merc.js')}}"></script>

    <!--Swiper slider js-->
    <script src="{{asset('assets/libs/swiper/swiper-bundle.min.js')}}"></script>

    <!-- Dashboard init -->
    <script src="{{asset('assets/js/pages/dashboard-ecommerce.init.js')}}"></script>

    <!-- App js -->
    <script src="{{asset('assets/js/app.js')}}"></script>
</body>
<!--

  ░█████╗░██╗░░░██╗░█████╗░██████╗░░█████╗░████████╗███████╗░█████╗░██╗░░██╗
  ██╔══██╗██║░░░██║██╔══██╗██╔══██╗██╔══██╗╚══██╔══╝██╔════╝██╔══██╗██║░░██║
  ██║░░╚═╝██║░░░██║██║░░██║██████╔╝███████║░░░██║░░░█████╗░░██║░░╚═╝███████║
  ██║░░██╗██║░░░██║██║░░██║██╔══██╗██╔══██║░░░██║░░░██╔══╝░░██║░░██╗██╔══██║
  ╚█████╔╝╚██████╔╝╚█████╔╝██║░░██║██║░░██║░░░██║░░░███████╗╚█████╔╝██║░░██║
  ░╚════╝░░╚═════╝░░╚════╝░╚═╝░░╚═╝╚═╝░░╚═╝░░░╚═╝░░░╚══════╝░╚════╝░╚═╝░░╚═╝
   
   
   # About Cuoratech #
   * "Innovation meets technology! Cuoratech: a leader in software technologies, sustainable digitization, and innovative artificial intelligence."
   
   # You Can Contact US With *
   - https://fb.com/cuoratech
   - support@cuoratech.com 
   - https://www.linkedin.com/company/cuoratech/
   
   # All Rights Reserved © 2024 , Created By : Cuoratech
   -->
</html>