<?php

namespace Drupal\epa_core;

use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\image\Controller\ImageStyleDownloadController;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extend system FileDownloadController.
 */
class EPAImageStyleDownloadController extends ImageStyleDownloadController {

  /**
   * EPAFileDownload service.
   *
   * @var \Drupal\epa_core\EPAFileDownload
   */
  protected $epaFileDownload;

  /**
   * EPAFileDownloadController constructor.
   *
   * @param \Drupal\epa_core\EPAFileDownload $epa_file_download
   *   The epaFileDownload service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   */
  public function __construct(EPAFileDownload $epa_file_download, LockBackendInterface $lock, ImageFactory $image_factory, StreamWrapperManagerInterface $stream_wrapper_manager = NULL) {
    parent::__construct($lock, $image_factory, $stream_wrapper_manager);
    $this->epaFileDownload = $epa_file_download;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('epa_core.private_file_download'),
      $container->get('lock'),
      $container->get('image.factory'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * If current user is anonymous, make a decision about whether to
   * serve file based on the associated media.
   */
  public function deliver(Request $request, $scheme, ImageStyleInterface $image_style) {
    $this->epaFileDownload->privateFileCheck($request, $scheme);
    $response = parent::deliver($request, $scheme, $image_style);
    return $response;
  }

}
