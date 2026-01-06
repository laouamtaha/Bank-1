<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Model Bindings
    |--------------------------------------------------------------------------
    |
    | Customize which models the package should use. You can extend the
    | default models and specify your custom classes here.
    |
    */
    'models' => [
        'thread' => \Ritechoice23\ChatEngine\Models\Thread::class,
        'thread_participant' => \Ritechoice23\ChatEngine\Models\ThreadParticipant::class,
        'message' => \Ritechoice23\ChatEngine\Models\Message::class,
        'message_version' => \Ritechoice23\ChatEngine\Models\MessageVersion::class,
        'message_delivery' => \Ritechoice23\ChatEngine\Models\MessageDelivery::class,
        'message_deletion' => \Ritechoice23\ChatEngine\Models\MessageDeletion::class,
        'message_attachment' => \Ritechoice23\ChatEngine\Models\MessageAttachment::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the database table names used by the package.
    |
    */
    'tables' => [
        'threads' => 'threads',
        'thread_participants' => 'thread_participants',
        'messages' => 'messages',
        'message_versions' => 'message_versions',
        'message_deliveries' => 'message_deliveries',
        'message_deletions' => 'message_deletions',
        'message_attachments' => 'message_attachments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Settings
    |--------------------------------------------------------------------------
    |
    | Configure message behavior including mutability and deletion.
    |
    */
    'messages' => [
        // true = edits create versions, false = direct payload updates
        'immutable' => true,

        // 'soft', 'hard', or 'hybrid'
        'deletion_mode' => 'soft',

        // Time limit for editing messages (minutes), null = no limit
        'edit_time_limit' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Thread Settings
    |--------------------------------------------------------------------------
    |
    | Configure thread creation and deduplication behavior.
    |
    */
    'threads' => [
        // Generate hash from participants for deduplication
        'hash_participants' => true,

        // Include participant roles in the hash
        'include_roles_in_hash' => true,

        // Allow duplicate threads with same participants
        'allow_duplicates' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Tracking
    |--------------------------------------------------------------------------
    |
    | Configure message delivery and read receipt tracking.
    |
    */
    'delivery' => [
        // Track when messages are read
        'track_reads' => true,

        // Track when messages are delivered
        'track_deliveries' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence Settings
    |--------------------------------------------------------------------------
    |
    | Configure presence features like typing indicators.
    |
    */
    'presence' => [
        // Enable presence features
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Settings
    |--------------------------------------------------------------------------
    |
    | Configure message encryption. The package provides an interface
    | but does not implement encryption by default.
    |
    */
    'encryption' => [
        // Enable message encryption
        'enabled' => false,

        // Where in the pipeline to apply encryption
        'pipeline_position' => 'after_policy',

        // Encryption driver class
        'driver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Settings
    |--------------------------------------------------------------------------
    |
    | Configure data retention and cleanup policies.
    |
    */
    'retention' => [
        // Days to keep soft-deleted messages before hard delete (null = never)
        'deleted_messages_days' => null,

        // Days to keep delivery records (null = forever)
        'delivery_records_days' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Pipelines
    |--------------------------------------------------------------------------
    |
    | Configure the pipeline of middleware that processes messages.
    | These pipes run in order and can transform/validate message data.
    |
    */
    'pipelines' => [
        'message' => [
            // \Ritechoice23\ChatEngine\Pipes\SanitizeContent::class,
            // \Ritechoice23\ChatEngine\Pipes\DetectMentions::class,
            // \Ritechoice23\ChatEngine\Pipes\DetectUrls::class,
            // \Ritechoice23\ChatEngine\Pipes\ValidateMediaUrls::class,
            // \Ritechoice23\ChatEngine\Pipes\FilterProfanity::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Profanity Filter
    |--------------------------------------------------------------------------
    |
    | Configure the profanity filter behavior.
    | Mode options: 'asterisk' (replace with *), 'remove' (remove word), 'reject' (reject message)
    |
    */
    'profanity' => [
        // List of words to filter
        'words' => [
            // Add your profanity words here
            // Example: 'badword1', 'badword2'
        ],

        // Character/string to replace profanity with (used in 'asterisk' mode)
        'replacement' => '*',

        // How to handle profanity: 'asterisk', 'remove', 'reject'
        'mode' => 'asterisk',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Configure file attachment storage and limits.
    |
    */
    'attachments' => [
        // Default filesystem disk for storing attachments
        'disk' => 'public',

        // Storage path prefix
        'path' => 'chat-attachments',

        // Default visibility for uploaded files
        'visibility' => 'public',

        // Maximum number of attachments per message
        'max_per_message' => 10,

        // Allowed attachment types
        'allowed_types' => ['image', 'video', 'audio', 'file'],

        // Delete physical files when attachment record is deleted
        'delete_files_on_delete' => false,

        // Maximum file size in bytes (null = no limit, defer to PHP/server config)
        'max_file_size' => null,

        // Allowed MIME types (null = all allowed)
        'allowed_mime_types' => null,
    ],
];
