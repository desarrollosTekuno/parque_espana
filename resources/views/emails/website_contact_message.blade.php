@extends('emails.email_template')

@section('title', 'Nuevo mensaje de contacto')

@section('header', 'Nuevo mensaje de contacto')

@section('content')
    <p><strong>Nombre:</strong> {{ $contactMessage->name }}</p>
    <p><strong>Correo electrónico:</strong> {{ $contactMessage->email }}</p>
    <p><strong>Asunto:</strong> {{ $contactMessage->subject }}</p>
    <p><strong>Mensaje:</strong></p>
    <p style="white-space: pre-line;">{{ $contactMessage->message }}</p>
@endsection
