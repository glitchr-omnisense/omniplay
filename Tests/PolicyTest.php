<?php

namespace Omniplay\Tests;

use Omniplay\Capabilities;
use Omniplay\Config;
use Omniplay\Exception\OmniplayException;
use Omniplay\Exception\UsageLimitReachedException;
use Omniplay\Http\JsonClient;
use Omniplay\Model\Game;
use Omniplay\Model\GameRef;
use Omniplay\Model\Identifiers;
use Omniplay\Model\Query;
use Omniplay\Model\Service;
use Omniplay\Policy\GuardedCatalogue;
use Omniplay\Policy\GuardedHosting;
use Omniplay\Policy\GuardedSearch;
use Omniplay\ProviderFactory;
use Omniplay\ProviderInterface;
use Omniplay\Registry;
use Omniplay\SearchInterface;
use Omniplay\Testing\FixedHosting;
use Omniplay\Testing\FixedHostingFactory;
use Omniplay\Testing\FixedProvider;
use Omniplay\Testing\FixedProviderFactory;
use Omniplay\Testing\RecordedHttpClient;
use Omnisense\Contracts\Policy\Caching;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Every call through a contract is guarded by the provider's usage policy, as the site tightened it. */
final class PolicyTest extends TestCase
{
    public function testCallsThroughAContractCountUnderTheSitesLimit(): void
    {
        $portal = new Game('Portal 2', Identifiers::of(['steam' => 620]), 'fixed');
        $registry = new Registry([new FixedProviderFactory([$portal])], ['store' => ['factory' => 'fixed', 'policy' => ['rateLimits' => [['max' => 2, 'window' => 'minute', 'operations' => ['search', 'game']]]]]]);

        self::assertInstanceOf(GuardedSearch::class, $registry->search('store'));
        self::assertInstanceOf(FixedProvider::class, $registry->search('store')->inner());
        self::assertInstanceOf(FixedProvider::class, $registry->get('store'), 'get() gives the provider itself');
        self::assertSame('fixed', $registry->search('store')->getName());
        $registry->search('store')->search(new Query('Portal'));
        self::assertInstanceOf(GuardedCatalogue::class, $registry->catalogue('store'));
        self::assertSame($portal, $registry->game(GameRef::steam(620)));
        $registry->library('store')->library('nobody');
        try {
            $registry->catalogue('store')->game(GameRef::steam(620));
            self::fail('A third counted call went through.');
        } catch (OmniplayException $e) {
            self::assertInstanceOf(UsageLimitReachedException::class, $e);
            self::assertSame('fixed\'s usage policy allows 2 a minute for game, search: reached, try again in 60 s.', $e->getMessage());
        }
        self::assertSame(0, $registry->guard('store')->remaining('search'));
        self::assertNull($registry->guard('store')->remaining('library'), 'libraries are not counted');
    }

