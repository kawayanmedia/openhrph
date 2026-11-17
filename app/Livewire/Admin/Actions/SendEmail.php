<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Text;
use App\Mail\SendMarketingEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendEmail extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Send Email';

    public string $confirmText = 'Send Email?';

    public string $confirmButtonText = 'Send';

    public function __construct($model = null)
    {
        parent::__construct($model);
    }

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        foreach ($models as $model) {
            Mail::to($model)->send(new SendMarketingEmail($model, (object) $fields));
        }

        return self::message('Email has been sent!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Text::make('Subject', 'subject'),
            Text::make('Content', 'content'),
        ];
    }
}
