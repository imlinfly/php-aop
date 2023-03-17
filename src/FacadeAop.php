<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/13 13:39:35
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly\Aop;

use LinFly\FacadeContainer;

/**
 * Class FacadeAop
 * @package LinFly
 * @mixin AopManager
 * @see AopManager
 * @method static AopManager get(string $class, array $args = [], array $config = []) Get proxy class
 * @method static bool isAspect(string $class, string $method) Is there an aspect
 */
final class FacadeAop
{
    /**
     * Aop instance.
     * @var AopManager
     */
    private static AopManager $instance;

    /**
     * Get the aop instance.
     * @param array $config
     * @return AopManager
     */
    public static function getInstance(array $config = []): AopManager
    {
        if (!isset(self::$instance)) {
            self::$instance = FacadeContainer::make(AopManager::class, [$config]);
        }
        return self::$instance;
    }

    /**
     * Call the aop instance method.
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return self::getInstance()->$name(...$arguments);
    }
}
