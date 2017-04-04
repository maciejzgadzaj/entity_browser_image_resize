<?php

namespace Drupal\entity_browser_image_resize\EventSubscriber;

use Drupal\entity_browser\Events\EntitySelectionEvent;
use Drupal\entity_browser\Events\Events;
use Drupal\file_entity\Entity\FileEntity;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EntityBrowserImageResizeEventSubscriber.
 */
class EntityBrowserImageResizeEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[Events::SELECTED][] = ['onSelected'];

    return $events;
  }

  /**
   * This method is called whenever the "entity_browser.selected" event is dispatched.
   *
   * @param EntitySelectionEvent $event
   *   The event.
   */
  public function onSelected(EntitySelectionEvent $event) {
    $style_name = 'entity_browser_image_resize';
    if (!$style = ImageStyle::load($style_name)) {
      return;
    }

    $image_factory = \Drupal::service('image.factory');

    $files = $event->getEntities();
    /** @var FileEntity $source_file */
    foreach ($files as $source_file) {

      $source_uri = $source_file->getFileUri();
      /** @var \Drupal\Core\Image\Image $source_image */
      $source_image = $image_factory->get($source_uri);

      // Do not continue if source file is not an image.
      if (!$source_image->isValid()) {
        return;
      }

      $destination_uri = $style->buildUri($source_uri);
      if ($style->createDerivative($source_uri, $destination_uri)) {
        /** @var \Drupal\Core\Image\Image $destination_image */
        $destination_image = $image_factory->get($destination_uri);

        if (file_unmanaged_move($destination_uri, $source_uri, FILE_EXISTS_REPLACE)) {
          // Update original image file with new file size.
          $source_file->setSize($destination_image->getFileSize());
          $source_file->save();

          drupal_set_message(t('Image "@file_name" resized successfully:<ul><li>original image: @source_widthx@source_height px, @source_file_size</li><li>resized image: @destination_widthx@destination_height px, @destination_file_size</li></ul>', [
            '@file_name' => $source_file->getFilename(),
            '@source_width' => $source_image->getWidth(),
            '@source_height' => $source_image->getHeight(),
            '@source_file_size' => format_size($source_image->getFileSize()),
            '@destination_width' => $destination_image->getWidth(),
            '@destination_height' => $destination_image->getHeight(),
            '@destination_file_size' => format_size($destination_image->getFileSize()),
          ]));
        }
        else {
          drupal_set_message(t('Moving resized image failed.'), 'error');
        }
      }
      else {
        drupal_set_message(t('Image resizing failed.'), 'error');
      }
    }
  }

}
