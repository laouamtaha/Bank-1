<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ritechoice23\ChatEngine\Builders\ThreadBuilder thread()
 * @method static \Ritechoice23\ChatEngine\Builders\MessageBuilder message()
 * @method static \Ritechoice23\ChatEngine\Support\PresenceManager presence()
 * @method static \Ritechoice23\ChatEngine\Support\RetentionManager retention()
 * @method static \Ritechoice23\ChatEngine\Support\MessagePipeline pipeline()
 * @method static \Ritechoice23\ChatEngine\Support\PolicyChecker policy()
 * @method static \Ritechoice23\ChatEngine\Encryption\EncryptionManager encryption()
 * @method static \Illuminate\Database\Eloquent\Builder threadsFor(\Illuminate\Database\Eloquent\Model $actor)
 * @method static int unreadCountFor(\Illuminate\Database\Eloquent\Model $actor)
 * @method static \Ritechoice23\ChatEngine\Models\ThreadParticipant addParticipant(\Ritechoice23\ChatEngine\Models\Thread $thread, \Illuminate\Database\Eloquent\Model $actor, \Ritechoice23\ChatEngine\Enums\ParticipantRole $role = \Ritechoice23\ChatEngine\Enums\ParticipantRole::MEMBER)
 * @method static bool removeParticipant(\Ritechoice23\ChatEngine\Models\Thread $thread, \Illuminate\Database\Eloquent\Model $actor)
 * @method static void startTyping(\Ritechoice23\ChatEngine\Models\Thread $thread, \Illuminate\Database\Eloquent\Model $actor)
 * @method static void stopTyping(\Ritechoice23\ChatEngine\Models\Thread $thread, \Illuminate\Database\Eloquent\Model $actor)
 * @method static int markThreadAsRead(\Ritechoice23\ChatEngine\Models\Thread $thread, \Illuminate\Database\Eloquent\Model $actor)
 *
 * @see \Ritechoice23\ChatEngine\Chat
 */
class Chat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'chat';
    }
}
