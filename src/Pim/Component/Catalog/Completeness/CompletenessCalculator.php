<?php

declare(strict_types=1);

namespace Pim\Component\Catalog\Completeness;

use Akeneo\Component\StorageUtils\Repository\CachedObjectRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Pim\Component\Catalog\Completeness\Checker\ValueCompleteCheckerInterface;
use Pim\Component\Catalog\Factory\ValueFactory;
use Pim\Component\Catalog\Model\ChannelInterface;
use Pim\Component\Catalog\Model\CompletenessInterface;
use Pim\Component\Catalog\Model\FamilyInterface;
use Pim\Component\Catalog\Model\LocaleInterface;
use Pim\Component\Catalog\Model\ProductInterface;
use Pim\Component\Catalog\Model\ValueCollection;
use Pim\Component\Catalog\Model\ValueCollectionInterface;

/**
 * Calculates the completenesses for a provided product.
 *
 * This calculator creates a "fake" collection of required product values
 * according to the product family requirements. Then, it compares this
 * collection of fake values with the real values of the product, and generates
 * a list of completenesses, one completeness for each channel/locale possible
 * combinations.
 *
 * @author    Damien Carcel (damien.carcel@akeneo.com)
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class CompletenessCalculator implements CompletenessCalculatorInterface
{
    /** @var ValueFactory */
    protected $productValueFactory;

    /** @var CachedObjectRepositoryInterface */
    protected $channelRepository;

    /** @var CachedObjectRepositoryInterface */
    protected $localeRepository;

    /** @var ValueCompleteCheckerInterface */
    protected $productValueCompleteChecker;

    /** @var string */
    protected $completenessClass;

    /**
     * @param ValueFactory                    $productValueFactory
     * @param CachedObjectRepositoryInterface $channelRepository
     * @param CachedObjectRepositoryInterface $localeRepository
     * @param ValueCompleteCheckerInterface   $productValueCompleteChecker
     * @param string                          $completenessClass
     */
    public function __construct(
        ValueFactory $productValueFactory,
        CachedObjectRepositoryInterface $channelRepository,
        CachedObjectRepositoryInterface $localeRepository,
        ValueCompleteCheckerInterface $productValueCompleteChecker,
        $completenessClass
    ) {
        $this->productValueFactory = $productValueFactory;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
        $this->productValueCompleteChecker = $productValueCompleteChecker;
        $this->completenessClass = $completenessClass;
    }

    /**
     * {@inheritdoc}
     */
    public function calculate(ProductInterface $product): array
    {
        if (null === $product->getFamily()) {
            return [];
        }

        $completenesses = [];
        $requiredProductValues = $this->getRequiredValues($product->getFamily());

        foreach ($requiredProductValues as $channelCode => $requiredProductValuesByChannel) {
            foreach ($requiredProductValuesByChannel as $localeCode => $requiredProductValuesByChannelAndLocale) {
                $channel = $this->channelRepository->findOneByIdentifier($channelCode);
                $locale = $this->localeRepository->findOneByIdentifier($localeCode);

                $missingRequiredAttributes = $this->generateMissingAttributes(
                    $product->getValues(),
                    $channel,
                    $locale,
                    $requiredProductValuesByChannelAndLocale
                );

                $completenesses[] = $this->generateCompleteness(
                    $product,
                    $channel,
                    $locale,
                    $requiredProductValuesByChannelAndLocale,
                    $missingRequiredAttributes
                );
            }
        }

        return $completenesses;
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
    protected function getRequiredValues(FamilyInterface $family): array
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

                    $productValue = $this->productValueFactory->create(
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

    /**
     * @param ValueCollectionInterface $productValues
     * @param ChannelInterface         $channel
     * @param LocaleInterface          $locale
     * @param ValueCollectionInterface $requiredValues
     *
     * @return MissingRequiredAttributes
     */
    protected function generateMissingAttributes(
        ValueCollectionInterface $productValues,
        ChannelInterface $channel,
        LocaleInterface $locale,
        ValueCollectionInterface $requiredValues
    ): MissingRequiredAttributes {
        $missingRequiredAttribute = new MissingRequiredAttributes();

        foreach ($requiredValues as $requiredValue) {
            $attribute = $requiredValue->getAttribute();

            $productValue = $productValues->getByCodes(
                $attribute->getCode(),
                $requiredValue->getScope(),
                $requiredValue->getLocale()
            );

            if (null === $productValue ||
                !$this->productValueCompleteChecker->isComplete($productValue, $channel, $locale)
            ) {
                $missingRequiredAttribute->add($attribute);
            }
        }

        return $missingRequiredAttribute;
    }

    /**
     * Generates one completeness for the given required product value, channel
     * code, locale code, required attribute list and the missing missing required attributes list.
     *
     * @param ProductInterface               $product
     * @param ChannelInterface               $channel
     * @param LocaleInterface                $locale
     * @param ValueCollectionInterface $requiredValues
     * @param MissingRequiredAttributes      $missingRequiredAttributes
     *
     * @return CompletenessInterface
     */
    protected function generateCompleteness(
        ProductInterface $product,
        ChannelInterface $channel,
        LocaleInterface $locale,
        ValueCollectionInterface $requiredValues,
        MissingRequiredAttributes $missingRequiredAttributes
    ): CompletenessInterface {
        $missingAttributesCount = count($missingRequiredAttributes);
        $requiredAttributesCount = count($requiredValues);
        $missingRequiredAttributes = new ArrayCollection($missingRequiredAttributes->getAttributes());

        $completeness = new $this->completenessClass(
            $product,
            $channel,
            $locale,
            $missingRequiredAttributes,
            $missingAttributesCount,
            $requiredAttributesCount
        );

        return $completeness;
    }
}
