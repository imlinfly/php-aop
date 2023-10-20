<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/12 12:55:09
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly\Aop;

use InvalidArgumentException;

/**
 * LRU算法缓存
 */
class AopCache
{
    /**
     * The front of the array contains the LRU element
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Create a LRU Cache
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        /**
         * Cache capacity
         * @var int $capacity
         */
        protected int $capacity
    )
    {
        if ($capacity <= 0) {
            throw new InvalidArgumentException();
        }
    }

    /**
     * Get the value cached with this key
     *
     * @param int|string $key Cache key
     * @param mixed|null $default The value to be returned if key not found. (Optional)
     * @return mixed
     */
    public function get(int|string $key, mixed $default = null): mixed
    {
        if (isset($this->data[$key])) {
            $this->recordAccess($key);
            return $this->data[$key];
        } else {
            return $default;
        }
    }

    /**
     * Put something in the cache
     *
     * @param int|string $key Cache key
     * @param mixed $value The value to cache
     */
    public function set(int|string $key, mixed $value): void
    {
        if (isset($this->data[$key])) {
            $this->data[$key] = $value;
            $this->recordAccess($key);
        } else {
            $this->data[$key] = $value;
            if ($this->count() > $this->capacity) {
                // remove least recently used element (front of array)
                reset($this->data);
                unset($this->data[key($this->data)]);
            }
        }
    }

    /**
     * Get the number of elements in the cache
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Does the cache contain an element with this key
     *
     * @param int|string $key The key
     * @return bool
     */
    public function has(int|string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Remove the element with this key.
     *
     * @param int|string $key The key
     * @return mixed Value or null if not set
     */
    public function delete(int|string $key): mixed
    {
        if (isset($this->data[$key])) {
            $value = $this->data[$key];
            unset($this->data[$key]);
            return $value;
        } else {
            return null;
        }
    }

    /**
     * Clear the cache
     */
    public function empty(): void
    {
        $this->data = [];
    }

    /**
     * Moves the element from current position to end of array
     *
     * @param int|string $key The key
     */
    protected function recordAccess(int|string $key)
    {
        $value = $this->data[$key];
        unset($this->data[$key]);
        $this->data[$key] = $value;
    }

    /**
     * Get the cache data
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
