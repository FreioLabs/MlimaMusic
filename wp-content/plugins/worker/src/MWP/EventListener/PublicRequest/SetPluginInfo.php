<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_PublicRequest_SetPluginInfo implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    private $brand;

    private $slug = 'worker/init.php';

    private $loaderName = '0-worker.php';

    function __construct(MWP_WordPress_Context $context, MWP_Worker_Brand $brand)
    {
        $this->context = $context;
        $this->brand   = $brand;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::PUBLIC_REQUEST => 'onPublicRequest',
        );
    }

    public function onPublicRequest()
    {
        $this->context->addFilter('all_plugins', array($this, 'pluginInfoFilter'));
        $this->context->addFilter('all_plugins', array($this, 'pluginListFilter'));
        // This is a horrible hack, but it will allow us to hide a MU plugin in rebranded installations.
        $this->context->addFilter('show_advanced_plugins', array($this, 'muPluginListFilter'), 10, 2);
        $this->context->addFilter('plugin_row_meta', array($this, 'hidePluginDetails'), 10, 2);
        $this->context->addFilter('site_transient_update_plugins', array($this, 'parseUpdatePlugins'));
    }

    public function parseUpdatePlugins($updates)
    {
        if (!$this->brand->isActive()) {
            return $updates;
        }

        if (isset($updates->response[$this->slug])) {
            unset($updates->response[$this->slug]);
        }

        return $updates;
    }

    /**
     * @wp_filter all_plugins
     */
    public function pluginInfoFilter($plugins)
    {
        if (!isset($plugins[$this->slug])) {
            return $plugins;
        }

        if (!$this->brand->isActive()) {
            return $plugins;
        }

        if (!$this->brand->getName() && !$this->brand->getDescription() && !$this->brand->getAuthor() && !$this->brand->getAuthorUrl()) {
            return $plugins;
        }

        $plugins[$this->slug]['Name']        = $this->brand->getName();
        $plugins[$this->slug]['Title']       = $this->brand->getName();
        $plugins[$this->slug]['Description'] = $this->brand->getDescription();
        $plugins[$this->slug]['AuthorURI']   = $this->brand->getAuthorUrl();
        $plugins[$this->slug]['Author']      = $this->brand->getAuthor();
        $plugins[$this->slug]['AuthorName']  = $this->brand->getAuthor();
        $plugins[$this->slug]['PluginURI']   = '';

        return $plugins;
    }

    /**
     * @wp_filter all_plugins
     */
    public function pluginListFilter($plugins)
    {
        if (!isset($plugins[$this->slug])) {
            return $plugins;
        }

        if (!$this->brand->isActive()) {
            return $plugins;
        }

        if ($this->brand->isHide()) {
            unset($plugins[$this->slug]);
        }

        return $plugins;
    }

    /**
     * @wp_filter show_advanced_plugins
     */
    public function muPluginListFilter($previousValue, $type)
    {
        if (!$this->brand->isActive()) {
            return $previousValue;
        }

        // Drop-in's are filtered after MU plugins.
        if ($type !== 'dropins') {
            return $previousValue;
        }

        if (!$this->context->hasContextValue('plugins')) {
            return $previousValue;
        }

        $plugins = &$this->context->getContextValue('plugins');

        if (!isset($plugins['mustuse'][$this->loaderName])) {
            return $previousValue;
        }

        if ($this->brand->isHide()) {
            unset($plugins['mustuse'][$this->loaderName]);
        } else {
            $plugins['mustuse'][$this->loaderName]['Name']        = $this->brand->getName();
            $plugins['mustuse'][$this->loaderName]['Title']       = $this->brand->getName();
            $plugins['mustuse'][$this->loaderName]['Description'] = $this->brand->getDescription();
            $plugins['mustuse'][$this->loaderName]['AuthorURI']   = $this->brand->getAuthorUrl();
            $plugins['mustuse'][$this->loaderName]['Author']      = $this->brand->getAuthor();
            $plugins['mustuse'][$this->loaderName]['AuthorName']  = $this->brand->getAuthor();
            $plugins['mustuse'][$this->loaderName]['PluginURI']   = '';
        }

        return $previousValue;
    }

    /**
     * @wp_filter
     */
    public function hidePluginDetails($meta, $slug)
    {
        if ($slug !== $this->slug) {
            return $meta;
        }

        if (!$this->brand->getName() && !$this->brand->getDescription() && !$this->brand->getAuthor() && !$this->brand->getAuthorUrl()) {
            return $meta;
        }

        foreach ($meta as $metaKey => $metaValue) {
            if (strpos($metaValue, sprintf('>%s<', $this->context->translate('View details'))) === false) {
                continue;
            }
            unset($meta[$metaKey]);
            break;
        }

        return $meta;
    }
}
