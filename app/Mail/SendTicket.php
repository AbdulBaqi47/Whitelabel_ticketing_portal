<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class SendTicket extends Mailable
{
    use Queueable, SerializesModels;
    public $booking;
    public $_view;
    public $booking_pdf;
    /**
     * Create a new message instance.
     */
    public function __construct($booking, $booking_pdf)
    {
        $this->booking = $booking;
        $this->booking_pdf = $booking_pdf;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $booking_detail = request()->email_type == 'send_booking_email' ? 'Booking Detail': 'Ticket Detail';
        return new Envelope(
            subject: $booking_detail ." - ". $this->booking['booking_pnr'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'pdf.booking',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [storage_path('/app/public/booking-pdf/'.basename($this->booking_pdf))];
    }
}
