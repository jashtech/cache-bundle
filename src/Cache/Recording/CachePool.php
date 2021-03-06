<?php

/*
 * This file is part of php-cache\cache-bundle package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\CacheBundle\Cache\Recording;

use Cache\Taggable\TaggablePoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * A pool that logs and collects all your cache calls.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CachePool implements CacheItemPoolInterface, TaggablePoolInterface
{
    /**
     * @type array
     */
    private $calls = [];

    /**
     * @type CacheItemPoolInterface
     */
    private $cachePool;

    /**
     * @type LoggerInterface
     */
    private $logger;

    /**
     * @type string
     */
    private $name;

    /**
     * @type string
     */
    private $level = 'info';

    /**
     * LoggingCachePool constructor.
     *
     * @param CacheItemPoolInterface $cachePool
     */
    public function __construct(CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    /**
     * Record a call.
     *
     * @param $call
     */
    protected function addCall($call)
    {
        $this->calls[] = $call;

        $this->writeLog($call);
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return object
     */
    protected function timeCall($name, array $arguments = [])
    {
        $start  = microtime(true);
        $result = call_user_func_array([$this->cachePool, $name], $arguments);
        $time   = microtime(true) - $start;

        $object = (object) compact('name', 'arguments', 'start', 'time', 'result');

        return $object;
    }

    public function getItem($key)
    {
        $call        = $this->timeCall(__FUNCTION__, [$key]);
        $result      = $call->result;
        $call->isHit = $result->isHit();

        // Display the result in a good way depending on the data type
        if ($call->isHit) {
            $call->result = $this->getValueRepresentation($result->get());
        } else {
            $call->result = null;
        }

        $this->addCall($call);

        return $result;
    }

    public function hasItem($key)
    {
        $call = $this->timeCall(__FUNCTION__, [$key]);
        $this->addCall($call);

        return $call->result;
    }

    public function deleteItem($key)
    {
        $call = $this->timeCall(__FUNCTION__, [$key]);
        $this->addCall($call);

        return $call->result;
    }

    public function save(CacheItemInterface $item)
    {
        $key   = $item->getKey();
        $value = $this->getValueRepresentation($item->get());

        $call            = $this->timeCall(__FUNCTION__, [$item]);
        $call->arguments = ['<CacheItem>', $key, $value];
        $this->addCall($call);

        return $call->result;
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        $key   = $item->getKey();
        $value = $this->getValueRepresentation($item->get());

        $call            = $this->timeCall(__FUNCTION__, [$item]);
        $call->arguments = ['<CacheItem>', $key, $value];
        $this->addCall($call);

        return $call->result;
    }

    public function getItems(array $keys = [])
    {
        $call         = $this->timeCall(__FUNCTION__, [$keys]);
        $result       = $call->result;
        $call->result = sprintf('<DATA:%s>', gettype($result));
        $this->addCall($call);

        return $result;
    }

    public function clear()
    {
        $call = $this->timeCall(__FUNCTION__, []);
        $this->addCall($call);

        return $call->result;
    }

    public function clearTags(array $tags)
    {
        $call = $this->timeCall(__FUNCTION__, [$tags]);
        $this->addCall($call);

        return $call->result;
    }

    public function deleteItems(array $keys)
    {
        $call = $this->timeCall(__FUNCTION__, [$keys]);
        $this->addCall($call);

        return $call->result;
    }

    public function commit()
    {
        $call = $this->timeCall(__FUNCTION__);
        $this->addCall($call);

        return $call->result;
    }

    /**
     * @return array
     */
    public function getCalls()
    {
        return $this->calls;
    }

    /**
     * Get a string to represent the value.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function getValueRepresentation($value)
    {
        $type = gettype($value);
        if (in_array($type, ['boolean', 'integer', 'double', 'string', 'NULL'])) {
            $rep = $value;
        } elseif ($type === 'array') {
            $rep = json_encode($value);
        } elseif ($type === 'object') {
            $rep = get_class($value);
        } else {
            $rep = sprintf('<DATA:%s>', $type);
        }

        return $rep;
    }

    protected function writeLog($call)
    {
        if (!$this->logger) {
            return;
        }

        $data = [
            'name'      => $this->name,
            'method'    => $call->name,
            'arguments' => json_encode($call->arguments),
            'hit'       => isset($call->isHit) ? $call->isHit ? 'True' : 'False' : 'Invalid',
            'time'      => round($call->time * 1000, 2),
            'result'    => $call->result,
        ];

        $this->logger->log(
            $this->level,
            sprintf('[Cache] Provider: %s. Method: %s(%s). Hit: %s. Time: %sms. Result: %s',
                $data['name'],
                $data['method'],
                $data['arguments'],
                $data['hit'],
                $data['time'],
                $data['result']
            ),
            $data
        );
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $level
     */
    public function setLevel($level)
    {
        $this->level = $level;

        return $this;
    }
}
