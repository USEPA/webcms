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
   * The recipients of the email.
   * @var array $recipients
   */
  private array $recipients;

  /**
   * The expiring content view rendered results.
   * @var string $body
   */
  private string $body;

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
   * Set body.
   *
   * @param string $body
   *
   * @return void
   */
  public function setBody(string $body): void {
    $this->body = $body;
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
  /**
   * Get body.
   *
   * @return string
   */
  public function getBody(): string {
    return $this->body;
  }

  /**
   * Create the gid value so it can be used as view filter.
   *
   * @param $url_encode bool
   *   Whether the resulting string needs to be encoded or not.
   *
   * @return string
   */
  public function getViewGidValue($url_encode = FALSE): string {
    $gid_parameter = sprintf('"%s (%s)"',$this->getLabel(),$this->getId());
    return $url_encode ? urlencode($gid_parameter) : $gid_parameter;

  }

}
