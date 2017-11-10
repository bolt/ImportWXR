<?php

namespace Bolt\Extension\Bolt\Importwxr;

use Bolt\Extension\Bolt\Importwxr\Controller\ImportController;
use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;

/**
 * Importwxr extension class.
 *
 * @author Your Name <you@example.com>
 */
class ImportwxrExtension extends SimpleExtension
{

    protected function registerMenuEntries()
    {
        $menu = new MenuEntry('importwxr-menu', 'importWXR');
        $menu->setLabel('Import WXR')
            ->setIcon('fa:gear')
            ->setPermission('settings')
        ;
        return [
            $menu,
        ];
    }

    protected function registerBackendControllers()
    {
        return [
            '/extensions/importWXR' => new ImportController($this->getContainer(), $this->getConfig()),
        ];
    }

     /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [
            'templates' => [
                'position'  => 'prepend',
                'namespace' => 'importwxr'
            ]
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function getServiceProviders()
    {
        return [
            $this,
            new Provider\ImportProvider()
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'allowed_tags' => ['div', 'p', 'br', 's', 'u', 'strong', 'em', 'i', 'b',  'blockquote', 'a', 'img'],
            'allowed_attributes' => ['id', 'class', 'name', 'value', 'href', 'src', 'alt', 'title'],
            'max_images' => 10
        ];
    }

}
