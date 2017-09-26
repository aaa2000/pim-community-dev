<?php

declare(strict_types=1);

namespace Pim\Component\Catalog\Completeness;

use Pim\Component\Catalog\Model\AttributeInterface;

/**
 * Object that holds the list of missing required attributes used by the CompletenessCalculator.
 *
 * The CompletenessCalculator uses it to generate the completeness for a product, channel and scope.
 *
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MissingRequiredAttributes implements \Countable
{
    /** @var array */
    protected $attributes = [];

    /**
     * It adds an attribute to the list of missing required attribute if it is not already present.
     *
     * @param AttributeInterface $attribute
     */
    public function add(AttributeInterface $attribute): void
    {
        $attributeCode = $attribute->getCode();

        if (!array_key_exists($attributeCode, $this->attributes)) {
            $this->attributes[$attributeCode] = $attribute;
        }
    }

    /**
     * Returns a list of attributes that are missing.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return array_values($this->attributes);
    }

    /**
     * @return String[]
     */
    public function getAttributeCodes(): array
    {
        return array_keys($this->attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->attributes);
    }
}
