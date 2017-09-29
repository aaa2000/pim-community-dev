<?php

namespace Akeneo\Test\Integration;

use Akeneo\Test\IntegrationTestsBundle\Doctrine\Connection\ConnectionCloser;
use Akeneo\Test\IntegrationTestsBundle\Loader\FixturesLoader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @author    Marie Bochu <marie.bochu@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
abstract class TestCase extends KernelTestCase
{
    /** @var KernelInterface */
    protected $testKernel;

    /**
     * @return Configuration
     */
    abstract protected function getConfiguration();

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        static::bootKernel(['debug' => false]);

        $this->testKernel = new \AppKernelTest('test', false);
        $this->testKernel->boot();

        $this->getFixturesLoader()->load();
    }

    /**
     * @param string $service
     *
     * @return mixed
     */
    protected function get($service)
    {
        return static::$kernel->getContainer()->get($service);
    }

    /**
     * @param string $service
     *
     * @return mixed
     */
    protected function getParameter($service)
    {
        return static::$kernel->getContainer()->getParameter($service);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $connectionCloser = $this->testKernel->getContainer()->get('akeneo_integration_tests.doctrine.connection.connection_closer');
        $connectionCloser->closeConnections();

        parent::tearDown();
    }

    /**
     * Look in every fixture directory if a fixture $name exists.
     * And return the pathname of the fixture if it exists.
     *
     * @param string $name
     *
     * @throws \Exception if no fixture $name has been found
     *
     * @return string
     */
    protected function getFixturePath($name)
    {
        $configuration = $this->getConfiguration();
        foreach ($configuration->getFixtureDirectories() as $fixtureDirectory) {
            $path = $fixtureDirectory . $name;
            if (is_file($path) && false !== realpath($path)) {
                return realpath($path);
            }
        }

        throw new \Exception(sprintf('The fixture "%s" does not exist.', $name));
    }

    /**
     * @return FixturesLoader
     */
    private function getFixturesLoader(): FixturesLoader
    {
        $configuration = $this->getConfiguration();
        $factory = $this->testKernel->getContainer()->get('akeneo_integration_tests.loader.fixtures_loader_factory');

        return $factory->create($configuration);
    }
}
