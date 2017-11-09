<?php

namespace Bolt\Extension\Bolt\Importwxr\Provider;

use Bolt\Extension\Bolt\Importwxr\Provider\ImportService;
use Silex\Application;
use Silex\ServiceProviderInterface;


/**
 * ImportWXR Service Provider
 *
 * @author Néstor de Dios Fernández <nestor@twokings.nl>
 */
class NotarisProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $app['importwxr.import.service'] = $app->share(
            function (Application $app) {
                return new ImportService(
                    $app['db'],
                    $app['storage'],
                    $app['logger.system'],
                    $app['filesystem']
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
    }

}