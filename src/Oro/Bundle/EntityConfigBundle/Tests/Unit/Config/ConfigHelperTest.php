<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Config;

use Oro\Bundle\EntityConfigBundle\Config\ConfigHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\ConfigIdInterface;
use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

class ConfigHelperTest extends \PHPUnit_Framework_TestCase
{
    const FIELD_NAME = 'someExtendFieldName';
    const ENTITY_CLASS_NAME = 'Oro\Bundle\SomeBundle\Entity\SomeEntity';

    /** @var ConfigManager|\PHPUnit_Framework_MockObject_MockObject */
    private $configManager;

    /** @var FieldConfigModel|\PHPUnit_Framework_MockObject_MockObject */
    private $fieldConfigModel;

    /** @var ConfigHelper */
    private $configHelper;

    protected function setUp()
    {
        $this->configManager = $this->getMockBuilder(ConfigManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->fieldConfigModel = $this->createMock(FieldConfigModel::class);

        $this->configHelper = new ConfigHelper($this->configManager);
    }

    public function testGetExtendRequireJsModules()
    {
        $modules = ['module1'];

        $configProvider = $this->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $propertyConfig = $this->getMockBuilder(PropertyConfigContainer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $propertyConfig
            ->expects($this->once())
            ->method('getRequireJsModules')
            ->willReturn($modules);

        $configProvider
            ->expects($this->once())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfig);

        $this->configManager
            ->expects($this->once())
            ->method('getProvider')
            ->with('extend')
            ->willReturn($configProvider);

        $this->assertEquals($modules, $this->configHelper->getExtendRequireJsModules());
    }

    public function testGetEntityConfigByField()
    {
        $scope = 'scope';
        $className = 'className';
        $fieldConfigModel = $this->createMock(FieldConfigModel::class);

        $entityConfigModel = $this->createMock(EntityConfigModel::class);

        $entityConfigModel
            ->expects($this->once())
            ->method('getClassName')
            ->willReturn($className);

        $fieldConfigModel
            ->expects($this->once())
            ->method('getEntity')
            ->willReturn($entityConfigModel);

        $configProvider = $this->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $entityConfig = $this->createMock(ConfigInterface::class);

        $configProvider
            ->expects($this->once())
            ->method('getConfig')
            ->with($className)
            ->willReturn($entityConfig);

        $this->configManager
            ->expects($this->once())
            ->method('getProvider')
            ->with($scope)
            ->willReturn($configProvider);

        $this->assertEquals($entityConfig, $this->configHelper->getEntityConfigByField($fieldConfigModel, $scope));
    }

    public function testGetFieldConfig()
    {
        $scope = 'scope';
        $className = 'className';
        $fieldName = 'fieldName';

        $this->fieldConfigModel
            ->expects($this->once())
            ->method('getFieldName')
            ->willReturn($fieldName);

        $entityConfigModel = $this->createMock(EntityConfigModel::class);

        $entityConfigModel
            ->expects($this->once())
            ->method('getClassName')
            ->willReturn($className);

        $this->fieldConfigModel
            ->expects($this->once())
            ->method('getEntity')
            ->willReturn($entityConfigModel);

        $fieldConfig = $this->createMock(ConfigInterface::class);

        $configProvider = $this->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configProvider
            ->expects($this->once())
            ->method('getConfig')
            ->with($className, $fieldName)
            ->willReturn($fieldConfig);

        $this->configManager
            ->expects($this->once())
            ->method('getProvider')
            ->with($scope)
            ->willReturn($configProvider);

        $this->assertEquals($fieldConfig, $this->configHelper->getFieldConfig($this->fieldConfigModel, $scope));
    }

    public function testFilterEntityConfigByField()
    {
        $scope = 'scope';
        $className = 'className';
        $filterResults = ['one', 'two'];
        $callback = function () {
        };

        $entityConfigModel = $this->createMock(EntityConfigModel::class);

        $entityConfigModel
            ->expects($this->once())
            ->method('getClassName')
            ->willReturn($className);

        $this->fieldConfigModel
            ->expects($this->once())
            ->method('getEntity')
            ->willReturn($entityConfigModel);

        $configProvider = $this->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configProvider
            ->expects($this->once())
            ->method('filter')
            ->with($callback, $className)
            ->willReturn($filterResults);

        $this->configManager
            ->expects($this->once())
            ->method('getProvider')
            ->with($scope)
            ->willReturn($configProvider);

        $this->assertEquals(
            $filterResults,
            $this->configHelper->filterEntityConfigByField($this->fieldConfigModel, $scope, $callback)
        );
    }

    public function simpleFieldTypeDataProvider()
    {
        return [
            [
                'fieldType' => 'bigint',
                'additionalFieldOptions' => [],
                'expectedFieldType' => 'bigint',
                'expectedAdditionalFieldOptions' => [
                    'extend' => [
                        'is_extend' => true,
                        'origin' => ExtendScope::ORIGIN_CUSTOM,
                        'owner' => ExtendScope::OWNER_CUSTOM,
                        'state' => ExtendScope::STATE_NEW
                    ]
                ],
            ],
            [
                'fieldType' => 'boolean',
                'additionalFieldOptions' => [
                    'extend' => [
                        'someOption' => 'SomeValue'
                    ],
                    'attribute' => [
                        'is_attribute' => true
                    ]
                ],
                'expectedFieldType' => 'boolean',
                'expectedAdditionalFieldOptions' => [
                    'extend' => [
                        'is_extend' => true,
                        'origin' => ExtendScope::ORIGIN_CUSTOM,
                        'owner' => ExtendScope::OWNER_CUSTOM,
                        'state' => ExtendScope::STATE_NEW,
                        'someOption' => 'SomeValue'
                    ],
                    'attribute' => [
                        'is_attribute' => true
                    ]
                ],
            ],
            [
                'fieldType' => 'enum||some_enum_code',
                'additionalFieldOptions' => [],
                'expectedFieldType' => 'enum',
                'expectedAdditionalFieldOptions' => [
                    'extend' => [
                        'is_extend' => true,
                        'origin' => ExtendScope::ORIGIN_CUSTOM,
                        'owner' => ExtendScope::OWNER_CUSTOM,
                        'state' => ExtendScope::STATE_NEW
                    ],
                    'enum' => [
                        'enum_code' => 'some_enum_code'
                    ]
                ],
            ],
            [
                'fieldType' => 'oneToMany|owning_entity|target_entity|field_name_in_owning_entityenum||some',
                'additionalFieldOptions' => [],
                'expectedFieldType' => 'manyToOne',
                'expectedAdditionalFieldOptions' => [
                    'extend' => [
                        'is_extend' => true,
                        'origin' => ExtendScope::ORIGIN_CUSTOM,
                        'owner' => ExtendScope::OWNER_CUSTOM,
                        'state' => ExtendScope::STATE_NEW,
                        'relation_key' => 'oneToMany|owning_entity|target_entity|field_name_in_owning_entityenum',
                        'target_entity' => null
                    ],
                ],
            ]
        ];
    }

    /**
     * @dataProvider simpleFieldTypeDataProvider
     * @param string $fieldType
     * @param array $additionalFieldOptions
     * @param string $expectedFieldType
     * @param array $expectedAdditionalFieldOptions
     */
    public function testCreateFieldOptionsWhenSimpleFieldTypeIsGiven(
        $fieldType,
        $additionalFieldOptions,
        $expectedFieldType,
        $expectedAdditionalFieldOptions
    ) {
        $extendEntityConfig = $this->createMock(ConfigInterface::class);

        $result = $this->configHelper->createFieldOptions($extendEntityConfig, $fieldType, $additionalFieldOptions);

        $this->assertEquals([$expectedFieldType, $expectedAdditionalFieldOptions], $result);
    }

    public function testGetEntityConfig()
    {
        $className = 'Oro\Bundle\SomeBundle\Entity\SomeEntity';
        $scope = 'scope';

        $entityConfigModel = $this->createMock(EntityConfigModel::class);
        $entityConfigModel
            ->expects($this->once())
            ->method('getClassName')
            ->willReturn($className);

        $entityConfig = $this->createMock(ConfigInterface::class);

        $configProvider = $this->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configProvider
            ->expects($this->once())
            ->method('getConfig')
            ->with($className)
            ->willReturn($entityConfig);

        $this->configManager
            ->expects($this->once())
            ->method('getProvider')
            ->with($scope)
            ->willReturn($configProvider);

        $this->assertEquals($entityConfig, $this->configHelper->getEntityConfig($entityConfigModel, $scope));
    }

    private function expectsGetClassNameAndFieldName()
    {
        $this->fieldConfigModel
            ->expects($this->once())
            ->method('getFieldName')
            ->willReturn(self::FIELD_NAME);

        $entityConfigModel = $this->createMock(EntityConfigModel::class);
        $entityConfigModel
            ->expects($this->once())
            ->method('getClassName')
            ->willReturn(self::ENTITY_CLASS_NAME);

        $this->fieldConfigModel
            ->expects($this->once())
            ->method('getEntity')
            ->willReturn($entityConfigModel);
    }

    /**
     * @param string $scope
     * @param ConfigInterface $returnedConfig
     * @return ConfigProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    private function expectsGetProviderByScope($scope, ConfigInterface $returnedConfig)
    {
        $configProvider = $this->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configProvider
            ->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS_NAME, self::FIELD_NAME)
            ->willReturn($returnedConfig);

        $this->configManager
            ->expects($this->once())
            ->method('getProvider')
            ->with($scope)
            ->willReturn($configProvider);

        return $configProvider;
    }

    public function testUpdateFieldConfigsWhenNothingHasChanged()
    {
        $options = [
            'extend' => [
                'state' => ExtendScope::STATE_ACTIVE
            ],
        ];

        $this->expectsGetClassNameAndFieldName();

        $config = $this->createMock(ConfigInterface::class);
        $configProvider = $this->expectsGetProviderByScope('extend', $config);
        $configProvider
            ->expects($this->never())
            ->method('getPropertyConfig');

        $config
            ->expects($this->once())
            ->method('is')
            ->with('state', ExtendScope::STATE_ACTIVE)
            ->willReturn(true);

        $this->configManager
            ->expects($this->never())
            ->method('persist');

        $this->fieldConfigModel
            ->expects($this->never())
            ->method('fromArray');

        $this->configHelper->updateFieldConfigs($this->fieldConfigModel, $options);
    }

    public function testUpdateFieldConfigsWhenOptionValueHasChanged()
    {
        $options = [
            'extend' => [
                'state' => ExtendScope::STATE_ACTIVE
            ],
        ];
        $all = [
            'state' => ExtendScope::STATE_ACTIVE
        ];
        $indexedValues = ['state' => true];
        $scope = 'extend';

        $this->expectsGetClassNameAndFieldName();

        $config = $this->createMock(ConfigInterface::class);
        $configProvider = $this->expectsGetProviderByScope($scope, $config);

        $propertyConfigContainer = $this->getMockBuilder(PropertyConfigContainer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configProvider
            ->expects($this->once())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);

        $config
            ->expects($this->once())
            ->method('is')
            ->with('state', ExtendScope::STATE_ACTIVE)
            ->willReturn(false);

        $config
            ->expects($this->once())
            ->method('set')
            ->with('state', ExtendScope::STATE_ACTIVE);

        $config
            ->expects($this->once())
            ->method('all')
            ->willReturn($all);

        $configId = $this->createMock(ConfigIdInterface::class);

        $configId
            ->expects($this->once())
            ->method('getScope')
            ->willReturn($scope);

        $config
            ->expects($this->exactly(2))
            ->method('getId')
            ->willReturn($configId);

        $propertyConfigContainer
            ->expects($this->once())
            ->method('getIndexedValues')
            ->with($configId)
            ->willReturn($indexedValues);

        $this->configManager
            ->expects($this->once())
            ->method('persist')
            ->with($config);

        $this->fieldConfigModel
            ->expects($this->once())
            ->method('fromArray')
            ->with($scope, $all, $indexedValues);

        $this->configHelper->updateFieldConfigs($this->fieldConfigModel, $options);
    }
}
