<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/03/13 13:39:35
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace LinFly\Aop;

use Closure;
use LinFly\Aop\Library\AopChain;
use LinFly\Aop\Library\AopTarget;
use LinFly\Aop\Library\AopUtil;
use LinFly\Aop\Library\IAspect;
use LinFly\FacadeContainer;
use ReflectionClass;
use SplDoublyLinkedList;
use Workerman\Timer;

class AopManager
{
    /**
     * Aop配置
     * @var array
     */
    protected array $config = [];

    /**
     * 代理类名 index
     * @var int
     */
    protected int $nameIndex = 0;

    /**
     * 切入点列表
     * @var array
     */
    protected array $aspects = [];

    /**
     * 切入点缓存
     * @var AopCache
     */
    protected AopCache $aspectCache;

    /**
     * 代理类内容缓存
     * @var AopCache
     */
    protected AopCache $proxyCaches;

    public function __construct(array $config)
    {
        $defaultConfig = [
            // 是否开启内存缓存
            'cache' => false,
            // 最大缓存数量
            'max_cache_number' => 100,
            // 切入点类列表
            'aspects' => [
                /*[
                    // 需要切入的类
                    'classes' => ['app\controller\*'],
                    // 切入的类
                    'aspect' => [\app\aop\aspect\LogAspect::class],
                ],
                [
                    // 需要切入的类
                    'classes' => ['app\controller\*'],
                    // 只有在这些方法中才会切入
                    'only' => [],
                    // 除了这些方法，其他方法都会切入 (only 和 except 不能同时存在，优先 only)
                    'except' => [
                        \app\controller\IndexController::class => ['login'],
                    ],
                    // 切入的类
                    'aspect' => [\app\aop\aspect\IndexAspect::class],
                ],*/
            ],
        ];
        $this->config = array_merge($defaultConfig, $config);
        $this->config['aspects'] = (array)$this->config['aspects'];
        $this->aspects = &$this->config['aspects'];

        $this->aspectCache = new AopCache(10000);
        $this->proxyCaches = new AopCache($this->config['max_cache_number']);

        // 绑定容器实例化回调
        $this->bindContainerHandler();
        // 排序
        $this->sort();
    }

    /**
     * 对切入点列表排序
     * @return void
     */
    public function sort()
    {
        $aspects = &$this->aspects;
        // 对 $aspects 进行降序排序
        usort($aspects, function ($a, $b) {
            return ($a['sort'] ?? 0) < ($b['sort'] ?? 0) ? 1 : -1;
        });

        foreach ($aspects as &$aspect) {
            if (isset($aspect['__format'])) {
                continue;
            }

            $aspect['classes'] = array_map(function ($class) {
                if (str_contains($class, '*')) {
                    $class = str_replace(['*', '\\'], ['.*', '\\\\'], $class);
                }
                return $class;
            }, $aspect['classes']);

            $aspect['__format'] = true;
        }
    }

    /**
     * 调用代理类的方法
     * @param object $instance 代理类实例
     * @param string $targetClass 目标类
     * @param string $method 方法名
     * @param array $arguments 参数
     * @param Closure $call 调用目标类的方法
     * @return mixed
     */
    public function call(object $instance, string $targetClass, string $method, array $arguments, Closure $call)
    {
        // 获取切入点列表
        $aopChainClasses = $this->getAopChain($targetClass, $method);
        // 包装切入点属性
        $aopTarget = new AopTarget($instance, $targetClass, $method, $arguments, $call);
        // 生成切入点链
        $chain = new AopChain($aopChainClasses, $aopTarget);
        // 执行切入点
        return $chain->invoke();
    }

    /**
     * 获取切入点列表
     * @param string $class 目标类名
     * @param string $method 方法名
     * @return array
     */
    protected function getAopChain(string $class, string $method): array
    {
        $cacheKey = $class . '.' . $method;

        if ($cache = $this->aspectCache->get($cacheKey)) {
            return $cache;
        }

        $aopChain = [];

        $class = ltrim($class, '\\');

        $this->findAspects($class, function ($aspect) use ($method, $class, &$aopChain) {
            if ($this->checkMethodRule($class, $method, $aspect)) {
                array_push($aopChain, ...$aspect['aspect']);
            }
        });

        if (empty($aopChain)) {
            return [];
        }

        // 切入点去重
        $aopChain = array_unique($aopChain);
        // 缓存切入点
        $this->aspectCache->set($cacheKey, $aopChain);

        return $aopChain;
    }

