<?php

declare(strict_types=1);

namespace Pim\Component\Catalog\Completeness;

use Akeneo\Component\StorageUtils\Repository\CachedObjectRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Pim\Component\Catalog\Model\ChannelInterface;
use Pim\Component\Catalog\Model\CompletenessInterface;
use Pim\Component\Catalog\Model\LocaleInterface;
use Pim\Component\Catalog\Model\ProductInterface;
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
    /** @var CachedObjectRepositoryInterface */
    private $channelRepository;

    /** @var CachedObjectRepositoryInterface */
    private $localeRepository;

    /** @var MissingRequiredAttributesCalculator */
    private $missingRequiredAttributesCalculator;

    /** @var string */
    private $completenessClass;

    /** @var RequiredValuesGenerator */
    private $requiredValuedGenerator;

    /**
     * @param CachedObjectRepositoryInterface     $channelRepository
     * @param CachedObjectRepositoryInterface     $localeRepository
     * @param MissingRequiredAttributesCalculator $missingRequiredAttributesCalculator
     * @param RequiredValuesGenerator             $requiredValuesGenerator
     * @param string                              $completenessClass
     *
     * @internal param ValueFactory $productValueFactory
     * @internal param ValueCompleteCheckerInterface $productValueCompleteChecker
     */
    public function __construct(
        CachedObjectRepositoryInterface $channelRepository,
        CachedObjectRepositoryInterface $localeRepository,
        MissingRequiredAttributesCalculator $missingRequiredAttributesCalculator,
        RequiredValuesGenerator $requiredValuesGenerator,
        $completenessClass
    ) {
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
        $this->missingRequiredAttributesCalculator = $missingRequiredAttributesCalculator;
        $this->requiredValuedGenerator = $requiredValuesGenerator;
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

        $requiredProductValues = $this->requiredValuedGenerator->generate($product->getFamily());
        $missingRequiredAttributes = $this->missingRequiredAttributesCalculator->generate(
            $product->getValues(),
            $requiredProductValues
        );

        foreach ($requiredProductValues as $channelCode => $requiredValuesByChannel) {
            foreach ($requiredValuesByChannel as $localeCode => $requiredValuesByChannelAndLocale) {
                $missingRequiredAttributesForChannelAndLocale = $missingRequiredAttributes[$channelCode][$localeCode];

                $channel = $this->channelRepository->findOneByIdentifier($channelCode);
                $locale = $this->localeRepository->findOneByIdentifier($localeCode);

                $completenesses[] = $this->generateCompleteness(
                    $product,
                    $channel,
                    $locale,
                    $requiredValuesByChannelAndLocale,
                    $missingRequiredAttributesForChannelAndLocale
                );
            }
        }

        return $completenesses;
    }

    /**
     * Generates one completeness for the given required product value, channel
     * code, locale code, required attribute list and the missing missing required attributes list.
     *
     * @param ProductInterface          $product
     * @param ChannelInterface          $channel
     * @param LocaleInterface           $locale
     * @param ValueCollectionInterface  $requiredValues
     * @param MissingRequiredAttributes $missingRequiredAttributes
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
