<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/16 16:34:38
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly\Library;

use Closure;

class AopTarget
{
    /**
     * @param object $instance 代理类实例
     * @param string $targetClass 目标类
     * @param string $method 目标方法
     * @param array $arguments 目标方法参数
     * @param Closure $call 调用目标方法
     */
    public function __construct(
        private object  $instance,
        private string  $targetClass,
        private string  $method,
        private array   $arguments,
        private Closure $call
    )
    {
    }

    /**
     * @return object
     */
    public function getInstance(): object
    {
        return $this->instance;
    }

    /**
     * @return string
     */
    public function getTargetClass(): string
    {
        return $this->targetClass;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return Closure
     */
    public function getCall(): Closure
    {
        return $this->call;
    }

    /**
     * @param array $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }
}
