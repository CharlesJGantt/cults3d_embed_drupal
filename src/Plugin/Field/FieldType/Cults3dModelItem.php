<?php

namespace Drupal\cults3d_embed\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'cults3d_model' field type.
 *
 * @FieldType(
 *   id = "cults3d_model",
 *   label = @Translation("Cults3D Model"),
 *   description = @Translation("Stores a Cults3D model URL and snapshotted metadata."),
 *   default_widget = "cults3d_model_widget",
 *   default_formatter = "cults3d_model_card",
 * )
 */
class Cults3dModelItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'cults3d_url' => [
          'type' => 'varchar',
          'length' => 512,
        ],
        'model_name' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'description_summary' => [
          'type' => 'text',
          'size' => 'normal',
        ],
        'download_count' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'default' => 0,
        ],
        'price' => [
          'type' => 'varchar',
          'length' => 64,
        ],
        'thumbnail_url' => [
          'type' => 'varchar',
          'length' => 512,
        ],
        'fetched_at' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'default' => 0,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    $properties['cults3d_url'] = DataDefinition::create('string')
      ->setLabel(t('Cults3D URL'));

    $properties['model_name'] = DataDefinition::create('string')
      ->setLabel(t('Model Name'));

    $properties['description_summary'] = DataDefinition::create('string')
      ->setLabel(t('Description Summary'));

    $properties['download_count'] = DataDefinition::create('integer')
      ->setLabel(t('Download Count'));

    $properties['price'] = DataDefinition::create('string')
      ->setLabel(t('Price'));

    $properties['thumbnail_url'] = DataDefinition::create('string')
      ->setLabel(t('Thumbnail URL'));

    $properties['fetched_at'] = DataDefinition::create('integer')
      ->setLabel(t('Fetched At'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $url = $this->get('cults3d_url')->getValue();
    return empty($url);
  }

}
