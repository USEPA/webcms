<?php

namespace Drupal\epa_workflow;

/**
 * Help class to store data for processing EPA workflow emails.
 */
class EPAWorkflowEmailHandler {

  /**
   * Group id.
   *
   * @var int $id
   */
  private int $id;

  /**
   * Group label.
   *
   * @var string $label
   */
  private string $label;

  /**
   * The recipeints of the email.
   * @var array $recipients
   */
  private array $recipients;

  /**
   * Set group id.
   *
   * @param int $id
   *
   * @return void
   */
  public function setId(int $id): void {
    $this->id = $id;
  }

  /**
   * Set group label.
   *
   * @param string $label
   *
   * @return void
   */
  public function setLabel(string $label): void {
    $this->label = $label;
  }

  /**
   * Set recipients.
   *
   * @param array $recipients
   *
   * @return void
   */
  public function setRecipients(array $recipients): void {
    $this->recipients = $recipients;
  }

  /**
   * Get group id.
   *
   * @return int
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * Get group lablel.
   *
   * @return string
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * Get recipients.
   *
   * @return \Drupal\user\Entity\User[]
   */
  public function getRecipients(): array {
    return $this->recipients;
  }

}
