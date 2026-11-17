<?php

namespace App\Nova\Actions;

use App\Mail\SendMarketingEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class SendEmail extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $name = 'Send Email';

    public $confirmText = 'Send Email?';

    public $confirmButtonText = 'Send';

    public $model;

    public function __construct($model = null)
    {
        $this->model = $model;
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            // $model->status = STATUS_APPROVED;
            // $model->save();
            // Mail::queue()->to($model)->send(new SendMarketingEmail($model, $fields));
            Mail::to($model)->send(new SendMarketingEmail($model, $fields));
        }

        return Action::message('Email has been sent!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make('Subject'),
            Text::make('Content'),
        ];
    }
}
