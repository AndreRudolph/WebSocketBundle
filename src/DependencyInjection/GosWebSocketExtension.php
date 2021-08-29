<?php declare(strict_types=1);

namespace Gos\Bundle\WebSocketBundle\DependencyInjection;

use Gos\Bundle\WebSocketBundle\Authentication\Storage\Driver\StorageDriverInterface;
use Gos\Bundle\WebSocketBundle\Client\Auth\WebsocketAuthenticationProviderInterface;
use Gos\Bundle\WebSocketBundle\Client\ClientManipulatorInterface;
use Gos\Bundle\WebSocketBundle\Client\ClientStorageInterface;
use Gos\Bundle\WebSocketBundle\Client\Driver\DriverInterface;
use Gos\Bundle\WebSocketBundle\DependencyInjection\Factory\Authentication\AuthenticationProviderFactoryInterface;
use Gos\Bundle\WebSocketBundle\Periodic\PeriodicInterface;
use Gos\Bundle\WebSocketBundle\RPC\RpcInterface;
use Gos\Bundle\WebSocketBundle\Server\Type\ServerInterface;
use Gos\Bundle\WebSocketBundle\Topic\TopicInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Johann Saunier <johann_27@hotmail.fr>
 */
final class GosWebSocketExtension extends Extension implements PrependExtensionInterface
{
    private const DEPRECATED_ALIASES = [
        ClientManipulatorInterface::class => '3.11',
        ClientStorageInterface::class => '3.11',
        DriverInterface::class => '3.11',
        WebsocketAuthenticationProviderInterface::class => '3.11',
        'gos_web_socket.session_handler' => '3.11',
    ];

    private const DEPRECATED_SERVICES = [
        'gos_web_socket.client.authentication.websocket_provider' => '3.11',
        'gos_web_socket.client.driver.in_memory' => '3.11',
        'gos_web_socket.client.driver.symfony_cache' => '3.11',
        'gos_web_socket.client.manipulator' => '3.11',
        'gos_web_socket.client.storage' => '3.11',
    ];

    /**
     * @var AuthenticationProviderFactoryInterface[]
     */
    private array $authenticationProviderFactories = [];

