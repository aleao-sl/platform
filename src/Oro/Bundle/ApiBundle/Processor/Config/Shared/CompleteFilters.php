<?php

namespace Oro\Bundle\ApiBundle\Processor\Config\Shared;

use Doctrine\ORM\Mapping\ClassMetadata;

use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Bundle\ApiBundle\Config\EntityConfigInterface;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Config\FiltersConfig;
use Oro\Bundle\ApiBundle\Processor\Config\ConfigContext;
use Oro\Bundle\ApiBundle\Request\DataType;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;

/**
 * Makes sure that the filters configuration contains all supported filters
 * and all filters are fully configured.
 */
class CompleteFilters extends CompleteSection
{
    /** @var array [data type => true, ...] */
    protected $disallowArrayDataTypes;

    /**
     * @param DoctrineHelper $doctrineHelper
     * @param string[]       $disallowArrayDataTypes
     */
    public function __construct(DoctrineHelper $doctrineHelper, array $disallowArrayDataTypes)
    {
        parent::__construct($doctrineHelper);
        $this->disallowArrayDataTypes = array_fill_keys($disallowArrayDataTypes, true);
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var ConfigContext $context */

        $this->complete($context->getFilters(), $context->getClassName(), $context->getResult());
    }

    /**
     * {@inheritdoc}
     */
    protected function completeFields(
        EntityConfigInterface $section,
        $entityClass,
        EntityDefinitionConfig $definition
    ) {
        $metadata = $this->doctrineHelper->getEntityMetadataForClass($entityClass);

        /** @var FiltersConfig $section */
        $this->completePreConfiguredFieldFilters($section, $metadata, $definition);
        $this->completeIdentifierFieldFilters($section, $metadata, $definition);
        $this->completeIndexedFieldFilters($section, $metadata, $definition);
        $this->completeAssociationFilters($section, $metadata, $definition);
        $this->completeExtendedAssociationFilters($section, $metadata, $definition);
    }

    /**
     * @param FiltersConfig          $filters
     * @param ClassMetadata          $metadata
     * @param EntityDefinitionConfig $definition
     */
    protected function completePreConfiguredFieldFilters(
        FiltersConfig $filters,
        ClassMetadata $metadata,
        EntityDefinitionConfig $definition
    ) {
        $filtersFields = $filters->getFields();
        foreach ($filtersFields as $fieldName => $filter) {
            $propertyPath = $filter->getPropertyPath();
            $field = $definition->getField($fieldName);
            if (null !== $field) {
                $propertyPath = $field->getPropertyPath();
                if (!$filter->hasDataType()) {
                    $dataType = $field->getDataType();
                    if ($dataType) {
                        $filter->setDataType($dataType);
                    }
                }
            }
            if (!$propertyPath) {
                $propertyPath = $fieldName;
            }

            $dataType = $filter->getDataType();
            if (!$dataType && $metadata->hasField($propertyPath)) {
                $dataType = $metadata->getTypeOfField($propertyPath);
                $filter->setDataType($dataType);
            }
            if (!$filter->hasArrayAllowed() && $dataType) {
                $filter->setArrayAllowed(!isset($this->disallowArrayDataTypes[$dataType]));
            }
        }
    }

    /**
     * @param FiltersConfig          $filters
     * @param ClassMetadata          $metadata
     * @param EntityDefinitionConfig $definition
     */
    protected function completeIdentifierFieldFilters(
        FiltersConfig $filters,
        ClassMetadata $metadata,
        EntityDefinitionConfig $definition
    ) {
        $idFieldNames = $definition->getIdentifierFieldNames();
        foreach ($idFieldNames as $fieldName) {
            $field = $definition->getField($fieldName);
            if (null !== $field) {
                $filter = $filters->getOrAddField($fieldName);
                if (!$filter->hasDataType()) {
                    $dataType = $field->getDataType();
                    if (!$dataType) {
                        $dataType = $this->doctrineHelper->getFieldDataType(
                            $metadata,
                            $field->getPropertyPath($fieldName)
                        );
                        if (!$dataType) {
                            $dataType = DataType::STRING;
                        }
                    }
                    $filter->setDataType($dataType);
                }
                if (!$filter->hasArrayAllowed()) {
                    $filter->setArrayAllowed();
                }
            }
        }
    }

    /**
     * @param FiltersConfig          $filters
     * @param ClassMetadata          $metadata
     * @param EntityDefinitionConfig $definition
     */
    protected function completeIndexedFieldFilters(
        FiltersConfig $filters,
        ClassMetadata $metadata,
        EntityDefinitionConfig $definition
    ) {
        $indexedFields = $this->doctrineHelper->getIndexedFields($metadata);
        foreach ($indexedFields as $propertyPath => $dataType) {
            $fieldName = $definition->findFieldNameByPropertyPath($propertyPath);
            if ($fieldName) {
                $filter = $filters->getOrAddField($fieldName);
                if (!$filter->hasDataType()) {
                    $filter->setDataType($dataType);
                } else {
                    $dataType = $filter->getDataType();
                }
                if (!$filter->hasArrayAllowed()) {
                    $filter->setArrayAllowed(!isset($this->disallowArrayDataTypes[$dataType]));
                }
            }
        }
    }

    /**
     * @param FiltersConfig          $filters
     * @param ClassMetadata          $metadata
     * @param EntityDefinitionConfig $definition
     */
    protected function completeAssociationFilters(
        FiltersConfig $filters,
        ClassMetadata $metadata,
        EntityDefinitionConfig $definition
    ) {
        $relations = $this->doctrineHelper->getIndexedAssociations($metadata);
        foreach ($relations as $propertyPath => $dataType) {
            $fieldName = $definition->findFieldNameByPropertyPath($propertyPath);
            if ($fieldName) {
                $filter = $filters->getOrAddField($fieldName);
                if (!$filter->hasDataType()) {
                    $filter->setDataType($dataType);
                }
                if (!$filter->hasArrayAllowed()) {
                    $filter->setArrayAllowed();
                }
            }
        }
    }

    /**
     * @param FiltersConfig          $filters
     * @param ClassMetadata          $metadata
     * @param EntityDefinitionConfig $definition
     */
    protected function completeExtendedAssociationFilters(
        FiltersConfig $filters,
        ClassMetadata $metadata,
        EntityDefinitionConfig $definition
    ) {
        $fields = $definition->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($field->isExcluded()) {
                continue;
            }
            $dataType = $field->getDataType();
            if (!DataType::isExtendedAssociation($dataType)) {
                continue;
            }

            $filter = $filters->getOrAddField($fieldName);
            if (!$filter->hasDataType()) {
                $filter->setDataType(DataType::INTEGER);
            }
            if (!$filter->hasType()) {
                $filter->setType('association');
            }
            if (!$filter->hasArrayAllowed()) {
                $filter->setArrayAllowed();
            }
            $options = $filter->getOptions();
            if (null === $options) {
                $options = [];
            }
            list($associationType, $associationKind) = DataType::parseExtendedAssociation($dataType);
            $options = array_replace($options, [
                'associationOwnerClass' => $metadata->name,
                'associationType'       => $associationType,
                'associationKind'       => $associationKind
            ]);
            $filter->setOptions($options);
        }
    }
}
