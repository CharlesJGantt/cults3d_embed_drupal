<?php

namespace Drupal\cults3d_embed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Cults3D API settings.
 */
class Cults3dSettingsForm extends ConfigFormBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a Cults3dSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct($config_factory, ClientInterface $http_client) {
    parent::__construct($config_factory);
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cults3d_embed.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cults3d_embed_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cults3d_embed.settings');

    $form['api_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Username'),
      '#default_value' => $config->get('api_username'),
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('Leave blank to keep the existing key.'),
      '#attributes' => [
        'placeholder' => $config->get('api_key') ? $this->t('Key is set (enter new value to change)') : '',
      ],
    ];

    $form['test_connection'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test Connection'),
      '#submit' => ['::testConnection'],
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('cults3d_embed.settings');
    $config->set('api_username', $form_state->getValue('api_username'));

    $api_key = $form_state->getValue('api_key');
    if (!empty($api_key)) {
      $config->set('api_key', $api_key);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Test connection submit handler.
   */
  public function testConnection(array &$form, FormStateInterface $form_state) {
    $username = $form_state->getValue('api_username') ?: $this->config('cults3d_embed.settings')->get('api_username');
    $api_key = $form_state->getValue('api_key') ?: $this->config('cults3d_embed.settings')->get('api_key');

    if (empty($username) || empty($api_key)) {
      $this->messenger()->addError($this->t('Please provide both username and API key.'));
      return;
    }

    $query = 'query { creation(slug: "the-5-inch-big-jerk-open-pour-mold-by-makingbaits-com") { name(locale: EN) } }';

    try {
      $response = $this->httpClient->post('https://cults3d.com/graphql', [
        'auth' => [$username, $api_key],
        'json' => ['query' => $query],
        'timeout' => 10,
      ]);

      $data = json_decode($response->getBody(), TRUE);

      if (!empty($data['data']['creation']['name'])) {
        $this->messenger()->addStatus($this->t('Connection successful! Fetched model: @name', [
          '@name' => $data['data']['creation']['name'],
        ]));
      }
      else {
        $this->messenger()->addWarning($this->t('Connection succeeded but no data was returned. Check your credentials.'));
      }
    }
    catch (GuzzleException $e) {
      $this->messenger()->addError($this->t('Connection failed: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

}
