<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace Plugin\ActivityStreamsTwo\Util\Type\Extended\Object;

/**
 * \Plugin\ActivityStreamsTwo\Util\Type\Extended\Object\Video is an implementation of
 * one of the Activity Streams Extended Types.
 *
 * Represents a video document of any kind.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-video
 */
class Video extends Document
{
    /**
     * @var string
     */
    protected string $type = 'Video';
}
