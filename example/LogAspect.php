<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/17 17:31:05
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace example;

use LinFly\Aop\Library\AbstractAspect;
use LinFly\Aop\Library\AopTarget;
use support\Log;

class LogAspect extends AbstractAspect
{
    private float $time;

    // 记录方法执行时间
    public function before(AopTarget $aopTarget): void
    {
        $this->time = microtime(true);
    }

    public function after(AopTarget $aopTarget, mixed $result): void
    {
        $time = microtime(true) - $this->time;
        var_dump('after: ' . $aopTarget->getTargetClass() . '::' . $aopTarget->getMethod() . " -> return type:" . gettype($result) . " time: " . $time);
    }
}