    /**
     * 查找切入点
     * @param string $className
     * @param Closure $handler
     * @return void
     */
    public function findAspects(string $className, Closure $handler): void
    {
        $className = ltrim($className, '\\');
        foreach ($this->aspects as $aspect) {
            foreach ($aspect['classes'] as $class) {
                $result = null;
                if (str_contains($class, '*')) {
                    if (preg_match('/^' . $class . '$/', $className)) {
                        $result = $handler($aspect);
                    }
                } else {
                    if ($class === $className) {
                        $result = $handler($aspect);
                    }
                }
                if (false === $result) {
                    return;
                }
            }
        }
    }

    /**
     * 是否存在切入点
     * @param string $class
     * @param string|null $method
     * @return bool
     */
    public function isAspect(string $class, string $method = null): bool
    {
        if (is_null($method)) {
            if (null !== $this->aspectCache->get($class)) {
                return true;
            }
            $exist = false;
            $this->findAspects($class, function () use (&$exist) {
                $exist = true;
                return false;
            });
            $this->aspectCache->set($class, $exist);
            return $exist;
        }
        return !empty($this->getAopChain($class, $method));
    }

    /**
     * 校验方法规则
     * @param string $class
     * @param string $method
     * @param array $rule
     * @return bool
     */
    protected function checkMethodRule(string $class, string $method, array $rule)
    {
        // 允许的方法，优先高于排除的方法
        if (!empty($rule['only'] ?? [])) {
            if (isset($rule['only'][$class])) {
                $methods = (array)($rule['only'][$class] ?? []);
                if ($methods === ['*'] || in_array($method, $methods)) {
                    return true;
                }
            }
            return false;
        }

        // 忽略的方法 (only 和 except 不能同时存在，优先 only)
        if (!empty($rule['except'] ?? [])) {
            if (isset($rule['except'][$class])) {
                $methods = (array)($rule['except'][$class] ?? []);
                if ($methods === ['*'] || in_array($method, $methods)) {
                    return false;
                }
            }
            return true;
        }

        return true;
    }

    /**
     * 容器实例化处理
     * @param ReflectionClass $reflector
     * @return object
     * @throws \ReflectionException
     */
    protected function createInstance(ReflectionClass $reflector): object
    {
        if (
            // 忽略切入点接口类
            $reflector->implementsInterface(IAspect::class) ||
            // 忽略未设置切入点的类
            !$this->isAspect($reflector->getName())
        ) {
            return $reflector->newInstanceWithoutConstructor();
        }

        $isCache = $this->config['cache'] ?? false;
        $className = $reflector->getName();

        if ($isCache && $this->proxyCaches->has($className)) {
            [$newClassName, $code] = $this->proxyCaches->get($className);
        } else {
            $newClassName = $reflector->getShortName() . 'AopProxy_' . $this->nameIndex++;
            // 生成代理类代码
            $code = AopUtil::getTpl($reflector, $newClassName);
            // 缓存代理类代码
            if ($isCache) {
                $this->proxyCaches->set($className, [$newClassName, $code]);
            }
        }

        eval($code);

        $newReflector = new ReflectionClass($newClassName);
        return $newReflector->newInstanceWithoutConstructor();
    }

    /**
     * 绑定容器实例化回调
     * @return void
     */
    protected function bindContainerHandler(): void
    {
        FacadeContainer::setNewClassInstanceHandler(fn($reflector) => $this->createInstance($reflector));
    }

    /**
     * 设置切入点
     * @param array $aspects
     * @param bool $isEmpty 是否清空原有切入点
     * @return static
     */
    public function setAspects(array $aspects, bool $isEmpty = false): static
    {
        if ($isEmpty) {
            $this->aspects = [];
        }

        array_push($this->aspects, ...$aspects);

        return $this;
    }
}
