<?php
/**
 * @link https://github.com/himiklab/yii2-common
 * @copyright Copyright (c) 2014-2018 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\yii2\common;

use Yii;
use yii\web\UrlRule;

class UniversalUrlRule extends UrlRule
{
    public $pattern = '';
    public $route = '';

    /**
     * @param \yii\web\UrlManager $manager
     * @param string $route
     * @param array $params
     * @return bool|mixed|string
     */
    public function createUrl($manager, $route, $params)
    {
        // delete default names of controllers and actions
        $route = \str_replace(['default/', '/index'], '', $route);

        // from parent::createUrl()
        if ($route !== '') {
            $route .= ($this->suffix === null ? $manager->suffix : $this->suffix);
        }
        if (!empty($params) && ($query = \http_build_query($params)) !== '') {
            $route .= '?' . $query;
        }

        return $route;
    }

    /**
     * @param \yii\web\UrlManager $manager
     * @param \yii\web\Request $request
     * @return array|bool
     * @throws \yii\base\InvalidConfigException
     */
    public function parseRequest($manager, $request)
    {
        $params = $this->defaults;
        $urlElements = \explode('/', \trim($request->getPathInfo(), '/'));

        if (isset($urlElements[0]) && Yii::$app->hasModule($urlElements[0])) {
            $currentModule = Yii::$app->getModule($urlElements[0]);

            // process submodules
            $parentModulesPath = '';
            $n = 1;
            while (
                isset($urlElements[$n]) &&
                !empty($currentModule->modules) &&
                $currentModule->hasModule($urlElements[$n])
            ) {
                $parentModulesPath .= $urlElements[$n - 1] . '/';
                $currentModule = $currentModule->getModule($urlElements[$n]);
                ++$n;
            }
            --$n;
            $urlElements = \array_slice($urlElements, $n);

            switch (\count($urlElements)) {
                case 1:
                    // '<module:\w+>' => '<module>'
                    return [$parentModulesPath . $urlElements[0], $params];

                case 2:
                    // '<module:\w+>/<controller:\w+>' => '<module>/<controller>'
                    $controllerClassName = $currentModule->controllerNamespace .
                        '\\' . \str_replace(' ', '', \ucwords(\str_replace('-', ' ', $urlElements[1]))) . 'Controller';
                    if (isset($currentModule->controllerMap[$urlElements[1]]) || \class_exists($controllerClassName)) {
                        return [$parentModulesPath . $urlElements[0] . '/' . $urlElements[1], $params];
                    }

                    // '<module:\w+>/<action:\w+>' => '<module>/default/<action>'
                    return [$parentModulesPath . $urlElements[0] . '/default/' . $urlElements[1], $params];

                case 3:
                    // '<module:\w+>/<controller:\w+>/<action:\w+>' => '<module>/<controller>/<action>'
                    $controllerClassName = $currentModule->controllerNamespace .
                        '\\' . \str_replace(' ', '', \ucwords(\str_replace('-', ' ', $urlElements[1]))) . 'Controller';
                    if (isset($currentModule->controllerMap[$urlElements[1]]) || \class_exists($controllerClassName)) {
                        return [
                            $parentModulesPath . $urlElements[0] . '/' . $urlElements[1] . '/' . $urlElements[2],
                            $params
                        ];
                    }
                    return false;
                    break;

                default:
                    return false;
            }
        }

        return false;
    }
}
