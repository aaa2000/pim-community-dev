<?php

namespace Akeneo\Test\IntegrationTestsBundle\Loader;

use Akeneo\Test\Integration\Configuration;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @author    Alexandre Hocquard <alexandre.hocquard@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class FixturesLoaderFactory
{
    /** @var KernelInterface */
    protected $kernel;

    /** @var DatabaseSchemaHandler */
    protected $databaseSchemaHandler;

    /**
     * @param KernelInterface       $kernel
     * @param DatabaseSchemaHandler $databaseSchemaHandler
     */
    public function __construct(
        KernelInterface $kernel,
        DatabaseSchemaHandler $databaseSchemaHandler
    ) {
        $this->kernel = $kernel;
        $this->databaseSchemaHandler = $databaseSchemaHandler;
    }

    public function create(Configuration $configuration): FixturesLoader
    {
        return new FixturesLoader($this->kernel, $configuration, $this->databaseSchemaHandler);
    }
}
