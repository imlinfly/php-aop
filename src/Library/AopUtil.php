<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/14 14:00:12
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly\Aop\Library;

use InvalidArgumentException;
use LinFly\Aop\FacadeAop;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

class AopUtil
{
    public static function getTypeComments(?ReflectionType $type, ?string $className = null): string
    {
        if (!$type) {
            return 'mixed';
        }
        if ($type instanceof ReflectionNamedType) {
            $typeStr = $type->getName();
            if (!$type->isBuiltin()) {
                if ('self' === $typeStr) {
                    if (null !== $className) {
                        $typeStr = '\\' . $className;
                    }
                } else {
                    $typeStr = '\\' . $typeStr;
                }
            }
            if ($type->allowsNull() && 'mixed' !== $typeStr) {
                return $typeStr . '|null';
            } else {
                return $typeStr;
            }
        } elseif ($type instanceof ReflectionUnionType) {
            $result = [];
            foreach ($type->getTypes() as $subType) {
                $result[] = self::getTypeComments($subType, $className);
            }
            if ($type->allowsNull() && !\in_array('mixed', $result)) {
                $result[] = 'null';
            }

            return implode('|', $result);
        } elseif ($type instanceof ReflectionIntersectionType) {
            $result = [];
            foreach ($type->getTypes() as $subType) {
                $result[] = self::getTypeComments($subType, $className);
            }

            return implode('&', $result);
        } else {
            throw new InvalidArgumentException(sprintf('Unknown type %s', \get_class($type)));
        }
    }

    public static function getTypeCode(?ReflectionType $type, ?string $className = null): string
    {
        if (!$type) {
            return '';
        }
        if ($type instanceof ReflectionNamedType) {
            $typeStr = $type->getName();
            if (!$type->isBuiltin()) {
                if ('self' === $typeStr) {
                    if (null !== $className) {
                        $typeStr = '\\' . $className;
                    }
                } else {
                    $typeStr = '\\' . $typeStr;
                }
            }
            if ($type->allowsNull() && 'mixed' !== $typeStr) {
                return '?' . $typeStr;
            } else {
                return $typeStr;
            }
        } elseif ($type instanceof ReflectionUnionType) {
            $result = [];
            foreach ($type->getTypes() as $subType) {
                $result[] = self::getTypeCode($subType, $className);
            }
            if ($type->allowsNull() && !\in_array('mixed', $result)) {
                $result[] = 'null';
            }

            return implode('|', $result);
        } elseif ($type instanceof ReflectionIntersectionType) {
            $result = [];
            foreach ($type->getTypes() as $subType) {
                $result[] = self::getTypeCode($subType, $className);
            }

            return implode('&', $result);
        } else {
            throw new InvalidArgumentException(sprintf('Unknown type %s', \get_class($type)));
        }
    }

    public static function allowsType(ReflectionType $type, string $checkType, ?string $className = null): bool
    {
        if ('' === $checkType) {
            return false;
        }
        if ('null' === $checkType || '?' === $checkType[0]) {
            return $type->allowsNull();
        }
        $checkTypes = explode('|', $checkType);
        if ('?' === $checkTypes[0][0]) {
            $checkTypes[0][0] = substr($checkTypes[0][0], 1);
        }
        if ($type instanceof ReflectionNamedType) {
            $typeStr = $type->getName();
            if (!$type->isBuiltin()) {
                if ('self' === $typeStr) {
                    if (null !== $className) {
                        $typeStr = $className;
                    }
                }
            }

            return $typeStr === $checkType || \in_array($typeStr, $checkTypes) || is_subclass_of($checkType, $typeStr);
        }
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $subType) {
                $typeStr = ltrim(self::getTypeCode($subType, $className), '\\');
                if ($typeStr === $checkType || \in_array($typeStr, $checkTypes) || is_subclass_of($checkType, $typeStr)) {
                    return true;
                }
            }

