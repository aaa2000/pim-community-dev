<?php
namespace Pim\Bundle\DemoBundle\DataFixtures\ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Pim\Bundle\ProductBundle\Entity\ProductAttributeTranslation;
use Pim\Bundle\ProductBundle\Entity\ProductAttribute;
use Oro\Bundle\FlexibleEntityBundle\AttributeType\AbstractAttributeType;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Symfony\Component\Yaml\Yaml;

/**
 * Load fixtures for Product attributes
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2012 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 */
class LoadAttributeData extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface
{

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Get entity manager
     * @return Oro\Bundle\FlexibleEntityBundle\Manager\FlexibleManager
     */
    protected function getProductManager()
    {
        return $this->container->get('pim_product.manager.product');
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $configuration = Yaml::parse(realpath(__DIR__ .'/../../Resources/config/attributes.yml'));

        foreach ($configuration['attributes'] as $code => $data) {
            $attribute = $this->createAttribute($code, $data);
            $manager->persist($attribute);
            $this->addReference('product-attribute.'.$attribute->getCode(), $attribute);
        }

        $manager->flush();
    }

    /**
     * Create a new attribute
     *
     * @param string $code
     * @param array  $data
     */
    public function createAttribute($code, $data)
    {
        $attribute = $this->getProductManager()->createAttribute($data['type']);
        $attribute->setCode($code);
        $attribute->setLabel($data['labels']['default']);
        $attribute->setDescription($data['description']);
        $attribute->setGroup($this->getReference('attribute-group.'.$data['group']));

        foreach ($data['labels'] as $locale => $label) {
            $translation = $this->createTranslation($attribute, $locale, $label);
            $attribute->addTranslation($translation);
        }

        $parameters = $this->prepareParameters($data);
        $attribute->setParameters($parameters);

        if (isset($data['options'])) {
            $options = $this->prepareOptions($data['options']);
            foreach ($options as $option) {
                $attribute->addOption($option);
            }
        }

        return $attribute;
    }

    /**
     * Create a translation entity
     *
     * @param ProductAttribute $attribute ProductAttribute entity
     * @param string           $locale    Locale used
     * @param string           $content   Translated content
     *
     * @return \Pim\Bundle\ProductBundle\Entity\ProductAttributeTranslation
     */
    public function createTranslation($attribute, $locale, $content)
    {
        $translation = new ProductAttributeTranslation();
        $translation->setContent($content);
        $translation->setField('label');
        $translation->setForeignKey($attribute);
        $translation->setLocale($locale);
        $translation->setObjectClass('Pim\Bundle\ProductBundle\Entity\ProductAttribute');

        return $translation;
    }

    /**
     * Prepare parameters
     *
     * @param array $data
     *
     * @return \Pim\Bundle\ProductBundle\Entity\ProductAttributeTranslation
     */
    public function prepareParameters($data)
    {
        $parameters = $data['parameters'];
        $parameters['dateMin']= (isset($parameters['dateMin'])) ? new \DateTime($parameters['dateMin']) : null;
        $parameters['dateMax']= (isset($parameters['dateMax'])) ? new \DateTime($parameters['dateMax']) : null;

        return $parameters;
    }

    /**
     * Prepare attribute options
     *
     * @param array $data the options data
     *
     * @return array
     */
    public function prepareOptions($data)
    {
        $options = array();
        foreach ($data as $code => $data) {
            $option = $this->getProductManager()->createAttributeOption();
            $option->setTranslatable(true);
            $labels = $data['labels'];
            $option->setDefaultValue($labels['en_US']);
            foreach ($labels as $locale => $translated) {
                $optionValue = $this->getProductManager()->createAttributeOptionValue();
                $optionValue->setValue($translated);
                $optionValue->setLocale($locale);
                $option->addOptionValue($optionValue);
            }
            $options[]= $option;
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder()
    {
        return 20;
    }
}
