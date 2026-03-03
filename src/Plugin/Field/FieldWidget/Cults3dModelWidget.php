<?php

namespace Drupal\cults3d_embed\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'cults3d_model_widget' widget.
 *
 * @FieldWidget(
 *   id = "cults3d_model_widget",
 *   label = @Translation("Cults3D Model URL"),
 *   field_types = {
 *     "cults3d_model"
 *   }
 * )
 */
class Cults3dModelWidget extends WidgetBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a Cults3dModelWidget.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ClientInterface $http_client, DateFormatterInterface $date_formatter, ConfigFactoryInterface $config_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->httpClient = $http_client;
    $this->dateFormatter = $date_formatter;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('http_client'),
      $container->get('date.formatter'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta];

    $element['cults3d_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Cults3D Model URL'),
      '#default_value' => $item->cults3d_url ?? '',
      '#maxlength' => 512,
      '#description' => $this->t('Paste a Cults3D model URL (e.g. https://cults3d.com/en/3d-model/...).'),
      '#element_validate' => [[$this, 'validateUrl']],
    ];

    // Show existing fetched data as a preview.
    if (!empty($item->model_name)) {
      $fetched_date = $item->fetched_at
        ? $this->dateFormatter->format($item->fetched_at, 'short')
        : $this->t('Never');
      $element['preview'] = [
        '#type' => 'details',
        '#title' => $this->t('Fetched data preview'),
        '#open' => TRUE,
        'info' => [
          '#markup' => '<p><strong>' . htmlspecialchars($item->model_name) . '</strong><br>'
          . htmlspecialchars($item->description_summary ?? '') . '<br>'
          . $this->t('Downloads: @count | Price: @price', [
            '@count' => $item->download_count ?? 0,
            '@price' => $item->price ?? 'Free',
          ]) . '<br>'
          . $this->t('Last fetched: @date', [
            '@date' => $fetched_date,
          ]) . '</p>',
        ],
      ];
    }

    // Hidden fields to pass through existing values.
    $hidden_keys = [
      'model_name',
      'description_summary',
      'download_count',
      'likes_count',
      'views_count',
      'price',
      'thumbnail_url',
      'fetched_at',
    ];
    foreach ($hidden_keys as $key) {
      $element[$key] = [
        '#type' => 'hidden',
        '#default_value' => $item->{$key} ?? '',
      ];
    }

    return $element;
  }

  /**
   * Validate the URL format.
   */
  public function validateUrl($element, FormStateInterface $form_state, $form) {
    $url = $element['#value'];
    if (!empty($url) && !preg_match('#https?://cults3d\.com/.+/3d-model/.+/.+#', $url)) {
      $form_state->setError($element, $this->t('The URL must be a valid Cults3D model page (e.g. https://cults3d.com/en/3d-model/category/slug).'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      $url = $value['cults3d_url'] ?? '';
      if (empty($url)) {
        continue;
      }

      // Extract slug from URL.
      $slug = $this->extractSlug($url);
      if (empty($slug)) {
        continue;
      }

      // Fetch data from Cults3D API.
      $fetched = $this->fetchFromApi($slug);
      if ($fetched) {
        $value['model_name'] = $fetched['model_name'];
        $value['description_summary'] = $fetched['description_summary'];
        $value['download_count'] = $fetched['download_count'];
        $value['likes_count'] = $fetched['likes_count'];
        $value['views_count'] = $fetched['views_count'];
        $value['price'] = $fetched['price'];
        $value['thumbnail_url'] = $fetched['thumbnail_url'];
        $value['fetched_at'] = time();
      }
      // If fetch failed but we have existing data, keep it (hidden fields).
    }

    return $values;
  }

  /**
   * Extract the slug from a Cults3D URL.
   *
   * URL format: https://cults3d.com/en/3d-model/{category}/{slug}
   * The API slug is just the final path segment (the model slug).
   *
   * @param string $url
   *   The Cults3D model URL.
   *
   * @return string|null
   *   The extracted slug, or NULL if the URL does not match.
   */
  protected function extractSlug(string $url): ?string {
    if (preg_match('#cults3d\.com/[a-z]{2}/3d-model/[^/]+/([^/?]+)#', $url, $matches)) {
      return $matches[1];
    }
    return NULL;
  }

  /**
   * Fetch model data from the Cults3D GraphQL API.
   *
   * @param string $slug
   *   The model slug.
   *
   * @return array|null
   *   An associative array of model data, or NULL on failure.
   */
  protected function fetchFromApi(string $slug): ?array {
    $config = $this->configFactory->get('cults3d_embed.settings');
    $username = $config->get('api_username');
    $api_key = $config->get('api_key');

    if (empty($username) || empty($api_key)) {
      $this->messenger()->addError($this->t('Cults3D API credentials are not configured. Go to /admin/config/services/cults3d.'));
      return NULL;
    }

    $query = 'query { creation(slug: "' . addslashes($slug) . '") { name(locale: EN) description url downloadsCount likesCount viewsCount price(currency: USD) { formatted cents } illustrationImageUrl } }';

    try {
      $response = $this->httpClient->post('https://cults3d.com/graphql', [
        'auth' => [$username, $api_key],
        'json' => ['query' => $query],
        'timeout' => 15,
      ]);

      $data = json_decode($response->getBody(), TRUE);

      if (empty($data['data']['creation'])) {
        $this->messenger()->addWarning($this->t('No model found for slug: @slug', ['@slug' => $slug]));
        return NULL;
      }

      $creation = $data['data']['creation'];

      // Strip HTML and truncate description to 300 chars at word boundary.
      $description = strip_tags($creation['description'] ?? '');
      if (mb_strlen($description) > 300) {
        $description = mb_substr($description, 0, 300);
        $last_space = mb_strrpos($description, ' ');
        if ($last_space !== FALSE) {
          $description = mb_substr($description, 0, $last_space);
        }
        $description .= '...';
      }

      $price_data = $creation['price'] ?? NULL;
      if ($price_data === NULL || (int) ($price_data['cents'] ?? 0) === 0) {
        $price = 'Free';
      }
      else {
        $price = $price_data['formatted'] ?? 'Free';
      }

      return [
        'model_name' => $creation['name'] ?? '',
        'description_summary' => $description,
        'download_count' => (int) ($creation['downloadsCount'] ?? 0),
        'likes_count' => (int) ($creation['likesCount'] ?? 0),
        'views_count' => (int) ($creation['viewsCount'] ?? 0),
        'price' => $price,
        'thumbnail_url' => $creation['illustrationImageUrl'] ?? '',
      ];
    }
    catch (GuzzleException $e) {
      $this->messenger()->addError($this->t('Cults3D API fetch failed: @message', ['@message' => $e->getMessage()]));
      return NULL;
    }
  }

}
