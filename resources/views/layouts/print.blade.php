<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet" integrity="sha384-dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb" crossorigin="anonymous">
    <style>
      @import url('https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@100..900&display=swap');

      :root{
          --ey-brand: #1b3a5c;
          --ey-brand-light: #eef3f8;
          --ey-border: #d7dee6;
          --ey-muted: #6c7a89;
      }

      *{
          font-family: "Noto Kufi Arabic", sans-serif;
      }

      html, body{
          background: #fff;
      }

      body{
          color: #212529;
          font-size: 13px;
      }

      /* ---- Eyana print header ---- */
      .ey-print-header{
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 16px;
          border-bottom: 2px solid var(--ey-brand);
          padding-bottom: 10px;
          margin-bottom: 16px;
      }
      .ey-print-header img{
          height: 42px;
      }
      .ey-print-header .ey-print-title{
          text-align: center;
          flex: 1;
      }
      .ey-print-header .ey-print-title h4{
          margin: 0;
          color: var(--ey-brand);
          font-weight: 700;
      }
      .ey-print-header .ey-print-title small{
          color: var(--ey-muted);
      }
      .ey-print-header .ey-print-meta{
          text-align: start;
          font-size: 11px;
          color: var(--ey-muted);
          white-space: nowrap;
      }

      .ey-print-filters{
          background: var(--ey-brand-light);
          border: 1px solid var(--ey-border);
          border-radius: 4px;
          padding: 8px 14px;
          margin-bottom: 14px;
          font-size: 12px;
          display: flex;
          flex-wrap: wrap;
          gap: 4px 22px;
      }
      .ey-print-filters .ey-print-filter-label{
          color: var(--ey-muted);
      }

      .ey-print-footer{
          position: fixed;
          bottom: 0;
          left: 0;
          right: 0;
          border-top: 1px solid var(--ey-border);
          padding-top: 6px;
          font-size: 10px;
          color: var(--ey-muted);
          display: flex;
          justify-content: space-between;
      }

      /* ---- Print tables ---- */
      table.ey-print-table{
          width: 100%;
          border-collapse: collapse;
      }
      table.ey-print-table thead{
          display: table-header-group; /* repeat header on every printed page */
      }
      table.ey-print-table tfoot{
          display: table-footer-group;
      }
      table.ey-print-table th,
      table.ey-print-table td{
          border: 1px solid var(--ey-border);
          padding: 6px 8px;
          font-size: 12px;
      }
      table.ey-print-table thead th{
          background: var(--ey-brand-light);
          color: var(--ey-brand);
          font-weight: 700;
      }
      table.ey-print-table tr{
          page-break-inside: avoid;
      }
      table.ey-print-table tfoot td,
      table.ey-print-table tr.ey-total-row td{
          font-weight: 700;
          background: var(--ey-brand-light);
      }

      main{
          padding: 18px 20px 60px;
      }

      @page{
          size: A4;
          margin: 14mm 12mm 18mm;
      }

      @media print{
          .ey-no-print{
              display: none !important;
          }
          a{
              color: inherit;
              text-decoration: none;
          }
          main{
              padding: 0 0 50px;
          }
          /* Laravel Debugbar injects this into every response in local/debug
             environments; it must never show up in print output. */
          #phpdebugbar,
          .phpdebugbar,
          .phpdebugbar-openhandler-overlay{
              display: none !important;
          }
      }

      @media screen{
          body{
              background: #eceff3;
          }
          main{
              max-width: 210mm;
              margin: 16px auto;
              background: #fff;
              box-shadow: 0 0 8px rgba(0,0,0,.12);
              min-height: 297mm;
          }
      }

      @yield('extra-style')
    </style>
  </head>
  <body>
      <main>
    @yield('content')
</main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <div class="ey-no-print" style="position:fixed; bottom:16px; left:16px; z-index:50;">
        <button class="btn btn-primary shadow" onclick="window.print()">
            <i class="ri-printer-line"></i> طباعة
        </button>
    </div>
  </body>
</html>
