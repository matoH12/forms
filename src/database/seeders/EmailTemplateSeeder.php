<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update default thank you template
        EmailTemplate::updateOrCreate(
            ['slug' => 'dakovny-email'],
            [
                'name' => 'Ďakovný email / Thank you email',
                'subject' => 'Ďakujeme za vyplnenie formulára / Thank you for your submission - {{form_name}}',
                'body_html' => $this->getDefaultHtml(),
                'body_text' => $this->getDefaultText(),
                'include_submission_data' => true,
                'is_default' => true,
                'is_active' => true,
            ]
        );

        // Create or update new submission admin notification template
        EmailTemplate::updateOrCreate(
            ['slug' => 'new-submission-admin'],
            [
                'name' => 'Notifikácia o novej odpovedi / New submission notification',
                'system_type' => EmailTemplate::TYPE_NEW_SUBMISSION_ADMIN,
                'subject' => 'Nová odpoveď: {{form_name}} / New submission: {{form_name}}',
                'body_html' => $this->getNewSubmissionAdminHtml(),
                'body_text' => $this->getNewSubmissionAdminText(),
                'include_submission_data' => true,
                'is_default' => false,
                'is_active' => true,
            ]
        );
    }

    private function getDefaultHtml(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <!-- Slovak version -->
    <div style="margin-bottom: 32px;">
        <h2 style="color: #1a365d; margin-bottom: 16px;">Ďakujeme za vyplnenie formulára</h2>
        <p style="color: #4a5568; line-height: 1.6;">
            Vážený/á {{user_name}},
        </p>
        <p style="color: #4a5568; line-height: 1.6;">
            Ďakujeme za vyplnenie formulára <strong>{{form_name}}</strong>.
            Vaša odpoveď bola úspešne prijatá a bude spracovaná.
        </p>
        <p style="color: #4a5568; line-height: 1.6;">
            Dátum odoslania: {{submission_date}}
        </p>
        <p style="color: #4a5568; line-height: 1.6;">
            Ak máte akékoľvek otázky, neváhajte nás kontaktovať.
        </p>
        <p style="color: #6b7280; margin-top: 24px; font-size: 14px;">
            S pozdravom,<br>
            Forms Team
        </p>
    </div>

    <!-- Divider -->
    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 32px 0;">

    <!-- English version -->
    <div style="color: #6b7280;">
        <h2 style="color: #374151; margin-bottom: 16px; font-size: 18px;">Thank you for completing the form</h2>
        <p style="line-height: 1.6;">
            Dear {{user_name}},
        </p>
        <p style="line-height: 1.6;">
            Thank you for completing the form <strong>{{form_name}}</strong>.
            Your submission has been successfully received and will be processed.
        </p>
        <p style="line-height: 1.6;">
            Submission date: {{submission_date}}
        </p>
        <p style="line-height: 1.6;">
            If you have any questions, please do not hesitate to contact us.
        </p>
        <p style="margin-top: 24px; font-size: 14px;">
            Best regards,<br>
            Forms Team
        </p>
    </div>
</div>
HTML;
    }

    private function getDefaultText(): string
    {
        return <<<TEXT
Ďakujeme za vyplnenie formulára

Vážený/á {{user_name}},

Ďakujeme za vyplnenie formulára {{form_name}}.
Vaša odpoveď bola úspešne prijatá a bude spracovaná.

Dátum odoslania: {{submission_date}}

Ak máte akékoľvek otázky, neváhajte nás kontaktovať.

S pozdravom,
Forms Team

---

Thank you for completing the form

Dear {{user_name}},

Thank you for completing the form {{form_name}}.
Your submission has been successfully received and will be processed.

Submission date: {{submission_date}}

If you have any questions, please do not hesitate to contact us.

Best regards,
Forms Team
TEXT;
    }

    private function getNewSubmissionAdminHtml(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <!-- Slovak version -->
    <div style="margin-bottom: 32px;">
        <h2 style="color: #1a365d; margin-bottom: 16px;">Nová odpoveď na formulár</h2>
        <p style="color: #4a5568; line-height: 1.6;">
            Na formulár <strong>{{form_name}}</strong> prišla nová odpoveď.
        </p>

        <table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 12px 8px; color: #6b7280; width: 40%;">Odosielateľ:</td>
                <td style="padding: 12px 8px; color: #111827;"><strong>{{user_name}}</strong> ({{user_email}})</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 12px 8px; color: #6b7280;">Dátum:</td>
                <td style="padding: 12px 8px; color: #111827;">{{submission_date}}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 12px 8px; color: #6b7280;">ID odpovede:</td>
                <td style="padding: 12px 8px; color: #111827;">#{{submission_id}}</td>
            </tr>
        </table>

        <p style="margin-top: 24px;">
            <a href="{{submission_url}}" style="display: inline-block; padding: 12px 24px; background-color: #1a365d; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 500;">
                Zobraziť detail odpovede
            </a>
        </p>

        <p style="color: #9ca3af; font-size: 12px; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            Tento email ste dostali, pretože máte zapnuté notifikácie o nových odpovediach.
        </p>
    </div>

    <!-- Divider -->
    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 32px 0;">

    <!-- English version -->
    <div style="color: #6b7280;">
        <h2 style="color: #374151; margin-bottom: 16px; font-size: 18px;">New form submission</h2>
        <p style="line-height: 1.6;">
            A new submission has been received for <strong>{{form_name}}</strong>.
        </p>

        <table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 12px 8px; color: #6b7280; width: 40%;">Submitter:</td>
                <td style="padding: 12px 8px; color: #374151;"><strong>{{user_name}}</strong> ({{user_email}})</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 12px 8px; color: #6b7280;">Date:</td>
                <td style="padding: 12px 8px; color: #374151;">{{submission_date}}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 12px 8px; color: #6b7280;">Submission ID:</td>
                <td style="padding: 12px 8px; color: #374151;">#{{submission_id}}</td>
            </tr>
        </table>

        <p style="margin-top: 24px;">
            <a href="{{submission_url}}" style="display: inline-block; padding: 10px 20px; background-color: #4b5563; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 14px;">
                View submission details
            </a>
        </p>

        <p style="color: #9ca3af; font-size: 12px; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You received this email because you have enabled notifications for new submissions.
        </p>
    </div>
</div>
HTML;
    }

    private function getNewSubmissionAdminText(): string
    {
        return <<<TEXT
Nová odpoveď na formulár
========================

Na formulár {{form_name}} prišla nová odpoveď.

Odosielateľ: {{user_name}} ({{user_email}})
Dátum: {{submission_date}}
ID odpovede: #{{submission_id}}

Zobraziť detail: {{submission_url}}

---

Tento email ste dostali, pretože máte zapnuté notifikácie o nových odpovediach.

---

New form submission
===================

A new submission has been received for {{form_name}}.

Submitter: {{user_name}} ({{user_email}})
Date: {{submission_date}}
Submission ID: #{{submission_id}}

View details: {{submission_url}}

---

You received this email because you have enabled notifications for new submissions.
TEXT;
    }
}
