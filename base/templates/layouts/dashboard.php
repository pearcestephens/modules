@extends('layouts.base')

@section('body-class', 'layout-dashboard')

@section('content')
<div class="dashboard-wrapper">

    <!-- Sidebar -->
    @component('components.sidebar')

    <!-- Main Content Area -->
    <div class="dashboard-main">

        <!-- Header -->
        @component('components.header')

        <!-- Page Content -->
        <main class="dashboard-content">
            <div class="container-fluid py-4">

                <!-- Breadcrumbs -->
                @if(!empty($breadcrumbs))
                    @component('components.breadcrumbs')
                @endif

                <!-- Page Title -->
                @if(!empty($pageTitle))
                <div class="page-header mb-4">
                    <h1 class="h3 mb-0">{{ $pageTitle }}</h1>
                    @if(!empty($pageDescription))
                        <p class="text-muted">{{ $pageDescription }}</p>
                    @endif
                </div>
                @endif

                <!-- Alerts -->
                @component('components.alerts')

                <!-- Main Content -->
                @yield('dashboard-content')

            </div>
        </main>

        <!-- Footer -->
        @component('components.footer')

    </div>

</div>

<style>
    .dashboard-wrapper {
        display: flex;
        min-height: 100vh;
    }

    .dashboard-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        margin-left: 260px;
        transition: margin-left 0.3s;
    }

    .dashboard-content {
        flex: 1;
        padding-top: 60px;
    }

    @media (max-width: 768px) {
        .dashboard-main {
            margin-left: 0;
        }
    }
</style>

@endsection
