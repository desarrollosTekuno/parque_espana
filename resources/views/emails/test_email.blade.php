@extends('emails.email_template')

@section('title', $subjectText)

@section('header', 'Prueba de correo SMTP')

@section('content')
    <p>Se realizo una prueba de envio de correo con configuracion SMTP dinamica.</p>
    <p><strong>Entidad:</strong> {{ $entityId }}</p>
    <p><strong>Mensaje:</strong><br>{!! nl2br(e($messageText)) !!}</p>
@endsection
