<?php

namespace Drupal\products\Plugin\Block;

use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Drupal\node\NodeInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a 'Product QR Code' block.
 *
 * @Block(
 *  id = "product_qr_code",
 *  admin_label = @Translation("Product QR Code"),
 *  category = @Translation("Products"),
 * )
 */
class ProductQRCodeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  const QR_CODE_IMAGE_PATH = 'public://';

  /**
   * RouteMatch service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $node = $this->routeMatch->getParameter('node');

    // Return empty build array if conditions are not met.
    if (empty($node) || !($node instanceof NodeInterface)) {
      return $build;
    }

    // Show QR only for products.
    if ($node->bundle() !== 'product') {
      return $build;
    }

    $link_value = $node->get('field_purchase_link')->getValue();
    if (empty($link_value[0]['uri'])) {
      return $build;
    }

    // Generate the QR code.
    $this->generateQrCode($link_value[0]['uri'], $node->id());

    $build = [
      '#theme' => 'product_qr_code',
      '#image' => $this->buildImage($node),
    ];
    return $build;

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [
      'url',
    ];
  }

  /**
   * Generates the QR code as a file for the passed string.
   *
   * @param string $link
   *   The link to generate Qr code for.
   * @param int $id
   *   The node id.
   */
  private function generateQrCode(string $link, int $id) {
    $renderer = new ImageRenderer(
      new RendererStyle(400),
      new ImagickImageBackEnd()
    );
    $writer = new Writer($renderer);
    // Adding id to name, makes sure we do not have duplicates, always have the
    // latest link and is easier to reference where needed.
    $writer->writeFile($link, self::QR_CODE_IMAGE_PATH . "product-$id-qrcode.png");
  }

  /**
   * Returns the qr code image build array.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The product node in context.
   */
  private function buildImage(NodeInterface $node) {
    $id = $node->id();
    return [
      '#theme' => 'image',
      '#uri' => self::QR_CODE_IMAGE_PATH . "product-$id-qrcode.png",
      '#width' => '200px',
      '#height' => '200px',
      '#alt' => $node->getTitle(),
    ];
  }

}
