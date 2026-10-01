@extends('layouts.base')

@section('body-class', 'bg-light')

@section('content')
    <div class="app-wrapper">
        <!-- Sidebar Backdrop for Mobile -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- Sidebar Navigation -->
        @include('layouts.partials.sidebar')

        <!-- Main Application Column -->
        <div class="app-main">
            <!-- Topbar Navigation -->
            @include('layouts.partials.topbar')

            <!-- Main Page Content -->
            <main class="app-content">
                {{-- Flash Messages --}}
                @include('layouts.partials.flash-messages')

                {{-- Page Header --}}
                @hasSection('header')
                    <div class="mb-4">
                        @yield('header')
                    </div>
                @endif

                {{-- Primary Body Slot --}}
                @yield('main')
            </main>

            <!-- Application Footer -->
            @include('layouts.partials.footer')
        </div>
    </div>
@endsection
