@extends('lookout::layouts.app')
@section('title', 'Edit threshold rule')
@section('content')
<div class="page-title-row">
    <span class="pt">Edit threshold rule</span>
    <span class="psub">{{ $rule['name'] ?? 'Threshold rule' }}</span>
</div>

<form method="POST" action="{{ route('lookout.alerts.rules.update', $rule['id']) }}">
    @csrf
    @method('PUT')
    @include('lookout::alerts.rules._form', ['submitLabel' => 'Save changes'])
</form>
@endsection
