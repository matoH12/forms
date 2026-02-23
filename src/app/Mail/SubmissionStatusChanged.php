<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubmissionStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public FormSubmission $submission
    ) {}

    /**
     * Get the appropriate email template based on submission status.
     */
    private function getEmailTemplate(): ?EmailTemplate
    {
        $form = $this->submission->form;

        if ($this->submission->status === 'approved' && $form->approval_email_template_id) {
            return EmailTemplate::find($form->approval_email_template_id);
        } elseif ($this->submission->status === 'rejected' && $form->rejection_email_template_id) {
            return EmailTemplate::find($form->rejection_email_template_id);
        }

        return null;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $template = $this->getEmailTemplate();

        // Use template subject if available
        if ($template) {
            return new Envelope(
                subject: $template->renderSubject($this->submission),
            );
        }

        $status = $this->submission->status === 'approved' ? 'schvalena' : 'zamietnuta';

        return new Envelope(
            subject: "Vasa ziadost bola {$status} - {$this->submission->form->localized_name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $template = $this->getEmailTemplate();

        // Use template body if available
        if ($template) {
            return new Content(
                htmlString: $template->renderHtml($this->submission),
            );
        }

        return new Content(
            markdown: 'emails.submission-status',
            with: [
                'submission' => $this->submission,
                'form' => $this->submission->form,
                'isApproved' => $this->submission->status === 'approved',
                'adminResponse' => $this->submission->admin_response,
                'reviewedAt' => $this->submission->reviewed_at,
                'url' => config('app.url') . '/my/submissions/' . $this->submission->id,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
