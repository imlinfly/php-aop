<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/16 16:31:39
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly\Library;

use LinFly\FacadeContainer;

class AopChain
{
    /**
     * @param array $chainClasses 切入点类列表
     * @param AopTarget $aopTarget 切入点属性
     */
    public function __construct(
        private array     $chainClasses,
        private AopTarget $aopTarget
    )
    {
    }

    /**
     * 获取切入点属性
     * @return AopTarget
     */
    public function getTarget(): AopTarget
    {
        return $this->aopTarget;
    }

    /**
     * 执行切面链
     * @return mixed
     */
    public function invoke()
    {
        // 弹出一个切入点类
        $chain = array_shift($this->chainClasses);
        if ($chain) {
            $chain = clone FacadeContainer::get($chain);
            /** @var IAspect $chain */
            // 调用前置
            $chain->before($this->aopTarget);
            // 调用环绕
            $result = $chain->around($this);
            // 调用后置
            $chain->after($this->aopTarget, $result);
            // 递归调用
            return $result;
        } else {
            // 调用原始方法
            return $this->doCall();
        }
    }

    /**
     * 调用原始方法
     * @return mixed
     */
    private function doCall()
    {
        return ($this->aopTarget->getCall())(...$this->aopTarget->getArguments());
    }
}
