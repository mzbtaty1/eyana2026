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
 <?php
     $value = Cookie::get('site_mode');
$rname = Route::currentRouteName();
//dd($name);
       ?>

<!doctype html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-bs-theme="{{$value}}" data-theme-colors="default" dir="rtl">
   <head>
      <meta charset="utf-8" />
      <title>ايانا تورز - @yield('title')</title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
      <meta name="csrf-token" content="{{csrf_token()}}">
      <meta http-equiv="Pragma" content="no-cache" />
      <script>window.MAPBOX_TOKEN = @json(config('services.mapbox.token'));</script>
      <!-- App favicon -->
      <link rel="shortcut icon" href="{{asset('assets/images/favicon.ico')}}">
      <!-- Cairo: Arabic-friendly typeface for the 2026 visual system -->
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
      <link href="{{asset('assets/css/custom.min.css')}}?v={{file_exists(public_path('assets/css/custom.min.css')) ? filemtime(public_path('assets/css/custom.min.css')) : 1}}" rel="stylesheet" type="text/css" />

      <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js" integrity="sha512-AA1Bzp5Q0K1KanKKmvN/4d3IRKVlv9PYgwFPvm32nPO6QS8yH1HO7LbgB1pgiOxPtfeg5zEn2ba64MUcqJx6CA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
      <script src="{{asset('assets/jquery-3.7.0.slim.min.js')}}"></script> 
      
      
      
          <script src="{{asset('assets/dselect.js')}}?v={{file_exists(public_path('assets/dselect.js')) ? filemtime(public_path('assets/dselect.js')) : 1}}"></script>

       
      <link href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.css" rel="stylesheet">
      <link href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.css" rel="stylesheet">
      <script src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
      <script src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.js"></script>
      <script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.dataTables.js"></script>
      <script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.colVis.min.js"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
      <!--<script src="https://cdn.datatables.net/plug-ins/2.1.3/api/sum().js"></script>-->
      <!--
         <script src=""></script>
         <script src=""></script>
         <script src=""></script>
         -->
      <style>
         .dark_img{
         }
         @if($value == "dark")
         .dark_img{
         filter: brightness(0) invert(1) !important;
         }
         @endif
         .my_badge{
         font-size: 11px;
         }
         rtag{
         color:red;
         }
         .go_logo{
         width: 120px;
         filter: brightness(0) invert(1) !important;
         margin-top: 17px;
         }
         body{
         print-color-adjust: exact;
         -webkit-print-color-adjust: exact;
         }

         /* Eyana print baseline for screens without a dedicated print view: hide chrome,
            keep only the report content. Mark screen-only controls with .ey-no-print
            (filter forms, action buttons, etc.) to hide them too. */
         @media print{
             #page-topbar,
             .app-menu.navbar-menu,
             .ey-no-print,
             #phpdebugbar,
             .phpdebugbar,
             .phpdebugbar-openhandler-overlay{
                 display: none !important;
             }
             .main-content{
                 margin: 0 !important;
             }
             .vertical-menu, #layout-wrapper{
                 background: #fff !important;
             }
             @page{
                 size: A4;
                 margin: 12mm;
             }
         }
      </style>
       <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.1.1/css/buttons.dataTables.css">
   </head>
   <body @if($rname == 'site.accounts_statement_custom_view') onload="findTotal();" @endif>
   <!--
      site.accounts_statement_custom_view
      -->
   <!-- Begin page -->
   <div id="layout-wrapper">
      @auth
      <header id="page-topbar">
         <div class="layout-width">
            <div class="navbar-header">
               <div class="d-flex">
                  <!-- LOGO -->
                  <div class="navbar-brand-box horizontal-logo">
                     <a href="index.html" class="logo logo-dark">
                     <span class="logo-sm">
                     <img src="assets/images/logo-sm.png" alt="" height="22">
                     </span>
                     <span class="logo-lg">
                     <img src="assets/images/logo-dark.png" alt="" height="17">
                     </span>
                     </a>
                     <a href="index.html" class="logo logo-light">
                     <span class="logo-sm">
                     <img src="assets/images/logo-sm.png" alt="" height="22">
                     </span>
                     <span class="logo-lg">
                     <img src="assets/images/logo-light.png" alt="" height="17">
                     </span>
                     </a>
                  </div>
                  <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger material-shadow-none" id="topnav-hamburger-icon">
                  <span class="hamburger-icon">
                  <span></span>
                  <span></span>
                  <span></span>
                  </span>
                  </button>
                  <!-- App Search-->
                  <form class="app-search d-none d-md-block">
                     <div class="position-relative">
                        <input type="text" class="form-control" placeholder="Search..." autocomplete="off" id="search-options" value="">
                        <span class="mdi mdi-magnify search-widget-icon"></span>
                        <span class="mdi mdi-close-circle search-widget-icon search-widget-icon-close d-none" id="search-close-options"></span>
                     </div>
                     <div class="dropdown-menu dropdown-menu-lg" id="search-dropdown">
                        <div data-simplebar style="max-height: 320px;">
                           <!-- item-->
                           <div class="dropdown-header">
                              <h6 class="text-overflow text-muted mb-0 text-uppercase">Recent Searches</h6>
                           </div>
                           <div class="dropdown-item bg-transparent text-wrap">
                              <a href="index.html" class="btn btn-soft-secondary btn-sm rounded-pill">how to setup <i class="mdi mdi-magnify ms-1"></i></a>
                              <a href="index.html" class="btn btn-soft-secondary btn-sm rounded-pill">buttons <i class="mdi mdi-magnify ms-1"></i></a>
                           </div>
                           <!-- item-->
                           <div class="dropdown-header mt-2">
                              <h6 class="text-overflow text-muted mb-1 text-uppercase">Pages</h6>
                           </div>
                           <!-- item-->
                           <a href="javascript:void(0);" class="dropdown-item notify-item">
                           <i class="ri-bubble-chart-line align-middle fs-18 text-muted me-2"></i>
                           <span>Analytics Dashboard</span>
                           </a>
                           <!-- item-->
                           <a href="javascript:void(0);" class="dropdown-item notify-item">
                           <i class="ri-lifebuoy-line align-middle fs-18 text-muted me-2"></i>
                           <span>Help Center</span>
                           </a>
                           <!-- item-->
                           <a href="javascript:void(0);" class="dropdown-item notify-item">
                           <i class="ri-user-settings-line align-middle fs-18 text-muted me-2"></i>
                           <span>My account settings</span>
                           </a>
                           <!-- item-->
                           <div class="dropdown-header mt-2">
                              <h6 class="text-overflow text-muted mb-2 text-uppercase">Members</h6>
                           </div>
                           <div class="notification-list">
                              <!-- item -->
                              <a href="javascript:void(0);" class="dropdown-item notify-item py-2">
                                 <div class="d-flex">
                                    <img src="assets/images/users/avatar-2.jpg" class="me-3 rounded-circle avatar-xs" alt="user-pic">
                                    <div class="flex-grow-1">
                                       <h6 class="m-0">Angela Bernier</h6>
                                       <span class="fs-11 mb-0 text-muted">Manager</span>
                                    </div>
                                 </div>
                              </a>
                              <!-- item -->
                              <a href="javascript:void(0);" class="dropdown-item notify-item py-2">
                                 <div class="d-flex">
                                    <img src="assets/images/users/avatar-3.jpg" class="me-3 rounded-circle avatar-xs" alt="user-pic">
                                    <div class="flex-grow-1">
                                       <h6 class="m-0">David Grasso</h6>
                                       <span class="fs-11 mb-0 text-muted">Web Designer</span>
                                    </div>
                                 </div>
                              </a>
                              <!-- item -->
                              <a href="javascript:void(0);" class="dropdown-item notify-item py-2">
                                 <div class="d-flex">
                                    <img src="assets/images/users/avatar-5.jpg" class="me-3 rounded-circle avatar-xs" alt="user-pic">
                                    <div class="flex-grow-1">
                                       <h6 class="m-0">Mike Bunch</h6>
                                       <span class="fs-11 mb-0 text-muted">React Developer</span>
                                    </div>
                                 </div>
                              </a>
                           </div>
                        </div>
                        <div class="text-center pt-3 pb-1">
                           <a href="pages-search-results.html" class="btn btn-primary btn-sm">View All Results <i class="ri-arrow-right-line ms-1"></i></a>
                        </div>
                     </div>
                  </form>
               </div>
               <div class="d-flex align-items-center">
                  <div class="dropdown d-md-none topbar-head-dropdown header-item">
                     <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle" id="page-header-search-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                     <i class="bx bx-search fs-22"></i>
                     </button>
                     <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0" aria-labelledby="page-header-search-dropdown">
                        <form class="p-3">
                           <div class="form-group m-0">
                              <div class="input-group">
                                 <input type="text" class="form-control" placeholder="Search ..." aria-label="Recipient's username">
                                 <button class="btn btn-primary" type="submit"><i class="mdi mdi-magnify"></i></button>
                              </div>
                           </div>
                        </form>
                     </div>
                  </div>
                  <div class="ms-1 header-item d-none d-sm-flex">
                     <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle" data-toggle="fullscreen">
                     <i class='bx bx-fullscreen fs-22'></i>
                     </button>
                  </div>
                  <div class="ms-1 header-item d-none d-sm-flex">
                     <a href="{{route('site.change_mode')}}">
                     <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle">
                     @if($value == "light")
                     <i class="bx bx-moon fs-22 fs-22"></i>
                     @else
                     <i class="ri-sun-fill fs-22"></i>
                     @endif
                     </button>
                     </a>
                  </div>
                  <div class="dropdown ms-sm-3 header-item topbar-user">
                     <button type="button" class="btn material-shadow-none" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                     <span class="d-flex align-items-center">
                     <img class="rounded-circle header-profile-user" src="{{asset('assets/images/users/avatar-1.png')}}" alt="Header Avatar">
                     <span class="text-start ms-xl-2">
                     <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{Auth::user()->name}}</span>
                     </span>
                     </span>
                     </button>
                     <div class="dropdown-menu dropdown-menu-end">
                        <!-- item-->
                        <h6 class="dropdown-header">مرحباً {{Auth::user()->name}} !</h6>
                        <a class="dropdown-item" href="pages-profile.html"><i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i> <span class="align-middle">
                        حسابي
                        </span></a>
                        <a class="dropdown-item" href="apps-chat.html"><i class="mdi mdi-message-text-outline text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Messages</span></a>
                        <a class="dropdown-item" href="apps-tasks-kanban.html"><i class="mdi mdi-calendar-check-outline text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Taskboard</span></a>
                        <a class="dropdown-item" href="pages-faqs.html"><i class="mdi mdi-lifebuoy text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Help</span></a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="pages-profile.html"><i class="mdi mdi-wallet text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Balance : <b>$5971.67</b></span></a>
                        <a class="dropdown-item" href="pages-profile-settings.html"><span class="badge bg-success-subtle text-success mt-1 float-end">New</span><i class="mdi mdi-cog-outline text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Settings</span></a>
                        <a class="dropdown-item" href="auth-lockscreen-basic.html"><i class="mdi mdi-lock text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Lock screen</span></a>
                        <a class="dropdown-item" href="auth-logout-basic.html"><i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> <span class="align-middle" data-key="t-logout">Logout</span></a>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </header>
      @endauth
      <!-- ========== App Menu ========== -->
      @auth
      <div class="app-menu navbar-menu">
         <!-- LOGO -->
         <div class="navbar-brand-box">
            <!-- Dark Logo-->
            <a href="{{route('site.index')}}" class="logo logo-dark">
            <span class="logo-sm">
            <img src="{{asset('assets/images/flymix_colored.png')}}" class="go_logo" height="">
            </span>
            <span class="logo-lg">
            <img src="{{asset('assets/images/flymix_colored.png')}}" class="go_logo" height="">
            </span>
            </a>
            <!-- Light Logo-->
            <a href="{{route('site.index')}}" class="logo logo-light">
            <span class="logo-sm">
            <img src="{{asset('assets/images/flymix_colored.png')}}" class="go_logo" height="">
            </span>
            <span class="logo-lg">
            <img src="{{asset('assets/images/flymix_colored.png')}}" class="go_logo" height="">
            </span>
            </a>
            <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
            <i class="ri-record-circle-line"></i>
            </button>
         </div>
         <div id="scrollbar">
            <div class="container-fluid">
               <div id="two-column-menu">
               </div>
               <ul class="navbar-nav" id="navbar-nav">
                  <li class="menu-title"><span data-key="t-menu">روابط سريعة</span></li>
                  <li class="nav-item">
                     <a class="nav-link menu-link" href="{{route('site.index')}}">
                     <i class="ri-home-smile-line"></i> <span data-key="t-widgets">الصفحة الرئيسية</span>
                     </a>
                  </li>
                   @if(Auth::user()->account_type == 2)
                  <li class="nav-item"> 
                     <a class="nav-link menu-link" href="{{route('site.suppliers')}}">
                     <i class="ri-group-line"></i> <span data-key="t-widgets">الموردين و العملاء</span>
                     </a>
                  </li>
                  <!--
                     <li class="nav-item">
                         <a class="nav-link menu-link" href="{{route('site.customers')}}">
                             <i class="ri-group-line"></i> <span data-key="t-widgets"></span>
                         </a>
                     </li>
                     -->
                  <!--
                     <li class="nav-item">
                         <a class="nav-link menu-link" href="{{route('site.expenses')}}">
                             <i class="ri-exchange-dollar-line"></i> <span data-key="t-widgets">المصروفات</span>
                         </a>
                     </li>
                     -->
                  <!--
                     <li class="nav-item">
                           <a class="nav-link menu-link" href="">
                                <i class="ri-bill-line"></i><span data-key="t-widgets">الفواتير</span>
                           </a>
                       </li>
                     -->
                  <li class="nav-item">
                     <a class="nav-link menu-link" href="{{route('site.airlines')}}">
                     <i class="ri-flight-takeoff-line"></i><span data-key="t-widgets">خطوط الطيران</span>
                     </a>
                  </li>
                  <li class="nav-item">
                     <a class="nav-link menu-link" href="{{route('site.collectors')}}">
                     <i class="ri-truck-line"></i><span data-key="t-widgets">المحصلين</span>
                     </a>
                  </li>
                 
                   @endif
                     <li class="nav-item">
                     <a class="nav-link menu-link" href="{{route('site.visas')}}">
                     <i class="ri-survey-line"></i><span data-key="t-widgets">التأشيرات</span>
                     </a>
                  </li>
                  <!--
                     <li class="nav-item">
                                       <a class="nav-link menu-link" href="{{route('site.profits')}}">
                                          <i class="ri-currency-line"></i><span data-key="t-widgets">الارباح</span>
                                       </a>
                                   </li>
                     -->
                   @if(Auth::user()->account_type == 2)
                  <li class="nav-item">
                     <a class="nav-link menu-link collapsed" href="#AccountsBar" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="AccountsBar">
                     <i class="ri-file-list-line"></i><span data-key="t-widgets">كشوفات الحسابات </span>
                     </a>
                     <div class="menu-dropdown collapse" id="AccountsBar" style="">
                        <ul class="nav nav-sm flex-column">
                           <li class="nav-item">
                              <a href="{{route('site.accounts_statement')}}" class="nav-link" data-key="t-basic-tables">كشف حساب شركات </a>
                           </li>
                            
                           <li class="nav-item">
                              <a href="{{route('site.accounts_statement_custom_get')}}" class="nav-link" data-key="t-grid-js">
                               كشف حساب مختصر
                               </a>
                           </li>
                            
                           <li class="nav-item">
                              <a href="{{route('site.accounts_statement_suppliers_get')}}" class="nav-link" data-key="t-grid-js">
                               كشف حساب موردين
                               </a>
                           </li>
                        </ul>
                     </div>
                  </li>
                   @endif
                  <li class="nav-item">
                     <a class="nav-link menu-link collapsed" href="#sidebarTables" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarTables">
                     <i class="ri-bill-line"></i><span data-key="t-tables">الفواتير</span>
                     </a>
                     <div class="menu-dropdown collapse" id="sidebarTables" style="">
                        <ul class="nav nav-sm flex-column">
                           <li class="nav-item">
                              <a href="{{route('site.invoices_ajax')}}" class="nav-link" data-key="t-basic-tables">كل الفواتير</a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.invoices_lite')}}" class="nav-link" data-key="t-basic-tables">فواتير اخر 3 شهور</a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.invoices_daily_report')}}" class="nav-link" data-key="t-basic-tables">التقرير اليومي</a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.shared_invoices')}}" class="nav-link" data-key="t-basic-tables">الفواتير المشتركة</a>
                           </li>
                            <li class="nav-item">
                              <a href="{{route('site.employee_log')}}" class="nav-link" data-key="t-basic-tables">
                                  
                            تقرير تذاكر وارباح الموظفين      
                                </a>
                           </li>
                            <li class="nav-item">
                              <a href="{{route('site.invoices_full_report')}}" class="nav-link" data-key="t-basic-tables">تقرير الفواتير التفصيلي</a>
                           </li>
                            <li class="nav-item">
                              <a href="{{route('site.air_cairo_calc')}}" class="nav-link" data-key="t-basic-tables">تكلفة اير كايرو</a>
                           </li>
                            
