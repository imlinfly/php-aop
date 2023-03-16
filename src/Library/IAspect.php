<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/16 16:30:12
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly\Library;


interface IAspect
{
    /**
     * 执行前置
     * @param AopTarget $aopTarget
     * @return mixed
     */
    public function before(AopTarget $aopTarget);

    /**
     * 执行环绕
     * @param AopChain $aopChain
     * @return mixed
     */
    public function around(AopChain $aopChain): mixed;

    /**
     * 执行后置
     * @param AopTarget $aopTarget
     * @param mixed $result
     * @return mixed
     */
    public function after(AopTarget $aopTarget, mixed $result);
}
