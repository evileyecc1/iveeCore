<?php
/**
 * RedisWrapper class file.
 *
 * PHP version 5.4
 *
 * @category IveeCore
 * @package  IveeCoreClasses
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCrest/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCrest/blob/master/iveeCore/RedisWrapper.php
 */

namespace iveeCore;

/**
 * RedisWrapper provides caching functionality for iveeCore based on Redis with PhpRedis (php5-redis).
 *
 * Instantiating iveeCore objects that need to pull data from the SDE DB is a relatively expensive process. This is the
 * case for all Type objects and it's descendants, AssemblyLine, SolarSystem, Station and market data. Since these are
 * immutable except when affected by updates from CREST, using an object cache is the natural and easy way to greatly
 * improve performance of iveeCore. The CREST updaters automatically clear the cache for the objects that have had their
 * DB entry updated, or the caching expiry time is set to reasonably short values.
 *
 * @category IveeCore
 * @package  IveeCoreClasses
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCrest/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCrest/blob/master/iveeCore/RedisWrapper.php
 */
class RedisWrapper implements ICache
{
    /**
     * @var \iveeCore\RedisWrapper $instance holds the singleton RedisWrapper object.
     */
    protected static $instance;

    /**
     * @var \Redis $redis holds the Redis object
     */
    protected $redis;

    /**
     * @var int $hits stores the number of cache hits.
     */
    protected $hits = 0;

    /**
     * Returns RedisWrapper instance.
     *
     * @return \iveeCore\RedisWrapper
     */
    public static function instance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Constructor.
     */
    protected function __construct()
    {
        $this->redis = new \Redis;
        $this->redis->connect(Config::getCacheHost(), Config::getCachePort());
        $this->redis->setOption(\Redis::OPT_PREFIX, Config::getCachePrefix());
    }

    /**
     * Stores item in Redis.
     *
     * @param \iveeCore\ICacheable $item to be stored
     *
     * @return boolean true on success
     */
    public function setItem(ICacheable $item)
    {
        $ttl = $item->getCacheExpiry() - time();

        if ($ttl < 1) {
            return false;
        }

        return $this->redis->setex(
            $item->getKey(),
            $ttl,
            gzencode(serialize($item))
        );
    }

    /**
     * Gets item from Redis.
     *
     * @param string $key under which the item is stored
     *
     * @return \iveeCore\ICacheable
     * @throws \iveeCore\Exceptions\KeyNotFoundInCacheException if key is not found
     */
    public function getItem($key)
    {
        $item = $this->redis->get($key);
        if (!$item) {
            $exceptionClass = Config::getIveeClassName('KeyNotFoundInCacheException');
            throw new $exceptionClass("Key not found in Redis.");
        }
        //count hit
        $this->hits++;
        return unserialize(gzdecode($item));
    }

    /**
     * Removes item from Redis.
     *
     * @param string $key of object to be removed
     *
     * @return bool true on success
     */
    public function deleteItem($key)
    {
        return $this->redis->delete($key);
    }

    /**
     * Removes multiple items from Redis.
     *
     * @param string[] $keys of items to be removed
     *
     * @return bool true on success, also if memcached has been disabled
     */
    public function deleteMulti(array $keys)
    {
        return $this->redis->delete($keys);
    }

    /**
     * Clears all stored items in current Redis DB.
     *
     * @return boolean true on success
     */
    public function flushCache()
    {
        return $this->redis->flushDB();
    }

    /**
     * Gets the number of hits the cache wrapper registered.
     *
     * @return int the number of hits
     */
    public function getHits()
    {
        return $this->hits;
    }
}
