@extends('smartacars::layouts.frontend')

@section('content')
    <h1>Hello World</h1>

    <p>
        This view is loaded from module: {{ config('smartacars.name') }}
    </p>
@endsection
