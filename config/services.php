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

    'ai_cli' => [
        'codex_bin' => env('AI_CODEX_BIN', 'codex'),
        'agy_bin' => env('AI_AGY_BIN', 'agy'),
        'codex_model' => env('AI_CODEX_MODEL'),
        'agy_model' => env('AI_AGY_MODEL'),
        'timeout' => (int) env('AI_CLI_TIMEOUT', 300),
        'workdir' => env('AI_CLI_WORKDIR') ?: base_path(),
        'use_sudo' => filter_var(env('AI_CLI_USE_SUDO', false), FILTER_VALIDATE_BOOLEAN),
        'sudo_bin' => env('AI_CLI_SUDO_BIN', 'sudo'),
        'sudo_user' => env('AI_CLI_SUDO_USER', 'root'),
        'agy_skip_permissions' => filter_var(env('AI_AGY_SKIP_PERMISSIONS', false), FILTER_VALIDATE_BOOLEAN),
        'describe_images' => filter_var(env('AI_DESCRIBE_IMAGES', true), FILTER_VALIDATE_BOOLEAN),
        'image_max_mb' => (int) env('AI_IMAGE_MAX_MB', 10),
    ],

    'ai_whatsapp_suggestions' => filter_var(env('AI_WHATSAPP_SUGGESTIONS', true), FILTER_VALIDATE_BOOLEAN),

    'assistant' => [
        'db_connection' => env('ASSISTANT_DB_CONNECTION', 'sqlite_readonly'),
        'max_iterations' => (int) env('ASSISTANT_MAX_ITERATIONS', 5),
        'max_rows' => (int) env('ASSISTANT_MAX_ROWS', 200),
        'history_max_messages' => (int) env('ASSISTANT_HISTORY_MAX_MESSAGES', 30),

        'attachments' => [
            'enabled' => filter_var(env('ASSISTANT_ATTACHMENTS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'max_files' => (int) env('ASSISTANT_ATTACHMENTS_MAX_FILES', 3),
            'max_file_mb' => (int) env('ASSISTANT_ATTACHMENTS_MAX_FILE_MB', 25),
            'max_pdf_pages' => (int) env('ASSISTANT_ATTACHMENTS_MAX_PDF_PAGES', 100),
            'allowed_mimes' => array_filter(array_map('trim', explode(',', (string) env(
                'ASSISTANT_ATTACHMENTS_ALLOWED_MIMES',
                'image/jpeg,image/png,image/webp,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain'
            )))),
            'max_context_chars' => (int) env('ASSISTANT_ATTACHMENTS_MAX_CONTEXT_CHARS', 48000),
            'timeout' => (int) env('ASSISTANT_ATTACHMENTS_TIMEOUT', 900),
            'max_reopens_per_response' => (int) env('ASSISTANT_ATTACHMENTS_MAX_REOPENS', 2),
        ],

        'whatsapp' => [
            'enabled' => filter_var(env('ASSISTANT_WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'instance' => env('ASSISTANT_WHATSAPP_INSTANCE', 'Assistente_Pejota'),
            'allowed_numbers' => array_filter(array_map('trim', explode(',', (string) env('ASSISTANT_WHATSAPP_ALLOWED_NUMBERS', '')))),
            'end_command' => env('ASSISTANT_WHATSAPP_END_COMMAND', '#fim'),
            'help_command' => env('ASSISTANT_WHATSAPP_HELP_COMMAND', '#ajuda'),
            'ack_enabled' => filter_var(env('ASSISTANT_WHATSAPP_ACK', true), FILTER_VALIDATE_BOOLEAN),
            'debounce_seconds' => (int) env('ASSISTANT_WHATSAPP_DEBOUNCE_SECONDS', 15),
        ],
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
