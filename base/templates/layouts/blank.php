@extends('layouts.base')

@section('body-class', 'layout-blank')

@section('content')
<main class="blank-content">
    @yield('page-content')
</main>

<style>
    .layout-blank .blank-content {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

@endsection
