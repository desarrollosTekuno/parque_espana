@extends('emails.email_template')

@section('title', 'Tickets de acceso — Pase por día')
@section('header', 'Tickets de acceso')

@section('content')
    @if ($specificVisitor)
        {{-- Ticket individual para el visitante --}}
        <p style="font-size: 15px; color: #0f172a; margin: 0 0 6px;">
            Hola, <strong>{{ $specificVisitor->first_name }} {{ $specificVisitor->last_name }}</strong>
        </p>
        <p style="font-size: 14px; color: #475569; margin: 0 0 24px;">
            El socio <strong>{{ $member->full_name }}</strong> te ha registrado como visitante del club
            para el día <strong>{{ $date }}</strong>. Presenta este código QR en recepción para ingresar.
        </p>

        @php $visitor = $specificVisitor; @endphp
        <div style="margin-bottom: 28px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="vertical-align: top; padding-right: 20px;">
                        <p style="margin: 0 0 4px; font-size: 18px; font-weight: 700; color: #0f172a;">
                            {{ $visitor->first_name }} {{ $visitor->last_name }}
                        </p>
                        <p style="margin: 0 0 4px; font-size: 13px; color: #64748b;">
                            Edad: {{ $visitor->age }} años
                        </p>
                        @if ($visitor->email)
                            <p style="margin: 0 0 4px; font-size: 13px; color: #64748b;">
                                Correo: {{ $visitor->email }}
                            </p>
                        @endif
                        <p style="margin: 12px 0 0; font-size: 12px; color: #94a3b8; font-family: monospace;">
                            {{ $visitor->ticket_code }}
                        </p>
                    </td>
                    <td style="vertical-align: top; text-align: center; width: 140px;">
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data={{ urlencode($visitor->ticket_code) }}"
                            alt="QR {{ $visitor->first_name }}"
                            width="130"
                            height="130"
                            style="display: block; border-radius: 8px;"
                        />
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 16px; padding: 14px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
            <p style="margin: 0; font-size: 13px; color: #14532d;">
                Tu acceso es responsabilidad del socio que te registró. Respeta el reglamento del club durante tu visita.
            </p>
        </div>

    @else
        {{-- Ticket completo para el socio responsable --}}
        <p style="font-size: 15px; color: #0f172a; margin: 0 0 6px;">
            Hola, <strong>{{ $member->full_name }}</strong>
        </p>
        <p style="font-size: 14px; color: #475569; margin: 0 0 24px;">
            A continuación encontrarás los tickets de acceso para tus invitados del <strong>{{ $date }}</strong>.
            Cada visitante deberá mostrar su código QR al personal de recepción para ingresar al club.
        </p>

        @foreach ($dayPass->visitors as $visitor)
            <div style="margin-bottom: 28px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="vertical-align: top; padding-right: 20px;">
                            <p style="margin: 0 0 4px; font-size: 18px; font-weight: 700; color: #0f172a;">
                                {{ $visitor->first_name }} {{ $visitor->last_name }}
                            </p>
                            <p style="margin: 0 0 4px; font-size: 13px; color: #64748b;">
                                Edad: {{ $visitor->age }} años
                            </p>
                            @if ($visitor->email)
                                <p style="margin: 0 0 4px; font-size: 13px; color: #64748b;">
                                    Correo: {{ $visitor->email }}
                                </p>
                            @endif
                            <p style="margin: 12px 0 0; font-size: 12px; color: #94a3b8; font-family: monospace;">
                                {{ $visitor->ticket_code }}
                            </p>
                        </td>
                        <td style="vertical-align: top; text-align: center; width: 140px;">
                            <img
                                src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data={{ urlencode($visitor->ticket_code) }}"
                                alt="QR {{ $visitor->first_name }}"
                                width="130"
                                height="130"
                                style="display: block; border-radius: 8px;"
                            />
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach

        <div style="margin-top: 16px; padding: 14px 16px; background: #fef9c3; border: 1px solid #fde68a; border-radius: 10px;">
            <p style="margin: 0; font-size: 13px; color: #713f12;">
                <strong>Recuerda:</strong> Como socio responsable, eres responsable del comportamiento de tus invitados
                dentro de las instalaciones del club. En caso de daños, se aplicará la multa correspondiente.
            </p>
        </div>
    @endif
@endsection