<!--
                           <li class="nav-item">
                              <a href="{{route('site.invoices_reissue')}}" class="nav-link" data-key="t-grid-js">تعديل فاتورة / اعادة اصدار</a>
                           </li>
-->
                        </ul>
                     </div>
                  </li>
                  <li class="nav-item">
                     <a class="nav-link menu-link collapsed" href="#sidebarTablesMarketing" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarTablesMarketing">
                     <i class="ri-price-tag-3-line"></i><span data-key="t-tables">التسويق</span>
                     </a>
                     <div class="menu-dropdown collapse" id="sidebarTablesMarketing" style="">
                        <ul class="nav nav-sm flex-column">
                           <li class="nav-item">
                              <a href="{{route('site.marketing_prices_all')}}" class="nav-link" data-key="t-basic-tables">كل القوائم</a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.marketing_prices')}}" class="nav-link" data-key="t-basic-tables">تسويق شركات</a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.marketing_prices_create')}}" class="nav-link" data-key="t-grid-js">انشاء جديد</a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.marketing_title_create')}}" class="nav-link" data-key="t-grid-js">انشاء بيان جديد</a>
                           </li>
                        </ul>
                     </div>
                  </li>
                  <li class="nav-item">
                     <a class="nav-link menu-link collapsed" href="#sidebarSafeArea" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarSafeArea">
                     <i class="ri-safe-line"></i><span data-key="t-tables">العمليات المالية</span>
                     </a>
                     <div class="menu-dropdown collapse" id="sidebarSafeArea" style="">
                        <ul class="nav nav-sm flex-column">
                            @if(Auth::user()->account_type == 2)
                           <li class="nav-item">
                              <a href="{{route('site.storages')}}" class="nav-link" data-key="t-basic-tables">
                              الخزائن
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.acc_all')}}" class="nav-link" data-key="t-basic-tables">
                              كشف حساب الخزائن
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.bonds')}}" class="nav-link" data-key="t-basic-tables">
                              السندات
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.bonds_daily_report')}}" class="nav-link" data-key="t-basic-tables">
                              القاصة اليومية
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.banks')}}" class="nav-link" data-key="t-basic-tables">حسابات البنوك</a>
                           </li>
                           <li class="nav-item">
                              <a href="{{route('site.expenses')}}" class="nav-link" data-key="t-basic-tables">المصروفات</a>
                           </li>
                            <li class="nav-item">
                              <a href="{{route('site.accounts_statement_trans_all')}}" class="nav-link" data-key="t-basic-tables">تقرير عمليات الربح والمصروف</a>
                           </li>
                            @endif
                           <li class="nav-item">
                              <a href="{{route('site.profits')}}" class="nav-link" data-key="t-basic-tables">الارباح</a>
                           </li>
                        </ul>
                     </div>
                  </li>
                    @if(Auth::user()->account_type == 2)
                  <li class="nav-item">
                     <a class="nav-link menu-link collapsed" href="#sidebarSettings" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarSettings">
                     <i class="ri-equalizer-line"></i><span data-key="t-tables">
                         اعدادات البرنامج
                         </span>
                     </a>
                     <div class="menu-dropdown collapse" id="sidebarSettings" style="">
                        <ul class="nav nav-sm flex-column">
                           <li class="nav-item">
                              <a href="{{route('site.admins')}}" class="nav-link" data-key="t-basic-tables">
                               حسابات الموظفين
                               </a>
                           </li>
                            <li class="nav-item">
                              <a href="{{route('site.password')}}" class="nav-link" data-key="t-basic-tables">
                               الحسابات وكلمات المرور
                               </a>
                           </li>
                            <li class="nav-item">
                              <a href="{{route('site.alerts')}}" class="nav-link" data-key="t-basic-tables">
                               الاشعارات والتنبيهات
                               </a>
                           </li>
                        </ul>
                     </div>
                  </li>
                   @endif
                  <li class="nav-item">
                     <a class="nav-link menu-link" href="{{route('site.my_account')}}">
                     <i class="ri-account-circle-line"></i><span data-key="t-widgets">حسابي</span>
                     </a>
                  </li>
                  <li class="nav-item">
                     <a class="nav-link menu-link" href="{{route('logout')}}">
                     <i class="ri-logout-box-line"></i> <span data-key="t-widgets">تسجيل خروج</span>
                     </a>
                  </li>
               </ul>
            </div>
            <!-- Sidebar -->
         </div>
         <div class="sidebar-background"></div>
      </div>
      @endauth
      <!-- Left Sidebar End -->
      <!-- Vertical Overlay-->
      <div class="vertical-overlay"></div>
      <!-- ============================================================== -->
      <!-- Start right Content here -->
      <!-- ============================================================== -->
      <div class="main-content">
         <div class="page-content">
            <div class="container-fluid">
               <div class="row">
                  <div class="col">
                      @if($rname == "site.index")
                      @else
                      <button class="btn btn-primary" onclick="window.history.go(-1); return false;">
                      رجوع للصفحة السابقة <i class="ri-arrow-left-circle-line"></i>
                      </button>
