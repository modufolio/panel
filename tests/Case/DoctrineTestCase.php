<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Case;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\PanelResourceConfigurator;
use Modufolio\Panel\Resource\ResourceListing;
use Modufolio\Panel\Routing\PanelResourceRouteLoader;
use Modufolio\Panel\Tests\Fixture\CapturingRenderer;
use Modufolio\Panel\Tests\Fixture\StaticSharedProps;
use Modufolio\Panel\Tests\Routing\FixtureController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * A real EntityManager over the fixture entities, with the schema rebuilt
 * before every test.
 *
 * SQLite in memory when nothing is configured, so a fresh checkout runs the
 * suite with zero setup; the same `DB_*` variables appkit and json-api read
 * point it at a real engine instead (see docker-compose.yml). The classes
 * under test need only an EntityManager and, for a listing, a URL generator
 * and the two host contracts — so this deliberately boots no kernel.
 *
 * The listing helpers build a {@see ResourceListing} the way a host would:
 * routes come from the package's own route loader, so what a test sees
 * derived from "does this route exist" is derived from the real routes.
 */
abstract class DoctrineTestCase extends TestCase
{
    private static ?EntityManager $entityManager = null;

    /** The renderer behind the last {@see listing()} built. */
    protected ?CapturingRenderer $renderer = null;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $em = self::entityManager();
        $em->clear();

        self::recreateSchema($em);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    // ── Doctrine ─────────────────────────────────────────────────────────────

    protected static function em(): EntityManagerInterface
    {
        return self::entityManager();
    }

    /** Persist and flush, in one go. */
    protected function persist(object ...$entities): void
    {
        $em = self::em();

        foreach ($entities as $entity) {
            $em->persist($entity);
        }

        $em->flush();
    }

    /** Detach everything, so the next read comes from the database. */
    protected function clear(): void
    {
        self::em()->clear();
    }

    private static function entityManager(): EntityManager
    {
        if (self::$entityManager !== null && self::$entityManager->isOpen()) {
            return self::$entityManager;
        }

        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }

        $config = ORMSetup::createAttributeMetadataConfig(
            [dirname(__DIR__) . '/Fixture/Entity'],
            isDevMode: true,
        );

