@extends('emails.email_template')

@section('title', $titleText)

@section('header', $titleText)

@section('content')
    {!! $bodyHtml !!}

    @if(!empty($attachmentLinks))
        <div style="margin-top: 24px; padding: 14px 16px; background: #f8fbff; border: 1px solid #dbeafe; border-radius: 12px;">
            <p style="margin: 0 0 10px; font-size: 14px; color: #0f172a;"><strong>Archivos adjuntos</strong></p>
            <ul style="margin: 0; padding: 0; list-style: none;">
                @foreach($attachmentLinks as $attachmentLink)
                    <li style="margin-bottom: 8px;">
                        <a
                            href="{{ $attachmentLink['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="display: inline-block; padding: 8px 12px; border-radius: 8px; background: #ffffff; border: 1px solid #bfdbfe; color: #1d4ed8; text-decoration: none; font-size: 14px;"
                        >
                            {{ $attachmentLink['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