<br><br>                   
                      @endif
                     @yield('content')
                  </div>
                  <!-- end col -->
               </div>
            </div>
            <!-- container-fluid -->
         </div>
         <!-- End Page-content -->
         <footer class="footer">
            <div class="container-fluid">
               <div class="row">
                  <div class="col-sm-6">
                     <script>document.write(new Date().getFullYear())</script> © Flymix.
                  </div>
                  <div class="col-sm-6">
                     <div class="text-sm-end d-none d-sm-block">
                        <a href="https://cuoratech.com">
                        <img src="https://cuoratech.com/assets/images/cuoratech.png?v=372" class="dark_img" style="width: 59px;">
                        </a>
                        Created By 
                     </div>
                  </div>
               </div>
            </div>
         </footer>
      </div>
      <!-- end main content-->
   </div>
   <!-- END layout-wrapper -->
   <!--start back-to-top-->
   <button onclick="topFunction()" class="btn btn-danger btn-icon" id="back-to-top">
   <i class="ri-arrow-up-line"></i>
   </button>
   <!--end back-to-top-->
   <!--preloader-->
   <div id="preloader">
      <div id="status">
         <div class="spinner-border text-primary avatar-sm" role="status">
            <span class="visually-hidden">Loading...</span>
         </div>
      </div>
   </div>
   <!-- JAVASCRIPT -->
   <script src="{{asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
   <script src="{{asset('assets/libs/simplebar/simplebar.min.js')}}"></script>
   <script src="{{asset('assets/libs/node-waves/waves.min.js')}}"></script>
   <script src="{{asset('assets/libs/feather-icons/feather.min.js')}}"></script>
   <script src="{{asset('assets/js/pages/plugins/lord-icon-2.1.0.js')}}"></script>
   <script src="{{asset('assets/js/plugins.js')}}"></script>
   <!--     apexcharts -->
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
  
   <script>
      if (document.getElementById('myTable')) {
         let table = new DataTable('#myTable', {
            responsive: true,
         });
      }
      $.fn.dataTable.ext.errMode = 'none';

      // Shared delete-confirmation helper: submits a hidden CSRF-protected POST form
      // instead of navigating a bare GET link, after a SweetAlert confirmation.
      function confirmDeleteForm(formId, title, text) {
         swal({
            title: title || "هل انت متأكد؟",
            text: text || "لا يمكن التراجع عن هذا الإجراء",
            icon: "warning",
            buttons: true,
            dangerMode: true,
         }).then((willDelete) => {
            if (willDelete) {
               document.getElementById(formId).submit();
            } else {
               swal("تم الغاء عملية الحذف بنجاح");
            }
         });
      }

          var select_box_element = document.querySelector('#select_box');
          var select_box_element2 = document.querySelector('#select_box2');
          var select_box_element3 = document.querySelector('#select_box3');
          var select_box_element4 = document.querySelector('#box_4');
         
          var select_box2_t2 = document.querySelector('#select_box2_t2');
          var select_boxt2 = document.querySelector('#select_boxt2');
          var invoice_group = document.querySelector('#invoice_group_id');
       
       
     
          
         
      
      // Guarded: most of these target elements only exist on invoice-related
      // pages, and dselect.js may not have finished loading yet on a slow
      // connection. Without these guards, calling dselect() on a missing
      // element or before the script has loaded throws an uncaught
      // exception on every page load.
      if (typeof dselect === 'function') {
         [select_box_element, select_box_element2, select_box_element3, select_box_element4, select_box2_t2, select_boxt2, invoice_group]
            .forEach(function (el) {
               if (el) {
                  dselect(el, { search: true });
               }
            });
      }

      // Sidebar active-page highlighting. The compiled app.js bundle in this
      // build never included Velzon's own menu-active-state feature (no
      // "mm-active" logic present at all), so the sidebar never showed which
      // page you're on. Matches each menu link's URL against the current
      // path, picks the most specific match, marks it active, and expands
      // its parent accordion section if it's nested inside one.
      (function () {
         var current = window.location.pathname.replace(/\/+$/, '') || '/';
         var best = null;
         document.querySelectorAll('.app-menu .navbar-nav .nav-link[href]').forEach(function (link) {
            var raw = link.getAttribute('href');
            if (!raw || raw.charAt(0) === '#') {
               return;
            }
            var linkPath = link.pathname.replace(/\/+$/, '') || '/';
            var isMatch = linkPath === current || (linkPath !== '/' && current.indexOf(linkPath + '/') === 0);
            if (isMatch && (!best || linkPath.length > best.pathLen)) {
               best = { link: link, pathLen: linkPath.length };
            }
         });
         if (best) {
            best.link.classList.add('active');
            var section = best.link.closest('.menu-dropdown');
            while (section) {
               section.classList.add('show');
               var toggle = document.querySelector('[aria-controls="' + section.id + '"]');
               if (toggle) {
                  toggle.classList.remove('collapsed');
                  toggle.setAttribute('aria-expanded', 'true');
               }
               section = toggle ? toggle.closest('.menu-dropdown') : null;
            }
         }
      })();
   </script>
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