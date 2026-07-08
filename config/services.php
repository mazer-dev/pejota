<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),
        'audio_max_mb' => (int) env('OPENAI_AUDIO_MAX_MB', 25),
        'audio_transcription_model' => env('OPENAI_AUDIO_TRANSCRIPTION_MODEL', 'gpt-4o-transcribe'),
        'audio_transcription_prompt' => env('OPENAI_AUDIO_TRANSCRIPTION_PROMPT', 'Transcreva em português do Brasil, preservando nomes próprios, termos técnicos, valores e prazos quando forem mencionados.'),
        'audio_transcription_language' => env('OPENAI_AUDIO_TRANSCRIPTION_LANGUAGE', 'pt'),
    ],

];
