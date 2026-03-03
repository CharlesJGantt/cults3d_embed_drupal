<?php

namespace Drupal\cults3d_embed\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the Cults3D API proxy endpoint.
 */
class Cults3dApiProxyController extends ControllerBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a Cults3dApiProxyController.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Fetch model data from Cults3D API.
   */
  public function fetch(Request $request) {
    $content = json_decode($request->getContent(), TRUE);
    $url = $content['url'] ?? '';

    if (empty($url)) {
      return new JsonResponse(['error' => 'No URL provided.'], 400);
    }

    // Extract slug from URL (just the final path segment).
    if (!preg_match('#cults3d\.com/[a-z]{2}/3d-model/[^/]+/([^/?]+)#', $url, $matches)) {
      return new JsonResponse(['error' => 'Invalid Cults3D URL format.'], 400);
    }

    $slug = $matches[1];

    $config = $this->config('cults3d_embed.settings');
    $username = $config->get('api_username');
    $api_key = $config->get('api_key');

    if (empty($username) || empty($api_key)) {
      return new JsonResponse(['error' => 'Cults3D API credentials not configured.'], 500);
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
        return new JsonResponse(['error' => 'No model found for the given URL.'], 404);
      }

      $creation = $data['data']['creation'];

      // Strip HTML and truncate description.
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

      return new JsonResponse([
        'name' => $creation['name'] ?? '',
        'description' => $description,
        'download_count' => (int) ($creation['downloadsCount'] ?? 0),
        'likes_count' => (int) ($creation['likesCount'] ?? 0),
        'views_count' => (int) ($creation['viewsCount'] ?? 0),
        'price' => $price,
        'thumbnail_url' => $creation['illustrationImageUrl'] ?? '',
        'cults3d_url' => $url,
      ]);
    }
    catch (GuzzleException $e) {
      return new JsonResponse(['error' => 'API request failed.'], 500);
    }
  }

}
