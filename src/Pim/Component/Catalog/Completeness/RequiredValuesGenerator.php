<?php

declare(strict_types=1);

namespace Pim\Component\Catalog\Completeness;

use Pim\Component\Catalog\Factory\ValueFactory;
use Pim\Component\Catalog\Model\FamilyInterface;
use Pim\Component\Catalog\Model\ValueCollection;

/**
 * {description}
 *
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class RequiredValuesGenerator
{
    /** @var ValueFactory */
    private $valueFactory;

    /**
     * @param ValueFactory $valueFactory
     */
    public function __construct(ValueFactory $valueFactory)
    {
        $this->valueFactory = $valueFactory;
    }

    /**
     * Generates a two dimensional array indexed by channel and locale containing
     * the required product values for those channel/locale combinations. Those
     * are determined from the attribute requirements of the product family and
     * from the channel activated locales.
     *
     * This method takes into account the localizable and scopable characteristic
     * of the product value and local specific characteristic of the attribute.
     *
     * For example, you have 2 channels "mobile" and "print", two locales "en_US"
     * and "fr_FR", and the following attrbutes:
     * - "name" is non scopable and not localisable,
     * - "short_description" is scopable,
     * - "long_description" is scobable and localisable.
     *
     * The resulting array of product values would be like:
     * [
     *     "mobile" => [
     *         "en_US" => [
     *             ValueCollection {
     *                 name product value,
     *                 short_description-mobile product value,
     *                 long_description-mobile-en_US product value,
     *             }
     *         ],
     *         "fr_FR" => [
     *             ValueCollection {
     *                 name product value,
     *                 short_description-mobile product value,
     *                 long_description-mobile-fr_FR product value,
     *             }
     *         ],
     *     ],
     *     "print"  => [
     *         "en_US" => [
     *             ValueCollection {
     *                 name product value,
     *                 short_description-print product value,
     *                 long_description-print-en_US product value,
     *             }
     *         ],
     *         "fr_FR" => [
     *             ValueCollection {
     *                 name product value,
     *                 short_description-print product value,
     *                 long_description-print-fr_FR product value,
     *             }
     *         ],
     *     ],
     * ]
     *
     * @param FamilyInterface $family
     *
     * @return array
     */
    public function generate(FamilyInterface $family): array
    {
        $values = [];

        foreach ($family->getAttributeRequirements() as $attributeRequirement) {
            foreach ($attributeRequirement->getChannel()->getLocales() as $locale) {
                if ($attributeRequirement->isRequired()) {
                    $channelCode = $attributeRequirement->getChannelCode();
                    $localeCode = $locale->getCode();

                    $attribute = $attributeRequirement->getAttribute();
                    if ($attribute->isLocaleSpecific() && !$attribute->hasLocaleSpecific($locale)) {
                        continue;
                    }

                    $productValue = $this->valueFactory->create(
                        $attribute,
                        $attribute->isScopable() ? $channelCode : null,
                        $attribute->isLocalizable() ? $localeCode : null,
                        null
                    );

                    if (!isset($values[$channelCode][$localeCode])) {
                        $values[$channelCode][$localeCode] = new ValueCollection();
                    }
                    $values[$channelCode][$localeCode]->add($productValue);
                }
            }
        }

        return $values;
    }
}
