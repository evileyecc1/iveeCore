<?php
/**
 * CrestDataUpdater class file.
 *
 * PHP version 5.4
 *
 * @category IveeCore
 * @package  IveeCoreCrest
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCore/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCore/blob/master/iveeCore/CREST/CrestDataUpdater.php
 */

namespace iveeCore\CREST;

use iveeCore\Config;
use iveeCrest\Responses\Root;

/**
 * Abstract base class for the specific CREST endpoint updaters.
 *
 * @category IveeCore
 * @package  IveeCoreCrest
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCore/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCore/blob/master/iveeCore/CREST/CrestDataUpdater.php
 */
abstract class CrestDataUpdater
{
    /**
     * @var array $data holding the data received from CREST
     */
    protected $data;

    /**
     * @var int[] $updatedIds holding the updated IDs
     */
    protected $updatedIds = [];

    /**
     * Constructor.
     *
     * @param array $data the data received from CREST
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Saves the data to the database.
     *
     * @return void
     */
    public function insertIntoDB()
    {
        //lookup SDE class
        $sdeClass = Config::getIveeClassName('SDE');
        $sdeDb = $sdeClass::instance();
        $sql = '';
        $count = 0;

        foreach ($this->data as $item) {
            $sql .= $this->processDataItemToSQL($item);
            $count++;
            if ($count % 100 == 0 or $count == count($this->data)) {
                $sdeDb->multiQuery($sql . ' COMMIT;');
                $sql = '';
            }
        }

        $this->invalidateCaches();
        $this->updatedIds = [];
    }

    /**
     * Processes data objects to SQL.
     *
     * @param stdClass $item to be processed
     *
     * @return string the SQL queries
     */
    protected function processDataItemToSQL(\stdClass $item)
    {
        return '';
    }

    /**
     * Invalidate any cache entries that were update in the DB.
     *
     * @return void
     */
    protected function invalidateCaches()
    {
    }

    /**
     * Perform the complete update.
     *
     * @param \iveeCrest\Responses\Root $pubRoot to be used
     * @param bool $verbose whether info should be printed to console
     *
     * @return void
     */
    public static function doUpdate(Root $pubRoot, $verbose = true)
    {
        if ($verbose) {
            echo get_called_class() . ' getting data from CREST... ';
        }

        //fetch the data, check returned representation name
        $data = static::getData($pubRoot);
        if ($verbose) {
            echo "Done" . PHP_EOL . 'Saving data in DB... ';
        }

        //store in DB
        $cdu = new static($data);
        $cdu->insertIntoDB();
        if ($verbose) {
            echo 'Done' . PHP_EOL;
        }
    }

    /**
     * Fetch the data via CREST.
     *
     * @param \iveeCrest\Responses\Root $pubRoot to be used
     *
     * @return array
     */
    protected static function getData(Root $pubRoot)
    {
        return [];
    }
}
