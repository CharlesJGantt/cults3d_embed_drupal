<?php

namespace Drupal\cults3d_embed\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to render Cults3D model cards from CKEditor embeds.
 *
 * @Filter(
 *   id = "cults3d_embed_card",
 *   title = @Translation("Cults3D Model Card"),
 *   description = @Translation("Renders embedded Cults3D model cards inserted via the CKEditor plugin."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 10
 * )
 */
class Cults3dCardFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (strpos($text, 'cults3d-embed-card-wrapper') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $nodes = $xpath->query('//div[contains(@class, "cults3d-embed-card-wrapper")]');

    if ($nodes->length === 0) {
      return $result;
    }

    foreach ($nodes as $node) {
      $url = $node->getAttribute('data-cults3d-url');
      $name = $node->getAttribute('data-cults3d-name');
      $desc = $node->getAttribute('data-cults3d-desc');
      $downloads = $node->getAttribute('data-cults3d-downloads');
      $price = $node->getAttribute('data-cults3d-price');
      $thumb = $node->getAttribute('data-cults3d-thumb');

      // Sanitize values.
      $url = Xss::filter($url);
      $name = Xss::filter($name);
      $desc = Xss::filter($desc);
      $downloads = Xss::filter($downloads);
      $price = $price ?: 'Free';
      $price = Xss::filter($price);
      $thumb = Xss::filter($thumb);

      // Truncate description to 300 chars at word boundary.
      $desc = $this->truncateDescription($desc, 300);

      $render = [
        '#theme' => 'cults3d_embed_card',
        '#model_name' => $name,
        '#description_summary' => $desc,
        '#download_count' => $downloads,
        '#price' => $price,
        '#thumbnail_url' => $thumb,
        '#cults3d_url' => $url,
      ];

      $card_html = (string) \Drupal::service('renderer')->renderPlain($render);

      // Parse the rendered card HTML into a temporary DOM document,
      // then import nodes into the main DOM (avoids appendXML XML parsing).
      $card_dom = Html::load($card_html);
      $card_body = $card_dom->getElementsByTagName('body')->item(0);
      if ($card_body) {
        foreach ($card_body->childNodes as $child) {
          $imported = $dom->importNode($child, TRUE);
          $node->parentNode->insertBefore($imported, $node);
        }
      }
      $node->parentNode->removeChild($node);
    }

    $result->setProcessedText(Html::serialize($dom));
    $result->addAttachments([
      'library' => ['cults3d_embed/card'],
    ]);

    return $result;
  }

  /**
   * Truncates text to a maximum length at a word boundary.
   */
  private function truncateDescription(string $text, int $max_length): string {
    // Strip any HTML that might be in the description.
    $text = strip_tags($text);
    // Normalize whitespace.
    $text = preg_replace('/\s+/', ' ', trim($text));

    if (mb_strlen($text) <= $max_length) {
      return $text;
    }

    $truncated = mb_substr($text, 0, $max_length);
    $last_space = mb_strrpos($truncated, ' ');
    if ($last_space !== FALSE) {
      $truncated = mb_substr($truncated, 0, $last_space);
    }

    return $truncated . '...';
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Cults3D model cards inserted via the toolbar button will be rendered as styled cards.');
  }

}