            return false;
        } elseif ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $subType) {
                if (!self::allowsType($subType, $checkType, $className)) {
                    return false;
                }
            }

            return true;
        }
        throw new InvalidArgumentException(sprintf('Unknown type %s', \get_class($type)));
    }

    public static function getTpl(\ReflectionClass $reflector, string $newClassName): string
    {
        $class = $reflector->getName();
        $methodsTpl = static::getMethodsTpl($reflector);
        $constructMethod = $reflector->getConstructor();
        if (null !== $constructMethod) {
            $params = static::getMethodParamTpls($constructMethod);
            if (FacadeAop::isAspect($reflector->getName(), '__construct')) {
                $constructMethod = <<<TPL
                     public function __construct({$params['define']})
                     {
                        \$__args__ = \\func_get_args();
                        {$params['set_args']}
                        \$__result__ = \LinFly\Aop\FacadeAop::call(\$this, parent::class, '__construct', \$__args__, function({$params['define']}) {
                            \$__args__ = \\func_get_args();
                            {$params['set_args']}
                            return parent::__construct(...\$__args__);
                        });
                     }
                     
                TPL;
            } else if ($constructMethod->isProtected()) {
                $constructMethod = <<<TPL
                public function __construct({$params['define']})
                {
                    parent::__construct({$params['call']});
                }
                
                TPL;
            } else {
                $constructMethod = '';
            }
        } else {
            $constructMethod = '';
        }

        // 类模版定义
        return <<<TPL
        declare(strict_types=1);

        class {$newClassName} extends {$class}
        {
            {$constructMethod}
        {$methodsTpl}
        }
        TPL;
    }

    /**
     * 获取方法模版.
     */
    public static function getMethodsTpl(\ReflectionClass $reflection): string
    {
        $tpl = '';
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
            $methodName = $method->name;
            if ('__construct' === $methodName || $method->isStatic() || $method->isFinal() || !FacadeAop::isAspect($reflection->getName(), $methodName)) {
                continue;
            }
            $params = static::getMethodParamTpls($method);
            $methodReturnType = static::getMethodReturnType($method);
            $returnsReference = $method->returnsReference() ? ' & ' : '';
            $returnContent = $method->hasReturnType() && 'void' === static::getTypeCode($method->getReturnType(), $method->getDeclaringClass()->getName()) ? '' : 'return $__result__;';
            $tpl .= <<<TPL
                public function {$returnsReference}{$methodName}({$params['define']}){$methodReturnType}
                {
                    \$__args__ = \\func_get_args();
                    {$params['set_args']}
                    \$__result__ = \LinFly\Aop\FacadeAop::call(\$this, parent::class, '{$methodName}', \$__args__, function({$params['define']}) {
                        \$__args__ = \\func_get_args();
                        {$params['set_args']}
                        return parent::{$methodName}(...\$__args__);
                    });
                    {$params['set_args_back']}
                    {$returnContent}
                }
                
            TPL;
        }

        return $tpl;
    }

    /**
     * 获取方法参数模版们.
     */
    public static function getMethodParamTpls(\ReflectionMethod $method): array
    {
        $args = $define = $call = [];
        $setArgs = $setArgsBack = '';
        $result = [
            'args' => &$args,
            'define' => &$define,
            'call' => &$call,
            'set_args' => &$setArgs,
            'set_args_back' => &$setArgsBack,
        ];
        foreach ($method->getParameters() as $i => $param) {
            // 数组参数，支持可变传参
            if (!$param->isVariadic()) {
                $args[] = static::getMethodParamArgsTpl($param);
            }
            // 方法参数定义
            $define[] = static::getMethodParamDefineTpl($param);
            // 调用传参
            $call[] = static::getMethodParamCallTpl($param);
            // 引用传参
            if ($param->isPassedByReference()) {
                $paramName = $param->name;
                $setArgs .= '$__args__[' . $i . '] = &$' . $paramName . ';';
                $setArgsBack .= '$' . $paramName . ' = $__args__[' . $i . '];';
            }
        }
        foreach ($result as &$item) {
            if (\is_array($item)) {
                $item = implode(', ', $item);
            }
        }
        // 调用如果参数为空处理
        // @phpstan-ignore-next-line
        if ('' === $call) {
            $call = '...\func_get_args()';
        }

        return $result;
    }

    /**
     * 获取方法参数模版.
     */
    public static function getMethodParamArgsTpl(\ReflectionParameter $param): string
    {
        return ($param->isPassedByReference() ? ' & ' : '') . '$' . $param->name;
    }

    /**
     * 获取方法参数定义模版.
     */
    public static function getMethodParamDefineTpl(\ReflectionParameter $param): string
    {
        $result = '';
        // 类型
        $paramType = $param->getType();
        if ($paramType) {
            $paramType = static::getTypeCode($paramType, $param->getDeclaringClass()->getName());
        }
        $result .= null === $paramType ? '' : ($paramType . ' ');
        if ($param->isPassedByReference()) {
            // 引用传参
            $result .= ' & ';
        } elseif ($param->isVariadic()) {
            // 可变参数...
            $result .= '...';
        }
        // $参数名
        $result .= '$' . $param->name;
        // 默认值
        if ($param->isDefaultValueAvailable()) {
            $result .= ' = ' . var_export($param->getDefaultValue(), true);
        }

        return $result;
    }

    /**
     * 获取方法参数调用模版.
     */
    public static function getMethodParamCallTpl(\ReflectionParameter $param): string
    {
        return ($param->isVariadic() ? '...' : '') . '$' . $param->name;
    }

    /**
     * 获取方法返回值模版.
     */
    public static function getMethodReturnType(\ReflectionMethod $method): string
    {
        if (!$method->hasReturnType()) {
            return '';
        }
        $returnType = $method->getReturnType();

        return ': ' . static::getTypeCode($returnType, $method->getDeclaringClass()->getName());
    }
}

