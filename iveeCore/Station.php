<?php
/**
 * Station class file.
 *
 * PHP version 5.4
 *
 * @category IveeCore
 * @package  IveeCoreClasses
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCore/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCore/blob/master/iveeCore/Station.php
 */

namespace iveeCore;

/**
 * Class for representing stations.
 * Inheritance: Station -> SdeType -> CoreDataCommon
 *
 * @category IveeCore
 * @package  IveeCoreClasses
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCore/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCore/blob/master/iveeCore/Station.php
 */
class Station extends SdeType
{
    /**
     * @var string CLASSNICK holds the class short name which is used to lookup the configured FQDN classname in Config
     * (for dynamic subclassing)
     */
    const CLASSNICK = 'Station';

    /**
     * @var iveeCore\InstancePool $instancePool used to pool (cache) Station objects
     */
    protected static $instancePool;

    /**
     * @var array holding station operation details
     */
    protected static $operations;

    /**
     * @var array holding the serviceIds per station operation
     */
    protected static $operationServices;

    /**
     * @var array holding the service names
     */
    protected static $services;

    /**
     * @var int $solarSystemID the ID of SolarSystem this Station is in.
     */
    protected $solarSystemID;

    /**
     * @var int $operationID the ID of operation for this Station, this implies the available station services.
     */
    protected $operationID;

    /**
     * @var int $stationTypeID the ID of type of station.
     */
    protected $stationTypeID;

    /**
     * @var int $corporationID the ID of owning corporation.
     */
    protected $corporationID;

    /**
     * @var int $factionID the ID of the faction of the owning corporation.
     */
    protected $factionID;

    /**
     * @var bool $conquerable if the station is conquerable.
     */
    protected $conquerable;

    /**
     * @var float $reprocessingEfficiency the reprocessing efficiency of this Station as factor (<1.0)
     */
    protected $reprocessingEfficiency;

    /**
     * @var float $reprocessingStationsTake the base fraction the stations owner take as reprocessing tax
     */
    protected $reprocessingStationsTake;

    /**
     * @var float $tax the reprocessing efficiency of this Station as percentage / 100.
     */
    protected $tax;

    /**
     * @var array $assemblyLineTypeIDs the IDs of the available assemblyLineTypes, this determines possible industrial
     * activities (depending on the activity output item) as well as bonuses.
     */
    protected $assemblyLineTypeIDs = array();

    /**
     * Loads all Station names from DB to PHP.
     *
     * @return void
     */
    protected static function loadNames()
    {
        //lookup SDE class
        $sdeClass = Config::getIveeClassName('SDE');

        $res = $sdeClass::instance()->query(
            "SELECT stationID, stationName
            FROM staStations
            UNION
            SELECT facilityID as stationID, stationName
            FROM " . Config::getIveeDbName() . ".outposts;"
        );

        $namesToIds = array();
        while ($row = $res->fetch_assoc())
            $namesToIds[$row['stationName']] = (int) $row['stationID'];

        static::$instancePool->setNamesToKeys(static::getClassHierarchyKeyPrefix() . 'Names', $namesToIds);
    }

    /**
     * Returns a string that is used as cache key prefix specific to a hierarchy of SdeType classes. Example:
     * Type and Blueprint are in the same hierarchy, Type and SolarSystem are not.
     *
     * @return string
     */
    public static function getClassHierarchyKeyPrefix()
    {
        return __CLASS__ . '_';
    }

    /**
     * Returns an array with details about a specific station operation.
     * Note that "activityID" in the returned array is not comparable to industry activity IDs.
     *
     * @return array
     */
    protected static function getStaticOperationDetails($operationId)
    {
        if (!isset(static::$operations)) {
            $key = static::getClassHierarchyKeyPrefix() . 'operations';
            try {
                static::$operations = static::$instancePool->getItem($key)->data;
            } catch (Exceptions\KeyNotFoundInCacheException $e) {
                static::$operations = array();
                $sdeClass = Config::getIveeClassName('SDE');
                $sde = $sdeClass::instance();
                $res = $sde->query('SELECT operationID, activityID, operationName, description FROM staOperations;');
                if ($res->num_rows > 0) {
                    while ($row = $res->fetch_assoc()) {
                        static::$operations[(int) $row['operationID']] = array(
                            'activityID'    => (int) $row['activityID'],
                            'operationName' => $row['operationName'],
                            'description'   => $row['description']
                        );
                    }
                }
                $cacheableArrayClass = Config::getIveeClassName('CacheableArray');
                $cacheArray = new $cacheableArrayClass($key, time() + 24 * 3600);
                $cacheArray->data = static::$operations;
                static::$instancePool->setItem($cacheArray);
            }
        }
        return static::$operations[$operationId];
    }

