<?php

declare(strict_types=1);

namespace Pim\Component\Catalog\Completeness;

use Akeneo\Component\StorageUtils\Repository\CachedObjectRepositoryInterface;
use Pim\Component\Catalog\Completeness\Checker\ValueCompleteCheckerInterface;
use Pim\Component\Catalog\Model\ValueCollectionInterface;

/**
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MissingRequiredAttributesCalculator
{
    /** @var CachedObjectRepositoryInterface */
    private $channelRepository;

    /** @var CachedObjectRepositoryInterface */
    private $localeRepository;

    /** @var ValueCompleteCheckerInterface */
    private $valueCompleteChecker;

    /**
     * @param ValueCompleteCheckerInterface   $valueCompleteChecker
     * @param CachedObjectRepositoryInterface $channelRepository
     * @param CachedObjectRepositoryInterface $localeRepository
     */
    public function __construct(
        ValueCompleteCheckerInterface $valueCompleteChecker,
        CachedObjectRepositoryInterface $channelRepository,
        CachedObjectRepositoryInterface $localeRepository
    ) {
        $this->valueCompleteChecker = $valueCompleteChecker;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
    }

    /**
     * Generates a two dimenssionnal array....
     *
     * @param ValueCollectionInterface $productValues
     * @param ValueCollectionInterface $requiredValues
     *
     * @return array
     */
    public function generate(
        ValueCollectionInterface $productValues,
        array $requiredValues
    ): array {
        $missingRequiredAttributes = [];

        foreach ($requiredValues as $channelCode => $requiredValuesByChannel) {
            foreach ($requiredValuesByChannel as $localeCode => $requiredValuesByChannelAndLocale) {
                $missingRequiredAttributesForChannelAndLocale = new MissingRequiredAttributes();

                $channel = $this->channelRepository->findOneByIdentifier($channelCode);
                $locale = $this->localeRepository->findOneByIdentifier($localeCode);

                foreach ($requiredValuesByChannelAndLocale as $requiredValue) {
                    $attribute = $requiredValue->getAttribute();

                    $productValue = $productValues->getByCodes(
                        $attribute->getCode(),
                        $requiredValue->getScope(),
                        $requiredValue->getLocale()
                    );

                    if (null === $productValue ||
                        !$this->valueCompleteChecker->isComplete($productValue, $channel, $locale)
                    ) {
                        $missingRequiredAttributesForChannelAndLocale->add($attribute);
                    }
                }

                $missingRequiredAttributes[$channelCode][$localeCode] = $missingRequiredAttributesForChannelAndLocale;
            }
        }

        return $missingRequiredAttributes;
    }
}

