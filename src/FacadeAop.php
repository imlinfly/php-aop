<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/13 13:39:35
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly;

/**
 * Class FacadeAop
 * @package LinFly
 * @mixin AopManager
 * @see AopManager
 * @method static AopManager get(string $class, array $args = [], array $config = []) 获取代理类
 * @method static bool isAspect(string $class, string $method) 是否存在切入点
 */
final class FacadeAop
{
    /**
     * Container instance.
     * @var AopManager
     */
    private static AopManager $instance;

    /**
     * Get the container instance.
     * @return AopManager
     */
    public static function getInstance(): AopManager
    {
        if (!isset(self::$instance)) {
            self::$instance = FacadeContainer::make(AopManager::class, [[]]);
        }
        return self::$instance;
    }

    /**
     * Call the container instance method.
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return self::getInstance()->$name(...$arguments);
    }
}
