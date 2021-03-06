<?php
/**
 * ContentItemsTypeIdIndexer class file.
 *
 * PHP version 5.4
 *
 * @category IveeCrest
 * @package  IveeCrestResponses
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCore/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCore/blob/master/iveeCrest/Responses/ContentItemsTypeIdIndexer.php
 */

namespace iveeCrest\Responses;

/**
 * ContentItemsTypeIdIndexer is a trait for indexing response items.
 *
 * @category IveeCrest
 * @package  IveeCrestResponses
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCore/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCore/blob/master/iveeCrest/Responses/ContentItemsTypeIdIndexer.php
 */
trait ContentItemsTypeIdIndexer
{
    /**
     * Sets content to object, re-indexing types array by ID parsed from provided href.
     *
     * @param \stdClass $content to be set
     *
     * @return void
     */
    protected function setContent(\stdClass $content)
    {
        $indexedItems = [];
        foreach ($content->items as $item) {
            $indexedItems[(int) $item->type->id] = $item;
        }
        $this->content = $content;
        $this->content->items = $indexedItems;
    }
}
