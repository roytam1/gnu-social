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
use Plugin\ActivityStreamsTwo\Util\Type\Core\Collection;
use Plugin\ActivityStreamsTwo\Util\Type\Core\Link;
use Plugin\ActivityStreamsTwo\Util\Type\Util;
use Plugin\ActivityStreamsTwo\Util\Type\ValidatorTools;

/**
 * \Plugin\ActivityStreamsTwo\Util\Type\Validator\ItemsValidator is a dedicated
 * validator for items attribute.
 */
class ItemsValidator extends ValidatorTools
{
    /**
     * Validate items value
     *
     * @param mixed $value
     * @param mixed $container A Collection type
     *
     * @throws Exception
     *
     * @return bool
     */
    public function validate(mixed $value, mixed $container): bool
    {
        // Validate that container is a Collection
        Util::subclassOf(
            $container,
            [Collection::class],
            true
        );

        // URL type
        if (is_string($value)) {
            return Util::validateUrl($value);
        }

        if (is_array($value)) {
            // Empty array
            if (!count($value)) {
                return true;
            }
            $value = Util::arrayToType($value);
        }

        // Link type
        if (is_object($value)) {
            return Util::subclassOf($value, Link::class, true);
        }

        // A Collection
        if (!is_array($value)) {
            return false;
        }

        if (!count($value)) {
            return false;
        }

        return $this->validateObjectCollection(
            $value,
            $this->getCollectionItemsValidator()
        );
    }
}
