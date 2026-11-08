<?php

namespace Omniplay;

use Omniplay\Exception\InvalidConfigException;
use Omniplay\Exception\NotSupportedException;

/**
 * The application's providers by name, each built once from its factory and
 * options:
 *
 *   $registry = new Registry([new DockerFactory($http)], [
 *       'local' => ['factory' => 'docker', 'options' => ['host' => 'unix:///var/run/docker.sock', 'services' => [...]]],
 *   ]);
 *
 *   $registry->hosting('local')->start($service, $secret);
 */
final class Registry
{
    /** @var array<string, ProviderFactoryInterface> */
    private array $factories = [];

    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                     $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): ProviderInterface
    {
        return $this->providers[$name] ??= $this->factory($name)->create($this->config[$name]['options'] ?? []);
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    public function hosting(string $name): HostingInterface
    {
        $provider = $this->get($name);

        return $provider instanceof HostingInterface && $provider->capabilities()->hosting ? $provider : throw NotSupportedException::capability($provider, $name, 'start servers');
    }

    /**
     * The first provider, in the configuration's order (or the order named),
     * that starts a server of this service in this region.
     */
    public function hostingFor(string $service, ?string $region = null, string ...$names): ?HostingInterface
    {
        foreach ($names ?: $this->names() as $name) {
            $provider = $this->get($name);
            if ($provider instanceof HostingInterface && $provider->capabilities()->starts($service, $region)) {
                return $provider;
            }
        }

        return null;
    }

    /** @return array<string, mixed> the options a provider is configured with */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /** @return list<string> the configured providers' names */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return list<string> the factories installed: what `factory:` may name */
    public function factories(): array
    {
        return array_keys($this->factories);
    }

    private function factory(string $name): ProviderFactoryInterface
    {
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" hosting provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" hosting provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }
}
