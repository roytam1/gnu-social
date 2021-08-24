<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace Plugin\ActivityStreamsTwo\Util\Type\Core;

/**
 * \Plugin\ActivityStreamsTwo\Util\Type\Core\Activity is an implementation of one of the
 * Activity Streams Core Types.
 *
 * Activity objects are specializations of the base Object type that
 * provide information about actions that have either already occurred,
 * are in the process of occurring, or may occur in the future.
 *
 * @see https://www.w3.org/TR/activitystreams-core/#activities
 */
class Activity extends AbstractActivity
{
    /**
     * @var string
     */
    protected string $type = 'Activity';

    /**
     * Describes the direct object of the activity.
     * For instance, in the activity "John added a movie to his
     * wishlist", the object of the activity is the movie added.
     *
     * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-object-term
     *
     * @var string
     *             | ObjectType
     *             | Link
     *             | null
     */
    protected string $object;
}
