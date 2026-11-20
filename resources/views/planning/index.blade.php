@extends('layouts.app')
@section('title', ['budgets'=>'Presupuestos','alerts'=>'Alertas y notificaciones','notifications'=>'Notificaciones'][$workspacePage] ?? 'FinanzApp')
@section('content')
@include('planning._workspace')
@endsection
