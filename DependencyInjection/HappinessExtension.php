<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class HappinessExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $viewsPath = \dirname(__DIR__) . '/Resources/views';
        if (is_dir($viewsPath)) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    $viewsPath => null,
                ],
            ]);
        }
    }

    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
