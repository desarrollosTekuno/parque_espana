@extends('emails.email_template')

@section('title', $titleText)

@section('header', $titleText)

@section('content')
    {!! $bodyHtml !!}
@endsection
