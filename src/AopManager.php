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
     * 切入点索引
     * @var array
     */
    protected array $aspects = [];

    /**
     * 切入点缓存
     * @var array
     */
    protected array $aspectCaches = [];

    /**
     * 代理类内容缓存
     * @var array
     */
    protected array $proxyCaches = [];

    public function __construct(array $config)
    {
        $defaultConfig = [
            // 是否开启内存缓存
            'cache' => false,
            // 最大缓存数量
            'max_cache_number' => 100,
            // 切入点列表
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
                    'allows' => [],
                    // 除了这些方法，其他方法都会切入 (allows 和 ignores 不能同时存在，优先 allows)
                    'ignores' => [
                        \app\controller\IndexController::class => ['login'],
                    ],
                    // 切入的类
                    'aspect' => [\app\aop\aspect\IndexAspect::class],
                ],*/
            ],
        ];
        $this->config = array_merge($defaultConfig, $config);

        // 绑定容器实例化回调
        $this->bindContainerHandler();
        // 生成切入点索引
        $this->generateAspectsIndex();
    }

    /**
     * 生成aspects索引
     * @return void
     */
    public function generateAspectsIndex()
    {
        $aspects = $this->config['aspects'];
        foreach ($aspects as $aspect) {
            $classes = $aspect['classes'];
            $allows = $aspect['allows'] ?? [];
            $ignores = $aspect['ignores'] ?? [];
            $aspect = $aspect['aspect'];
            foreach ($classes as $class) {
                $class = ltrim($class, '\\');
                // 通配符切入点
                if (str_contains($class, '*')) {
                    $class = str_replace(['*', '\\'], ['.*', '\\\\'], $class);
                    $this->aspects['matches'][$class][] = [
                        'allows' => $allows,
                        'ignores' => $ignores,
                        'aspect' => $aspect,
                    ];
                } else // 精准切入点
                {
                    $this->aspects['static'][$class][] = [
                        'allows' => $allows,
                        'ignores' => $ignores,
                        'aspect' => $aspect,
                    ];
                }
            }
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

        if (isset($this->aspectCaches[$cacheKey])) {
            return $this->aspectCaches[$cacheKey];
        }

        $aopChain = [];

        $class = ltrim($class, '\\');

        $this->findAspects($class, function ($aspect) use ($method, $class, &$aopChain) {
            foreach ($aspect as $item) {
                if ($this->checkMethodRule($class, $method, $item)) {
                    array_push($aopChain, ...$item['aspect']);
                }
            }
        });

        if (empty($aopChain)) {
            return [];
        }

        // 切入点去重
        $aopChain = array_unique($aopChain);

        // 回收缓存
        if (count($this->aspectCaches) > 10000) {
            unset($this->aspectCaches[key($this->aspectCaches)]);
        }

        return $this->aspectCaches[$cacheKey] = $aopChain;
    }

    /**
     * 查找切入点
     * @param string $class
     * @param Closure $handler
     * @return void
     */
    public function findAspects(string $class, Closure $handler): void
    {
        // 匹配动态切入点
        foreach ($this->aspects['matches'] ?? [] as $aspectClass => $aspects) {
            if (preg_match('/^' . $aspectClass . '$/', $class)) {
                if (false === $handler($aspects)) {
                    return;
                }
            }
        }

        // 获取精准切入点
        if (isset($this->aspects['static'][$class])) {
            $handler($this->aspects['static'][$class]);
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
            $exist = false;
            $this->findAspects($class, function () use (&$exist) {
                $exist = true;
                return false;
            });
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
        // 允许的方法，优先高于忽略的方法
        if (!empty($rule['allows'] ?? [])) {
            if (isset($rule['allows'][$class])) {
                $methods = (array)($rule['allows'][$class] ?? []);
                if ($methods === ['*'] || in_array($method, $methods)) {
                    return true;
                }
            }
            return false;
        }

        // 忽略的方法 (allows 和 ignores 不能同时存在，优先 allows)
        if (!empty($rule['ignores'] ?? [])) {
            if (isset($rule['ignores'][$class])) {
                $methods = (array)($rule['ignores'][$class] ?? []);
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

        if ($isCache && isset($this->proxyCaches[$className])) {
            [$newClassName, $code] = $this->proxyCaches[$className];
        } else {
            $newClassName = $reflector->getShortName() . 'AopProxy_' . $this->nameIndex++;
            // 生成代理类代码
            $code = AopUtil::getTpl($reflector, $newClassName);
            // 缓存代理类代码
            if ($isCache) {
                $this->proxyCaches[$className] = [$newClassName, $code];
                // 回收缓存
                $maxCache = intval($this->config['max_cache_number'] ?? 100);
                if (count($this->proxyCaches) > $maxCache) {
                    unset($this->proxyCaches[key($this->proxyCaches)]);
                }
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
}