    public function addAuthenticationProviderFactory(AuthenticationProviderFactoryInterface $factory): void
    {
        $this->authenticationProviderFactories[] = $factory;
    }

    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($this->authenticationProviderFactories);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));

        $loader->load('services.php');

        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->registerForAutoconfiguration(PeriodicInterface::class)->addTag('gos_web_socket.periodic');
        $container->registerForAutoconfiguration(RpcInterface::class)->addTag('gos_web_socket.rpc');
        $container->registerForAutoconfiguration(ServerInterface::class)->addTag('gos_web_socket.server');
        $container->registerForAutoconfiguration(TopicInterface::class)->addTag('gos_web_socket.topic');

        $this->registerAuthenticationConfiguration($config, $container);
        $this->registerClientConfiguration($config, $container);
        $this->registerServerConfiguration($config, $container);
        $this->registerOriginsConfiguration($config, $container);
        $this->registerBlockedIpAddressesConfiguration($config, $container);
        $this->registerPingConfiguration($config, $container);

        $this->maybeEnableAuthenticatorApi($config, $container);

        $this->markAliasesDeprecated($container);
        $this->markServicesDeprecated($container);
    }

    private function markAliasesDeprecated(ContainerBuilder $container): void
    {
        $usesSymfony51Api = method_exists(Alias::class, 'getDeprecation');

        foreach (self::DEPRECATED_ALIASES as $aliasId => $deprecatedSince) {
            if (!$container->hasAlias($aliasId)) {
                continue;
            }

            $alias = $container->getAlias($aliasId);

            if ($usesSymfony51Api) {
                $alias->setDeprecated(
                    'gos/web-socket-bundle',
                    $deprecatedSince,
                    'The "%alias_id%" service is deprecated and will be removed in GosWebSocketBundle 4.0.'
                );
            } else {
                $alias->setDeprecated(
                    true,
                    'The "%alias_id%" service is deprecated and will be removed in GosWebSocketBundle 4.0.'
                );
            }
        }
    }

    private function markServicesDeprecated(ContainerBuilder $container): void
    {
        $usesSymfony51Api = method_exists(Definition::class, 'getDeprecation');

        foreach (self::DEPRECATED_SERVICES as $serviceId => $deprecatedSince) {
            if (!$container->hasDefinition($serviceId)) {
                continue;
            }

            $service = $container->getDefinition($serviceId);

            if ($usesSymfony51Api) {
                $service->setDeprecated(
                    'gos/web-socket-bundle',
                    $deprecatedSince,
                    'The "%service_id%" service is deprecated and will be removed in GosWebSocketBundle 4.0.'
                );
            } else {
                $service->setDeprecated(
                    true,
                    'The "%service_id%" service is deprecated and will be removed in GosWebSocketBundle 4.0.'
                );
            }
        }
    }

    private function maybeEnableAuthenticatorApi(array $config, ContainerBuilder $container): void
    {
        if (!$config['authentication']['enable_authenticator']) {
            return;
        }

        $container->getDefinition('gos_web_socket.event_subscriber.client')
            ->replaceArgument(0, new Reference('gos_web_socket.authentication.token_storage'))
            ->replaceArgument(1, new Reference('gos_web_socket.authentication.authenticator'));

        $container->getDefinition('gos_web_socket.server.application.wamp')
            ->replaceArgument(3, new Reference('gos_web_socket.authentication.token_storage'));

        $container->removeDefinition('gos_web_socket.client.authentication.websocket_provider');
        $container->removeDefinition('gos_web_socket.client.driver.doctrine_cache');
        $container->removeDefinition('gos_web_socket.client.driver.in_memory');
        $container->removeDefinition('gos_web_socket.client.driver.symfony_cache');
        $container->removeDefinition('gos_web_socket.client.manipulator');
        $container->removeDefinition('gos_web_socket.client.storage');
    }

    private function registerAuthenticationConfiguration(array $config, ContainerBuilder $container): void
    {
        $authenticators = [];

        if (isset($config['authentication']['providers'])) {
            foreach ($this->authenticationProviderFactories as $factory) {
                $key = str_replace('-', '_', $factory->getKey());

                if (!isset($config['authentication']['providers'][$key])) {
                    continue;
                }

                $authenticators[] = new Reference($factory->createAuthenticationProvider($container, $config['authentication']['providers'][$key]));
            }
        }

        $container->getDefinition('gos_web_socket.authentication.authenticator')
            ->replaceArgument(0, new IteratorArgument($authenticators));

        $storageId = null;

        switch ($config['authentication']['storage']['type']) {
            case Configuration::AUTHENTICATION_STORAGE_TYPE_IN_MEMORY:
                $storageId = 'gos_web_socket.authentication.storage.driver.in_memory';

                break;

            case Configuration::AUTHENTICATION_STORAGE_TYPE_PSR_CACHE:
                $storageId = 'gos_web_socket.authentication.storage.driver.psr_cache';

                $container->getDefinition($storageId)
                    ->replaceArgument(0, new Reference($config['authentication']['storage']['pool']));

                break;

            case Configuration::AUTHENTICATION_STORAGE_TYPE_SERVICE:
                $storageId = $config['authentication']['storage']['id'];

                break;
        }

        $container->setAlias('gos_web_socket.authentication.storage.driver', $storageId);
        $container->setAlias(StorageDriverInterface::class, $storageId);
    }

    private function registerClientConfiguration(array $config, ContainerBuilder $container): void
    {
        // @deprecated to be removed in 4.0, authentication API has been replaced
        $container->setParameter('gos_web_socket.client.storage.ttl', $config['client']['storage']['ttl']);

        // @deprecated to be removed in 4.0, authentication API has been replaced
        $container->setParameter('gos_web_socket.firewall', (array) $config['client']['firewall']);

        // @deprecated to be removed in 4.0, session handler config is moved
        if (isset($config['client']['session_handler'])) {
            $sessionHandlerId = $config['client']['session_handler'];

            $container->getDefinition('gos_web_socket.server.builder')
                ->addMethodCall('setSessionHandler', [new Reference($sessionHandlerId)]);

            $container->setAlias('gos_web_socket.session_handler', $sessionHandlerId);
        }

        // @deprecated to be removed in 4.0, authentication API has been replaced
        if (isset($config['client']['storage']['driver'])) {
            $driverId = $config['client']['storage']['driver'];
            $storageDriver = $driverId;

            if (isset($config['client']['storage']['decorator'])) {
                $decoratorId = $config['client']['storage']['decorator'];
                $container->getDefinition($decoratorId)
                    ->setArgument(0, new Reference($decoratorId));

                $storageDriver = $decoratorId;
            }

            // Alias the DriverInterface in use for autowiring
            $container->setAlias(DriverInterface::class, new Alias($storageDriver));

            $container->getDefinition('gos_web_socket.client.storage')
                ->replaceArgument(0, new Reference($storageDriver));
        }
    }

    private function registerServerConfiguration(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('gos_web_socket.server.port', $config['server']['port']);
        $container->setParameter('gos_web_socket.server.host', $config['server']['host']);
        $container->setParameter('gos_web_socket.server.origin_check', $config['server']['origin_check']);
        $container->setParameter('gos_web_socket.server.ip_address_check', $config['server']['ip_address_check']);
        $container->setParameter('gos_web_socket.server.keepalive_ping', $config['server']['keepalive_ping']);
        $container->setParameter('gos_web_socket.server.keepalive_interval', $config['server']['keepalive_interval']);

        $routerConfig = [];

        foreach (($config['server']['router']['resources'] ?? []) as $resource) {
            if (\is_array($resource)) {
                $routerConfig[] = $resource;
            } else {
                $routerConfig[] = [
                    'resource' => $resource,
                    'type' => null,
                ];
            }
        }

        $container->setParameter('gos_web_socket.router_resources', $routerConfig);
    }

    private function registerOriginsConfiguration(array $config, ContainerBuilder $container): void
    {
        $container->getDefinition('gos_web_socket.registry.origins')
            ->replaceArgument(0, $config['origins']);
    }

    private function registerBlockedIpAddressesConfiguration(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('gos_web_socket.blocked_ip_addresses', $config['blocked_ip_addresses']);
    }

    /**
     * @throws InvalidArgumentException if an unsupported ping service type is given
     */
    private function registerPingConfiguration(array $config, ContainerBuilder $container): void
    {
        if (!isset($config['ping'])) {
            return;
        }

        foreach ((array) $config['ping']['services'] as $pingService) {
            $serviceId = $pingService['name'];

            switch ($pingService['type']) {
                case Configuration::PING_SERVICE_TYPE_DOCTRINE:
                    $definition = new ChildDefinition('gos_web_socket.periodic_ping.doctrine');
                    $definition->replaceArgument(0, new Reference($serviceId));
                    $definition->replaceArgument(1, $pingService['interval']);
                    $definition->addTag('gos_web_socket.periodic');

                    $container->setDefinition('gos_web_socket.periodic_ping.doctrine.'.$serviceId, $definition);

                    break;

                case Configuration::PING_SERVICE_TYPE_PDO:
                    $definition = new ChildDefinition('gos_web_socket.periodic_ping.pdo');
                    $definition->replaceArgument(0, new Reference($serviceId));
                    $definition->replaceArgument(1, $pingService['interval']);
                    $definition->addTag('gos_web_socket.periodic');

                    $container->setDefinition('gos_web_socket.periodic_ping.pdo.'.$serviceId, $definition);

                    break;

                default:
                    throw new InvalidArgumentException(sprintf('Unsupported ping service type "%s"', $pingService['type']));
            }
        }
    }

    /**
     * @throws LogicException if required dependencies are missing
     */
    public function prepend(ContainerBuilder $container): void
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles['GosPubSubRouterBundle'])) {
            throw new LogicException('The GosWebSocketBundle requires the GosPubSubRouterBundle, please run "composer require gos/pubsub-router-bundle".');
        }

        // Prepend the websocket router now so the pubsub bundle creates the router service, we will inject the resources into the service with a compiler pass
        $container->prependExtensionConfig(
            'gos_pubsub_router',
            [
                'routers' => [
                    'websocket' => [],
                ],
            ]
        );
    }
}
