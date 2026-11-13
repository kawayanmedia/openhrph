<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Assistant
    |--------------------------------------------------------------------------
    |
    | Settings for the in-app HR assistant. The provider and model fall back to
    | the application's default AI provider when left empty, so the assistant
    | follows the same credentials configured in config/ai.php.
    |
    */

    'provider' => env('ASSISTANT_PROVIDER'),

    'model' => env('ASSISTANT_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Tool-using turns can take a while, so the agent gets a longer timeout
    | than a plain chat completion.
    |
    */

    'timeout' => (int) env('ASSISTANT_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Default Hours Threshold
    |--------------------------------------------------------------------------
    |
    | Used when a question refers to an hours shortfall ("who is under 80?").
    |
    */

    'default_under_hours' => (float) env('ASSISTANT_UNDER_HOURS', 80),

    /*
    |--------------------------------------------------------------------------
    | Result Limits
    |--------------------------------------------------------------------------
    |
    | Caps the rows returned to the model so a prompt cannot flood its context.
    |
    */

    'max_result_rows' => 30,

    'recent_conversations' => 12,

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prompts allowed per user, per minute. Keeps a runaway client from
    | burning provider credits.
    |
    */

    'prompts_per_minute' => (int) env('ASSISTANT_PROMPTS_PER_MINUTE', 20),

];
