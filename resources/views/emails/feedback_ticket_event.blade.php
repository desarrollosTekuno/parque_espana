@extends($templateView)

@section('title', $subjectText)

@section('header', $headerText)

@section('content')
    <p>{!! nl2br(e($messageText)) !!}</p>

    <p><strong>Folio:</strong> {{ $ticketData['ticket_number'] ?? '-' }}</p>
    <p><strong>Titulo:</strong> {{ $ticketData['title'] ?? '-' }}</p>
    <p><strong>Estatus:</strong> {{ $ticketData['status'] ?? '-' }}</p>
    <p><strong>Prioridad:</strong> {{ $ticketData['priority'] ?? '-' }}</p>
    <p><strong>Categoria:</strong> {{ $ticketData['category'] ?? '-' }}</p>
@endsection
