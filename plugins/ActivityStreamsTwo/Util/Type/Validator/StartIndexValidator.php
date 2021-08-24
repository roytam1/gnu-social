<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace Plugin\ActivityStreamsTwo\Util\Type\Validator;

use Exception;
use Plugin\ActivityStreamsTwo\Util\Type\Core\OrderedCollectionPage;
use Plugin\ActivityStreamsTwo\Util\Type\Util;
use Plugin\ActivityStreamsTwo\Util\Type\ValidatorInterface;

/**
 * \Plugin\ActivityStreamsTwo\Util\Type\Validator\StartIndexValidator is a dedicated
 * validator for startIndex attribute.
 */
class StartIndexValidator implements ValidatorInterface
{
    /**
     * Validate startIndex value
     *
     * @param mixed $value
     * @param mixed $container An OrderedCollectionPage
     *
     * @throws Exception
     *
     * @return bool
     */
    public function validate(mixed $value, mixed $container): bool
    {
        // Container type is OrderedCollectionPage
        Util::subclassOf($container, OrderedCollectionPage::class, true);

        // Must be a non-negative integer
        return Util::validateNonNegativeInteger($value);
    }
}
