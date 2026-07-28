<?php


namespace App\Jobs;

use App\Mail\VerificationCodeMail;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendVerificationCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        public User $user,
        public string $code,
    ) {
        $this->onQueue('critical');
    }

    public function handle(): void
    {
        $mail = new VerificationCodeMail(
            user: $this->user,
            code: $this->code,
        );

        Mail::to($this->user->email)->send($mail);

        EmailLog::create([
            'mailable' => VerificationCodeMail::class,
            'recipient_email' => $this->user->email,
            'subject' => $mail->envelope()->subject,
            'loggable_type' => User::class,
            'loggable_id' => $this->user->id,
            'sent_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        EmailLog::create([
            'mailable' => VerificationCodeMail::class,
            'recipient_email' => $this->user->email,
            'subject' => 'Verify your account',
            'loggable_type' => User::class,
            'loggable_id' => $this->user->id,
            'failed_at' => now(),
            'failure_reason' => $exception->getMessage(),
        ]);

        logger()->error('SendVerificationCodeJob permanently failed', [
            'user_id' => $this->user->id,
            'user_email' => $this->user->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
