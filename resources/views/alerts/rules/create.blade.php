@extends('lookout::layouts.app')
@section('title', 'Create threshold rule')
@section('content')
<div class="page-title-row">
    <span class="pt">Create threshold rule</span>
    <span class="psub">Evaluate Lookout metrics against a threshold and notify configured channels</span>
</div>

<form method="POST" action="{{ route('lookout.alerts.rules.store') }}">
    @csrf
    @include('lookout::alerts.rules._form', ['submitLabel' => 'Create rule'])
</form>
@endsection
