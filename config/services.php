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

    'evolution' => [
        'base_url' => env('EVOLUTION_API_URL', 'http://127.0.0.1:8085'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'instance' => env('EVOLUTION_INSTANCE'),
        'timeout' => (int) env('EVOLUTION_TIMEOUT', 30),
        'default_company_id' => env('EVOLUTION_DEFAULT_COMPANY_ID'),
        'webhook_token' => env('EVOLUTION_WEBHOOK_TOKEN'),
        'webhook_verify_api_key' => (bool) env('EVOLUTION_WEBHOOK_VERIFY_API_KEY', true),
        'webhook_forward_url' => env('EVOLUTION_WEBHOOK_FORWARD_URL'),
        'transcribe_audio' => (bool) env('EVOLUTION_TRANSCRIBE_AUDIO', true),
    ],

];
