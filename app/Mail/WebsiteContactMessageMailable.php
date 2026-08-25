<?php

namespace App\Mail;

use App\Models\Website\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WebsiteContactMessageMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Nuevo mensaje de contacto: ' . $this->contactMessage->subject)
            ->replyTo($this->contactMessage->email, $this->contactMessage->name)
            ->view('emails.website_contact_message');
    }
}
