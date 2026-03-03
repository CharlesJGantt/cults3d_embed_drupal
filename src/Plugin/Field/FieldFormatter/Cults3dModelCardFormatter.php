<?php

namespace Drupal\cults3d_embed\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'cults3d_model_card' formatter.
 *
 * @FieldFormatter(
 *   id = "cults3d_model_card",
 *   label = @Translation("Cults3D Model Card"),
 *   field_types = {
 *     "cults3d_model"
 *   }
 * )
 */
class Cults3dModelCardFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      if ($item->isEmpty()) {
        continue;
      }

      $elements[$delta] = [
        '#theme' => 'cults3d_embed_card',
        '#model_name' => $item->model_name,
        '#description_summary' => $item->description_summary,
        '#download_count' => $item->download_count,
        '#likes_count' => $item->likes_count,
        '#views_count' => $item->views_count,
        '#price' => $item->price ?: 'Free',
        '#thumbnail_url' => $item->thumbnail_url,
        '#cults3d_url' => $item->cults3d_url,
        '#attached' => [
          'library' => ['cults3d_embed/card'],
        ],
      ];
    }

    return $elements;
  }

}
