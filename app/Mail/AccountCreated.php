<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class AccountCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Here are your next steps in '.config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $password = Str::random(12);
        $tokenPassword =  (string)Str::ulid() . Str::random(10);
        $this->user->password = bcrypt($password);
        $this->user->token_password = $tokenPassword;
        $this->user->save();

        return new Content(
            // view: 'emails.account-created',
            markdown: 'emails.account-created',
            with: [
                // 'url' => route('setup.login') config('app.url').config('nova.path'),
                'url' => route('setup.login', $tokenPassword),
                'user' => $this->user,
                'password' => $password,
                'token_password' => $tokenPassword,
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
