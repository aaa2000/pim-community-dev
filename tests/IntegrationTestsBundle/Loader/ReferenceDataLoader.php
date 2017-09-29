<?php

namespace Akeneo\Test\IntegrationTestsBundle\Loader;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Integration test loader for reference data
 *
 * @author    Alexandre Hocquard <alexandre.hocquard@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ReferenceDataLoader
{
    /** @var ObjectManager */
    protected $entityManager;

    /** @var string */
    protected $projectDir;

    /**
     * @param EntityManagerInterface $entityManager
     * @param string                 $projectDir
     */
    public function __construct(EntityManagerInterface $entityManager, string $projectDir)
    {
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
    }

    /**
     * Load data fixtures with the passed EntityManager
     */
    public function load()
    {
        $query = $this->entityManager->createQuery('SELECT COUNT(f) FROM \Acme\Bundle\AppBundle\Entity\Fabric f');
        if (0 === (int) $query->getSingleScalarResult()) {
            $stmt = $this->entityManager->getConnection()->prepare($this->getFabricsSql());
            $stmt->execute();
        }

        $query = $this->entityManager->createQuery('SELECT COUNT(c) FROM \Acme\Bundle\AppBundle\Entity\Color c');
        if (0 === (int) $query->getSingleScalarResult()) {
            $stmt = $this->entityManager->getConnection()->prepare($this->getColorSql());
            $stmt->execute();
        }
    }

    private function getFabricsSql()
    {
        $path = $this->projectDir . '/src/Acme/Bundle/AppBundle/Resources/fixtures/fabrics.sql';

        return file_get_contents(realpath($path));
    }

    private function getColorSql()
    {
        $path = $this->projectDir . '/src/Acme/Bundle/AppBundle/Resources/fixtures/colors.sql';

        return file_get_contents(realpath($path));
    }
}