        // Native lazy objects need PHP 8.4. Below that Doctrine generates
        // proxy classes, and the new config factory no longer picks a
        // directory for them on its own — CI on 8.3 was the first to notice.
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir() . '/modufolio-panel-proxies');
            $config->setProxyNamespace('Modufolio\\Panel\\Tests\\Proxies');
            $config->setAutoGenerateProxyClasses(true);
        }

        $connection = DriverManager::getConnection(self::connectionParams(), $config);

        return self::$entityManager = new EntityManager($connection, $config);
    }

    /**
     * SQLite in memory by default; `DB_DRIVER` selects a real engine, with
     * the same variables the sibling packages' suites read.
     *
     * @return array<string, mixed>
     */
    private static function connectionParams(): array
    {
        $driver = getenv('DB_DRIVER') ?: 'pdo_sqlite';

        if ($driver === 'pdo_sqlite') {
            return ['driver' => 'pdo_sqlite', 'memory' => true];
        }

        $params = [
            'driver'   => $driver,
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'dbname'   => getenv('DB_NAME') ?: 'panel_test',
            'user'     => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
        ];

        $port = getenv('DB_PORT');

        if ($port !== false && $port !== '') {
            $params['port'] = (int) $port;
        }

        // ODBC driver 18 encrypts by default and rejects the self-signed
        // certificate a containerised SQL Server presents.
        if (str_contains($driver, 'sqlsrv')) {
            $params['driverOptions'] = ['TrustServerCertificate' => '1'];
        }

        return $params;
    }

    /**
     * Drop every table and rebuild the mapped schema.
     *
     * Referential checks are suspended per platform while dropping, because
     * a real engine keeps tables between runs and the foreign keys between
     * them would otherwise dictate an order SchemaTool does not know.
     */
    private static function recreateSchema(EntityManager $em): void
    {
        $connection = $em->getConnection();
        $platform   = $connection->getDatabasePlatform();
        $manager    = $connection->createSchemaManager();
        $metadata   = $em->getMetadataFactory()->getAllMetadata();

        if ($platform instanceof SQLitePlatform) {
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        }

        try {
            do {
                $remaining = $manager->listTableNames();
                $dropped   = 0;

                foreach ($remaining as $table) {
                    try {
                        $connection->executeStatement(
                            $platform instanceof PostgreSQLPlatform
                                ? sprintf('DROP TABLE IF EXISTS %s CASCADE', $table)
                                : sprintf('DROP TABLE IF EXISTS %s', $table),
                        );
                        ++$dropped;
                    } catch (DbalException) {
                        // Still referenced by a table later in the list; the
                        // next pass gets it once the referrer is gone.
                    }
                }
            } while ($remaining !== [] && $dropped > 0);
        } finally {
            if ($platform instanceof SQLitePlatform) {
                $connection->executeStatement('PRAGMA foreign_keys = ON');
            } elseif ($platform instanceof AbstractMySQLPlatform) {
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            }
        }

        (new SchemaTool($em))->createSchema($metadata);
    }

    // ── Requests and listings ────────────────────────────────────────────────

    /**
     * A request carrying only what a listing reads from one: its query params.
     *
     * @param array<string, mixed> $query
     */
    protected function request(array $query = []): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($query);

        return $request;
    }

    /**
     * The routes the package generates for these resources, with every
     * operation enabled, as the URL generator a listing consults.
     *
     * @param class-string<PanelResource> ...$resourceClasses
     */
    protected function urlGenerator(string ...$resourceClasses): UrlGeneratorInterface
    {
        $registrations = implode('', array_map(
            static fn (string $class): string => sprintf('$panel->resource(\\%s::class);', $class),
            $resourceClasses,
        ));

        return $this->urlGeneratorFromConfig(
            'function (PanelResourceConfigurator $panel): void { ' . $registrations . ' }',
        );
    }

    /**
     * Routes from a hand-written config body, for a test that narrows the
     * generated operations — `$panel->resource(X::class)->only(['index'])`.
     * The body is PHP source, exactly as `config/panel_resources.php` would
     * hold it.
     */
    protected function urlGeneratorFromConfig(string $configBody): UrlGeneratorInterface
    {
        return new UrlGenerator($this->routesFromConfig($configBody), new RequestContext());
    }

    /**
     * The routes the package generates for a hand-written config body — the
     * collection itself, for a test that inspects routes rather than
     * generating URLs from them.
     */
    protected function routesFromConfig(string $configBody): RouteCollection
    {
        $file = tempnam(sys_get_temp_dir(), 'panel_routes_') . '.php';
        $this->tempFiles[] = $file;

        file_put_contents(
            $file,
            "<?php\n\nuse " . PanelResourceConfigurator::class . ";\n\nreturn {$configBody};\n",
        );

        return (new PanelResourceRouteLoader(new FileLocator([dirname($file)]), FixtureController::class))
            ->load($file, 'panel_resource');
    }

    /**
     * A listing bound to a request, the way the host's factory would build it.
     *
     * Routes default to everything the resource's own class generates; pass
     * `$urls` to test what a narrower route set derives.
     *
     * @param array<string, mixed> $query
     */
    protected function listing(
        PanelResource $resource,
        array $query = [],
        ?object $user = null,
        ?UrlGeneratorInterface $urls = null,
    ): ResourceListing {
        $this->renderer = new CapturingRenderer($this->createStub(ResponseInterface::class));

        return new ResourceListing(
            $resource,
            $this->request($query),
            self::em(),
            $urls ?? $this->urlGenerator($resource::class),
            new StaticSharedProps(),
            $this->renderer,
            $user,
        );
    }

    /**
     * Render, and hand back the props the host's renderer received.
     *
     * @return array<string, mixed>
     */
    protected function renderProps(ResourceListing $listing): array
    {
        $listing->render();

        if ($this->renderer === null) {
            self::fail('renderProps() needs a listing built through listing().');
        }

        return $this->renderer->props;
    }
}
