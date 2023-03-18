<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/12 12:55:09
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly\Aop;

/**
 * LRU算法缓存
 */
class AopCache
{
    /**
     * 双向链表MAP
     * @var array
     */
    protected array $map = [];

    /**
     * 缓存数据
     * @var array
     */
    protected array $data = [];

    /**
     * @param int $capacity 缓存容量
     */
    public function __construct(protected int $capacity)
    {
    }

    /**
     * 获取缓存
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->data[$key])) {
            $this->deleteKey($key);
            $this->createKey($key);
            return $this->data[$key] ?? $default;
        }

        return $default;
    }

    /**
     * 校验缓存是否存在
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->map[$key]);
    }

    /**
     * 获取缓存数量
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * 设置缓存
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set(string $key, mixed $value): bool
    {
        if (isset($this->data[$key])) {
            // 删除key
            $this->deleteKey($key);
        } else {
            if (count($this->data) >= $this->capacity) {
                $deleteKey = array_shift($this->map['keys']);
                $this->deleteKey($deleteKey);
                unset($this->data[$deleteKey]);
            }
        }
        $this->data[$key] = $value;
        $this->createKey($key);

        return $this->has($key);
    }

    /**
     * 删除缓存
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        if (isset($this->data[$key])) {
            $this->deleteKey($key);
            unset($this->data[$key]);
            return true;
        }
        return false;
    }

    /**
     * 清空缓存
     * @return void
     */
    public function empty(): void
    {
        $this->map = [];
        $this->data = [];
    }

    /**
     * 删除Key记录
     * @param string $key
     * @return void
     */
    protected function deleteKey(string $key): void
    {
        if (isset($this->map['index'][$key])) {
            $index = $this->map['index'][$key];
            unset($this->map['index'][$key], $this->map['keys'][$index]);
        }
    }

    /**
     * 创建key记录
     * @param string $key
     * @return void
     */
    protected function createKey(string $key): void
    {
        $this->map['keys'] ??= [];
        $this->map['index'][$key] = array_push($this->map['keys'], $key) - 1;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
