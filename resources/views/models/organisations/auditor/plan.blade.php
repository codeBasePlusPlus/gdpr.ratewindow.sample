@extends('layouts/contentLayoutMaster')
@section('title', trans('messages.plan') . ' ' . trans('messages.review'))
@section('vendor-style')
    {{-- vendor css files --}}
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/charts/apexcharts.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/toastr.min.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/forms/select/select2.min.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/dataTables.bootstrap5.min.css')) }}">
@endsection
@section('page-style')
    <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/charts/chart-apex.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/extensions/ext-component-toastr.css')) }}">
    <style>
        .review-type-alert {
            width: 20px;
            height: 20px;
        }
    </style>
@endsection
@section('content')
    @if (session()->get('success'))
        <div class="alert alert-success" role="alert">
            <h4 class="alert-heading">@lang('messages.success')!</h4>
            <div class="alert-body">
                {{ session()->get('success') }}.
            </div>
        </div>
    @endif
    @if (session()->get('error'))
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading">@lang('messages.error')!</h4>
            <div class="alert-body">
                {{ session()->get('error') }}.
            </div>
        </div>
    @endif
    <organisation-auditor-plan locale="{{ App::currentLocale() }}" action-id="{{ $actionId }}"/>
@endsection
@section('vendor-script')
    {{-- vendor files --}}
    <script src="{{ asset(mix('vendors/js/charts/apexcharts.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/forms/select/select2.full.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/extensions/moment.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/extensions/toastr.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/tables/datatable/jquery.dataTables.min.js')) }}"></script>
@endsection
@section('page-script')
    <script src="{{ asset(mix('js/models/organisations/plan/auditor/app.js')) }}"></script>
@endsection
