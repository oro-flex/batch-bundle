<?php

namespace Oro\Bundle\BatchBundle\Item;

use Oro\Bundle\BatchBundle\Exception\InvalidItemException;

/**
 * Interface for item transformation. Given an item as input, this interface provides
 * an extension point which allows for the application of business logic in an item
 * oriented processing scenario.  It should be noted that while it's possible to return
 * a different type than the one provided, it's not strictly necessary.  Furthermore,
 * returning null indicates that the item should not be continued to be processed.
 *
 * Inspired by Spring Batch org.springframework.batch.item.ItemProcessor
 *
 * @author    Benoit Jacquemont <benoit@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/MIT MIT
 */
interface ItemProcessorInterface
{
    /**
     * Process the provided item, returning a potentially modified or new item for continued
     * processing. It should not return null, instead it should throw an InvalidItemException
     * in case of warning;
     *
     * @param mixed $item item to be processed
     *
     * @return mixed Potentially modified or new item for continued processing
     *
     * @throws InvalidItemException if there is a problem processing the current record
     *                              (but the next one may still be valid)
     * @throws \Exception
     */
    public function process($item);
}
