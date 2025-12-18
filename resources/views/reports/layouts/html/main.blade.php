@include('reports.layouts.html.header')

  <body>
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top shadow-sm">
            <div class="container-fluid">
                <ul class="navbar-nav ms-auto"> <!-- pushes to right -->
                @if(!request()->routeIs('all-guest-report'))
                    <li class="nav-item me-2">
                        <a class="btn btn-danger" href="{{ $data['routes']['pdf'] }}" target="_blank"> <i class="fa-solid fa-file-pdf me-1"></i> PDF</a>
                    </li>
                @endif
                <li class="nav-item">
                    <a class="btn btn-success" href="{{ $data['routes']['excel'] }}"><i class="fa-solid fa-file-excel me-1"></i> EXCEL</a>
                </li>
                </ul>
            </div>
            </nav>
            <div  class="d-flex justify-content-between mt-3">
                <div class="">
                    <img src="{{asset('reports/images/report-logo.png')}}" height="65px" alt="MDB Logo" loading="lazy" />
                </div>
                <div class="">
                    <p> PRINT TIME: {{date(config('ahits.datetimeformat'))}}</p>
                </div>
            </div>
        @yield('content')
        @include('reports.layouts.html.footer')

