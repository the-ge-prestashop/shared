<?php declare(strict_types=1);

/**
 * Base trait containing helpers used by hook traits
 *
 * File name: ModuleSharedMethods.php
 * Created:   2024-09-02 07:24
 * @author    Gabriel Tenita <the.ge.1447624801@tenita.eu>
 * @link      https://github.com/the-ge/
 * @copyright Copyright (c) 2024-present Gabriel Tenita
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License version 2.0
 */

namespace TheGe\PrestaShop\Shared\Module\Helper;

use Controller;
use Db;
use PrestaShopModuleException;
use PrestaShopLogger;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

trait ModuleSharedMethods
{

    /**
     * Get the hook traits list by filtering the module traits array by the
     * existence of both the 'Hook' trait name prefix and a method with the trait name,
     * then convert the list to a hook names list.
     *
     * @return string[] The PrestaShop hooks list
     */
    private function hooks(): array
    {
        return array_map(
            fn($v) => lcfirst(substr($v, 4)),
            array_filter(
                array_map(
                    fn($v) => substr(strrchr($v, '\\') ?: $v, 1),
                    array_keys(class_uses($this, false))),
                fn($v) => str_starts_with($v, 'Hook') && method_exists($this, $v)
            )
        );
    }

    private function getControllerKey(?Controller $controller = null)
    {
        $controller ??= $this->context->controller;

        return strtolower(str_replace('Controller', '', $controller::class));
    }

    private function getService(string $name): object
    {
        return $this->get("{$this->name}.{$name}");
    }

    private function getParameter(string $name)
    {
        return SymfonyContainer::getInstance()->getParameter("{$this->name}.{$name}");
    }

    /**
     * Adds a CSS or JavaScript file
     * https://devdocs.prestashop-project.org/8/modules/creation/displaying-content-in-front-office/
     *
     * @param string $uri The CSS or Javascript URI relative to the asset root path,
     *                    which are 'views/css' and 'views/js' in the module folder; e.g. admin/form.js
     */
    private function addViewAsset(string $uri, array $options = []): void
    {
        ['extension' => $extension, 'filename' => $filename] = pathinfo($uri);
        $id  = "{$this->name}-$filename";
        // Asset URI e.g. '/shop/modules/mymodule/views/css/admin/somefile.css'
        $uri = fn(bool $is_modern): string => ($is_modern ? "modules/{$this->name}" : $this->getPathUri()) . "/views/{$extension}/{$uri}";
        $controller = $this->context->controller;
        /**
         * @param   array $method   [0 => legacy method name, 1 => modern method name]
         * @return  array           [0 => method name, 1 => false if legacy, true if modern] 
         */
        $findMethod = fn(array $method): array => [$method[(int) ($is_modern = method_exists($controller, $method[1]))], $is_modern];
        switch ($extension) {
            case 'css':
                [$method, $is_modern] = $findMethod(['addCSS', 'registerStylesheet']);
                $args = $is_modern ? [$id, $uri(true), $options] : [$uri(false), $options['media'] ?? 'all'];
                break;
            case 'js':
                [$method, $is_modern] = $findMethod(['addJS', 'registerJavascript']);
                $args = $is_modern ? [$id, $uri(true), $options] : [$uri(false)];
                break;
            default:
                throw new PrestaShopModuleException("Invalid extension for asset: {$uri}");
        };

        $controller->$method(...$args);
    }

    private function query(string $sql)
    {
        return ($this->database ?? Db::getInstance())->executeS($sql);
    }

    /**
     * Smarty template helper
     *
     * $this->moduleMainFile stores the main file __FILE__ constant
     * @see TheGePricesAltCurrency (thegepricesaltcurrency.php:52)
     *
     * @param      array<mixed>  $template_vars
     *
     * @return     string        The HTML code resulted by rendering the Smarty template
     */
    private function renderTemplate(string $template, array $template_vars): string // @phpstan-ignore method.unused
    {
        $this->context->smarty?->assign($template_vars);

        return $this->display($this->moduleMainFile, $template);
    }

    private function logException(PrestaShopModuleException $exception, string $context): string
    {
        PrestaShopLogger::addLog(
            "[{$exception->getFile()}:{$exception->getLine()}] {$exception->getMessage()}",
            3, // error
            $exception->getCode(),
            static::class,
        );
        $message = sprintf($this->trans('There was an error during %s.', [], "Modules.{$this->name}.Admin"), $context);
        $suffix  = $this->trans('Please, ask your developer to consult the logs or contact us through the Addons website.', [], "Modules.{$this->name}.Admin");

        return "{$message} {$suffix}";
    }
}