    public function testHostingIsGuardedWhenItsPolicyCountsItsCalls(): void
    {
        $registry = new Registry([new FixedHostingFactory()], [
            'local' => ['factory' => 'fixed-hosting', 'options' => ['services' => ['game']]],
            'counted' => ['factory' => 'fixed-hosting', 'options' => ['services' => ['game']], 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'minute', 'operations' => ['start']]]]],
        ]);

        self::assertInstanceOf(FixedHosting::class, $registry->hosting('local'), 'nothing to count: the provider itself');
        $hosting = $registry->hostingFor('game', null, 'counted');
        self::assertInstanceOf(GuardedHosting::class, $hosting);
        $hosting->start(new Service('game'), 'not-a-real-secret');
        self::assertSame([], $hosting->running('relay'), 'running is not counted');
        $this->expectException(UsageLimitReachedException::class);
        $hosting->start(new Service('game'), 'not-a-real-secret');
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        $http = RecordedHttpClient::routes(['~/search~' => [429, '{"error":"too many requests"}', ['retry-after' => ['120']]]]);
        $registry = new Registry([self::overHttp($http)], ['store' => ['factory' => 'http']]);

        try {
            $registry->search('store')->search(new Query('Portal'));
            self::fail('A 429 was read as games.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(120, $e->retryAfter);
            self::assertSame('http answered 429 Too Many Requests: try again in 120 s.', $e->getMessage());
        }
        try {
            $registry->search('store')->search(new Query('Half-Life'));
            self::fail('A call went out while the provider asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(119, $e->retryAfter);
        }
        self::assertCount(1, $http->requests, 'the wait held the next request back');
    }

    public function testAnEmptyBodyStillReadsAsEmptyData(): void
    {
        $json = new JsonClient(RecordedHttpClient::routes(['~/start~' => [204, '']]), 'Example');

        self::assertSame([], $json->request('POST', 'https://engine.example/containers/1/start')?->toArray());
    }

    public function testASiteMayTightenAPolicyNeverLoosenIt(): void
    {
        $declared = UsagePolicy::builder('http')->limit(RateLimit::perDay(1000))->commercial(Permission::Forbidden)->build();
        $tighter = new Registry([self::overHttp(RecordedHttpClient::routes([]), $declared)], ['store' => ['factory' => 'http', 'policy' => ['rateLimits' => [['max' => 100, 'window' => 'day']], 'rights' => ['caching' => ['permission' => 'forbidden']]]]]);

        self::assertSame(100, $tighter->guard('store')->remaining('search'));
        self::assertSame(Permission::Forbidden, $tighter->policy('store')->rights->caching->permission);

        $looser = new Registry([self::overHttp(RecordedHttpClient::routes([]), $declared)], ['store' => ['factory' => 'http', 'policy' => ['rateLimits' => [['max' => 5000, 'window' => 'day']]]]]);
        try {
            $looser->search('store');
            self::fail('A higher limit than the provider\'s was accepted.');
        } catch (InvalidPolicyException $e) {
            self::assertStringContainsString('5000 a day', $e->getMessage());
        }

        $commercial = new Registry([self::overHttp(RecordedHttpClient::routes([]), $declared)], ['store' => ['factory' => 'http', 'usage' => ['commercial' => true]]]);
        $this->expectException(UsageNotPermitted::class);
        $commercial->search('store');
    }

    public function testCapabilitiesWrittenTheOlderWayGiveAPolicy(): void
    {
        $capabilities = new Capabilities(search: true, attribution: 'Data from Example', terms: ['https://example.org/terms'], keyless: true);
        $policy = $capabilities->policy();

        self::assertSame(UsagePolicy::UNNAMED, $policy->provider);
        self::assertSame('Data from Example', $policy->rights->attribution->text);
        self::assertFalse($policy->requirements->key);
        self::assertNull($policy->rateLimits);
        self::assertSame('https://example.org/terms', $policy->provenance->terms);
        $registry = new Registry([new FixedProviderFactory()], ['store' => ['factory' => 'fixed']]);
        self::assertSame('fixed', $registry->policy('store')->provider, 'the registry names it after its provider');
        self::assertSame(['store' => $registry->policy('store')], $registry->policies());
    }

    public function testTheOlderFieldsStayAsGivenBesideAPolicy(): void
    {
        $policy = UsagePolicy::builder('x')->limit(RateLimit::perDay(10))->attribution('X data', 'https://x.example')
            ->caching(Caching::upTo(60))->commercial(Permission::Allowed)->provenance(terms: 'https://x.example/terms')->build();
        $capabilities = new Capabilities(hosting: true, policy: $policy);

        self::assertSame($policy, $capabilities->policy());
        self::assertNull($capabilities->attribution);
        self::assertSame([], $capabilities->terms);
        self::assertFalse($capabilities->keyless);
        self::assertArrayNotHasKey('policy', $capabilities->toArray(), 'toArray() keeps its shape');
    }

    /** A provider over HTTP, its JsonClient given the provider's Backoff: a search is one GET /search. */
    private static function overHttp(HttpClientInterface $http, ?UsagePolicy $policy = null): ProviderFactory
    {
        return new class($http, $policy) extends ProviderFactory {
            public function __construct(private readonly HttpClientInterface $http, private readonly ?UsagePolicy $policy)
            {
            }

            protected function populate(Config $c): void
            {
                $c->defaults(['omniplay.factory_name' => 'http', 'omniplay.required_options' => []]);
            }

            protected function build(Config $c): ProviderInterface
            {
                $json = new JsonClient($this->http, 'Example', [], $c->backoff());

                return new class($json, $this->policy) implements ProviderInterface, SearchInterface {
                    public function __construct(private readonly JsonClient $json, private readonly ?UsagePolicy $policy)
                    {
                    }

                    public function getName(): string
                    {
                        return 'http';
                    }

                    public function getTitle(): string
                    {
                        return 'Over HTTP';
                    }

                    public function capabilities(): Capabilities
                    {
                        return new Capabilities(search: true, policy: $this->policy);
                    }

                    public function search(Query $query): array
                    {
                        $this->json->get('https://api.example.org/search', ['q' => $query->text]);

                        return [];
                    }
                };
            }
        };
    }
}
