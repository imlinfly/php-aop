<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/17 17:32:25
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly\Library;

abstract class AbstractAspect implements IAspect
{

    public function before(AopTarget $aopTarget)
    {
    }

    public function around(AopChain $aopChain): mixed
    {
        return $aopChain->invoke();
    }

    public function after(AopTarget $aopTarget, mixed $result)
    {
    }
}