    /**
     * Returns an array with the serviceIDs for a given station operation.
     *
     * @return array
     */
    protected static function getStaticOperationServices($operationId)
    {
        if (!isset(static::$operationServices)) {
            $key = static::getClassHierarchyKeyPrefix() . 'operationServices';
            try {
                static::$operationServices = static::$instancePool->getItem($key)->data;
            } catch (Exceptions\KeyNotFoundInCacheException $e) {
                static::$operationServices = array();
                $sdeClass = Config::getIveeClassName('SDE');
                $sde = $sdeClass::instance();
                $res = $sde->query('SELECT * FROM staOperationServices;');
                if ($res->num_rows > 0)
                    while ($row = $res->fetch_assoc())
                        static::$operationServices[(int) $row['operationID']][] = (int) $row['serviceID'];

                $cacheableArrayClass = Config::getIveeClassName('CacheableArray');
                $cacheArray = new $cacheableArrayClass($key, time() + 24 * 3600);
                $cacheArray->data = static::$operationServices;
                static::$instancePool->setItem($cacheArray);
            }
        }
        return static::$operationServices[$operationId];
    }

    /**
     * Returns a name for a specific station service.
     *
     * @return string
     */
    public static function getServiceName($serviceId)
    {
        if (!isset(static::$services)) {
            $key = static::getClassHierarchyKeyPrefix() . 'services';
            try {
                static::$services = static::$instancePool->getItem($key)->data;
            } catch (Exceptions\KeyNotFoundInCacheException $e) {
                static::$services = array();
                $sdeClass = Config::getIveeClassName('SDE');
                $sde = $sdeClass::instance();
                $res = $sde->query('SELECT * FROM staServices;');
                if ($res->num_rows > 0)
                    while ($row = $res->fetch_assoc())
                        static::$services[(int) $row['serviceID']] = $row['serviceName'];

                $cacheableArrayClass = Config::getIveeClassName('CacheableArray');
                $cacheArray = new $cacheableArrayClass($key, time() + 24 * 3600);
                $cacheArray->data = static::$services;
                static::$instancePool->setItem($cacheArray);
            }
        }
        return static::$services[$serviceId];
    }

    /**
     * Constructor. Use iveeCore\Station::getById() to instantiate Station objects instead.
     *
     * @param int $id of the Station
     *
     * @throws iveeCore\Exceptions\StationIdNotFoundException if stationID is not found
     */
    protected function __construct($id)
    {
        $this->id = (int) $id;
        $this->setExpiry();
        $sdeClass = Config::getIveeClassName('SDE');
        $sde = $sdeClass::instance();

        //regular Stations and Outposts need to be treated differently
        if ($this->id >= 61000000) {
            $row = $sde->query(
                "SELECT io.stationTypeID, ownerID as playerCorpID, solarSystemID, stationName as playerStationName,
                    operationID, conquerable, reprocessingEfficiency
                FROM " . Config::getIveeDbName() . ".outposts as io
                JOIN staStationTypes as sst ON sst.stationTypeID = io.stationTypeID
                WHERE facilityID = " . $this->id . ';'
            )->fetch_assoc();
        } else {
            $row = $sde->query(
                "SELECT sta.operationID, sta.stationTypeID, sta.corporationID, sta.solarSystemID, sta.stationName,
                    io.stationName as playerStationName, sta.reprocessingEfficiency, reprocessingStationsTake,
                    factionID, conquerable, io.ownerID as playerCorpID
                FROM staStations as sta
                JOIN crpNPCCorporations as corps ON corps.corporationID = sta.corporationID
                JOIN staStationTypes as sst ON sst.stationTypeID = sta.stationTypeID
                LEFT JOIN " . Config::getIveeDbName() . ".outposts as io ON io.facilityID = stationID
                WHERE stationID = " . $this->id . ';'
            )->fetch_assoc();
        }

        if (empty($row))
            static::throwException('StationIdNotFoundException', "Station ID=". $this->id . " not found");

        //set data to attributes
        $this->conquerable   = (bool) $row['conquerable'];
        $this->solarSystemID = (int) $row['solarSystemID'];
        $this->stationTypeID = (int) $row['stationTypeID'];
        $this->corporationID = ($this->conquerable AND isset($row['playerCorpID']))
            ? (int) $row['playerCorpID'] : (int) $row['corporationID'];
        $this->name          = ($this->conquerable AND isset($row['playerStationName']))
            ? $row['playerStationName'] : $row['stationName'];
        $this->operationID   = (int) $row['operationID'];
        $this->reprocessingEfficiency   = (float) $row['reprocessingEfficiency'];

        if ($this->id < 61000000) {
            $this->factionID     = (int) $row['factionID'];
            $this->reprocessingStationsTake = (float) $row['reprocessingStationsTake'];
        }

        if ($this->id >= 61000000) {
            $res = $sde->query(
                "SELECT ritc.assemblyLineTypeID, activityID
                FROM ramInstallationTypeContents as ritc
                JOIN ramAssemblyLineTypes as ralt ON ritc.assemblyLineTypeID = ralt.assemblyLineTypeID
                WHERE installationTypeID = " . $this->stationTypeID . ';'
            );
        } else {
            //get assembly lines in station
            $res = $sde->query(
                "SELECT rals.assemblyLineTypeID, ralt.activityID
                FROM ramAssemblyLineStations as rals
                JOIN ramAssemblyLineTypes as ralt ON ralt.assemblyLineTypeID = rals.assemblyLineTypeID
                WHERE stationID = " . $this->id . ';'
            );
        }

        while ($row = $res->fetch_assoc()) {
            $this->assemblyLineTypeIDs[$row['activityID']][] = $row['assemblyLineTypeID'];
        }
    }

