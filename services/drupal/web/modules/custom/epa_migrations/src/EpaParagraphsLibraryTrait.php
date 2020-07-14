<?php

namespace Drupal\epa_migrations;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\paragraphs_library\Entity\LibraryItem;

/**
 * Helpers to create Paragraph Library items.
 */
trait EpaParagraphsLibraryTrait {

  /**
   * Create Paragraph Library Item.
   *
   * @param string $label
   *   The label for this Library Item.
   * @param \Drupal\paragraphs\Entity\Paragraph[]|\Drupal\paragraphs\Entity\Paragraph $paragraphs
   *   The paragraphs to store on this Library Item.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   *
   * @return \Drupal\paragraphs_library\Entity\LibraryItem
   *   The saved Paragraph Library Item.
   */
  public function createParagraphLibraryItem(string $label, $paragraphs, EntityTypeManager $entityTypeManager) {

    $library_item = $entityTypeManager->getStorage('paragraphs_library_item')
      ->create([
        'label' => $label,
        'paragraphs' => $paragraphs,
      ]);
    $library_item->save();

    return $library_item;
  }

  /**
   * Fetch a Paragraph Library Item.
   *
   * @param string $label
   *   The label for the item.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   *
   * @return \Drupal\paragraphs_library\Entity\LibraryItem
   *   The Paragraphs Library Item.
   */
  public function getParagraphLibraryItem(string $label, EntityTypeManager $entityTypeManager) {
    $library_item = $entityTypeManager->getStorage('paragraphs_library_item')
      ->loadByProperties([
        'label' => $label,
      ]);

    return reset($library_item);
  }

  /**
   * Create from_library paragraph.
   *
   * @param \Drupal\paragraphs_library\Entity\LibraryItem $library_item
   *   A paragraph library item.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   *
   * @return paragraph
   *   The saved from_library paragraph.
   */
  public function createFromLibraryParagraph(LibraryItem $library_item, EntityTypeManager $entityTypeManager) {

    $from_library = $entityTypeManager->getStorage('paragraph')
      ->create([
        'type' => 'from_library',
        'field_reusable_paragraph' => $library_item,
      ]);
    $from_library->save();

    return $from_library;
  }

}
