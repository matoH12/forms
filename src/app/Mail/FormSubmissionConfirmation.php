<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormSubmissionConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public FormSubmission $submission;
    public EmailTemplate $template;

    public function __construct(FormSubmission $submission, EmailTemplate $template)
    {
        $this->submission = $submission;
        $this->template = $template;
    }

    public function envelope(): Envelope
    {
        $subject = $this->template->subject;

        // Get localized form name (handle both array and string)
        $formName = $this->submission->form->name ?? '';
        if (is_array($formName)) {
            $formName = $formName['sk'] ?? $formName['en'] ?? '';
        } elseif (is_object($formName)) {
            $formName = $formName->sk ?? $formName->en ?? '';
        }

        // Replace placeholders in subject
        $subject = str_replace('{{form_name}}', $formName, $subject);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.submission-confirmation',
            with: [
                'htmlContent' => $this->template->renderHtml($this->submission),
                'textContent' => $this->template->renderText($this->submission),
            ],
        );
    }
}
