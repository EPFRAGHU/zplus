@extends('errors.layout')
@section('code', '403')
@section('title', 'Not allowed')
@section('message'){{ $exception?->getMessage() ?: 'Your login does not have permission for this page or action. Ask a Super Admin if you need it.' }}@endsection