    /**
     * Gets solarSystemID.
     *
     * @return int
     */
    public function getSolarSystemID()
    {
        return $this->solarSystemID;
    }

    /**
     * Gets SolarSystem.
     *
     * @return iveeCore\SolarSystem
     */
    public function getSolarSystem()
    {
        $systemClass = Config::getIveeClassName('SolarSystem');
        return $systemClass::getSolarSystem($this->getSolarSystemID());
    }

    /**
     * Gets operationID.
     *
     * @return int
     */
    public function getOperationID()
    {
        return $this->operationID;
    }

    /**
     * Gets the station operation details.
     *
     * @return array
     */
    public function getOperationDetails()
    {
        return static::getStaticOperationDetails($this->getOperationID());
    }

    /**
     * Gets the available service IDs of the station
     *
     * @return array
     */
    public function getServiceIds()
    {
        return static::getStaticOperationServices($this->getOperationID());
    }

    /**
     * Gets stationTypeID.
     *
     * @return int
     */
    public function getStationTypeId()
    {
        return $this->stationTypeID;
    }

    /**
     * Gets owning corporationID.
     *
     * @return int
     */
    public function getCorporationId()
    {
        return $this->corporationID;
    }

    /**
     * Gets the faction ID of the owning corporation.
     *
     * @return int
     */
    public function getFactionId()
    {
        if ($this->id >= 61000000)
            static::throwException('NoRelevantDataException', 'Data unavailable for player built outposts');
        return $this->corporationID;
    }

    /**
     * Gets whether the station is conquerable.
     *
     * @return bool
     */
    public function isConquerable()
    {
        return $this->conquerable;
    }

    /**
     * Gets station reprocessing efficiency.
     *
     * @return float
     */
    public function getReprocessingEfficiency()
    {
        return $this->reprocessingEfficiency;
    }

    /**
     * Gets station tax, defaults to 0.1 for NPC stations.
     *
     * @return float
     */
    public function getTax()
    {
        if (isset($this->tax))
            return $this->tax;
        return 0.1;
    }

    /**
     * Sets station tax
     *
     * @param float $tax the tax as factor
     *
     * @return void
     * @throws iveeCore\Exceptions\IveeCoreException if trying to get tax from player built outpost if none was set
     */
    public function setTax($tax)
    {
        $this->tax = (float) $tax;
    }

    /**
     * Gets a stations assemblyLineTypeIDs.
     *
     * @return array $activityID => array(id1, id2...)
     */
    public function getAssemblyLineTypeIDs()
    {
        return $this->assemblyLineTypeIDs;
    }

    /**
     * Gets a stations assemblyLineTypeIDs.
     *
     * @param int $activityID to get assemblyLineTypeIDs for
     *
     * @return array in the form array(id1, id2...)
     */
    public function getAssemblyLineTypeIDsForActivity($activityID)
    {
        if (isset($this->assemblyLineTypeIDs[$activityID]))
            return $this->assemblyLineTypeIDs[$activityID];
        else
            return array();
    }

    /**
     * Returns an IndustryModifier object for this station.
     *
     * @return iveeCore\IndustryModifier
     */
    public function getIndustryModifier()
    {
        $industryModifierClass = Config::getIveeClassName('IndustryModifier');
        return $industryModifierClass::getByStationID($this->id);
    }
}
